<?php

namespace App\Services\Dashboard\Schedule;

use App\Models\SectionSchedule;

use Illuminate\Support\Collection;

class ScheduleOptimizer
{
    protected $constraintChecker;

    /**
     * Create a new optimizer instance.
     *
     * @param ConstraintChecker $constraintChecker
     */
    public function __construct(ConstraintChecker $constraintChecker)
    {
        $this->constraintChecker = $constraintChecker;
    }

    /**
     * Optimize the generated schedule.
     *
     * @param array $schedules
     * @param Collection $teacherAvailabilities
     * @param Collection $sectionSchedules
     * @param array $options
     * @return array
     */
    public function optimize(
        array $schedules,
        Collection $teacherAvailabilities,
        Collection $sectionSchedules,
        array $options = []
    ): array {
        $maxIterations = $options['max_iterations'] ?? 100;
        $timeout = $options['timeout'] ?? 60;
        $startTime = microtime(true);
        $currentIteration = 0;
        $bestSchedule = $schedules;
        $bestScore = $this->evaluateSchedule($schedules);
        $sectionSubjects = collect();
        foreach ($schedules as $schedule) {
            $sectionSubject = \App\Models\SectionSubject::where('section_id', $schedule['section_id'])
                ->where('subject_id', $schedule['subject_id'])
                ->first();
            if ($sectionSubject) {
                $sectionSubjects->push($sectionSubject);
            }
        }
        while ($currentIteration < $maxIterations && (microtime(true) - $startTime) < $timeout) {
            $candidateSchedule = $this->createCandidateSchedule(
                $bestSchedule,
                $teacherAvailabilities,
                $sectionSchedules,
                $sectionSubjects
            );
            $errors = $this->constraintChecker->validateSchedule(
                $candidateSchedule,
                $teacherAvailabilities,
                $sectionSubjects
            );
            if (empty($errors)) {
                $candidateScore = $this->evaluateSchedule($candidateSchedule);
                if ($candidateScore > $bestScore) {
                    $bestSchedule = $candidateSchedule;
                    $bestScore = $candidateScore;
                }
            }
            $currentIteration++;
        }
        return $bestSchedule;
    }

    /**
     * Create a candidate schedule by making small modifications to the current best schedule.
     *
     * @param array $schedule
     * @param Collection $teacherAvailabilities
     * @param Collection $sectionSchedules
     * @param Collection $sectionSubjects
     * @return array
     */
    protected function createCandidateSchedule(
        array $schedule,
        Collection $teacherAvailabilities,
        Collection $sectionSchedules,
        Collection $sectionSubjects
    ): array {
        $candidateSchedule = $schedule;
        if (count($candidateSchedule) >= 2) {
            $index1 = array_rand($candidateSchedule);
            $index2 = array_rand($candidateSchedule);
            while ($index1 === $index2) {
                $index2 = array_rand($candidateSchedule);
            }
            $assignment1 = $candidateSchedule[$index1];
            $assignment2 = $candidateSchedule[$index2];
            $swapStrategy = rand(0, 2);
            switch ($swapStrategy) {
                case 0:
                    $tempPeriod = $assignment1['period_id'];
                    $tempDay = $assignment1['day_of_week'];
                    $candidateSchedule[$index1]['period_id'] = $assignment2['period_id'];
                    $candidateSchedule[$index1]['day_of_week'] = $assignment2['day_of_week'];
                    $candidateSchedule[$index2]['period_id'] = $tempPeriod;
                    $candidateSchedule[$index2]['day_of_week'] = $tempDay;
                    break;
                case 1:
                    $tempTeacher = $assignment1['teacher_id'];
                    $candidateSchedule[$index1]['teacher_id'] = $assignment2['teacher_id'];
                    $candidateSchedule[$index2]['teacher_id'] = $tempTeacher;
                    break;
                case 2:
                    $assignment = $assignment1;
                    $sectionId = $assignment['section_id'];
                    $teacherId = $assignment['teacher_id'];
                    $availabilities = $teacherAvailabilities
                        ->where('teacher_id', $teacherId)
                        ->map(function ($availability) {
                            return [
                                'period_id' => $availability->period_id,
                                'day_of_week' => $availability->day_of_week
                            ];
                        });
                    $sectionSlots = $sectionSchedules
                        ->where('section_id', $sectionId)
                        ->map(function ($schedule) {
                            return [
                                'period_id' => $schedule->period_id,
                                'day_of_week' => $schedule->day_of_week
                            ];
                        });
                    $possibleSlots = $availabilities->filter(function ($teacherSlot) use ($sectionSlots) {
                        return $sectionSlots->contains(function ($sectionSlot) use ($teacherSlot) {
                            return $sectionSlot['period_id'] == $teacherSlot['period_id'] &&
                                $sectionSlot['day_of_week'] == $teacherSlot['day_of_week'];
                        });
                    });
                    if ($possibleSlots->isNotEmpty()) {
                        $newSlot = $possibleSlots->random();
                        $candidateSchedule[$index1]['period_id'] = $newSlot['period_id'];
                        $candidateSchedule[$index1]['day_of_week'] = $newSlot['day_of_week'];
                    }
                    break;
            }
        }

        return $candidateSchedule;
    }

    /**
     * Evaluate a schedule based on soft constraints.
     *
     * @param array $schedule
     * @return float
     */
    protected function evaluateSchedule(array $schedule): float
    {
        $score = 0;
        $teacherAssignments = [];
        foreach ($schedule as $assignment) {
            $teacherId = $assignment['teacher_id'];
            if (!isset($teacherAssignments[$teacherId])) {
                $teacherAssignments[$teacherId] = [];
            }
            $teacherAssignments[$teacherId][] = $assignment;
        }
        $sectionAssignments = [];
        foreach ($schedule as $assignment) {
            $sectionId = $assignment['section_id'];
            if (!isset($sectionAssignments[$sectionId])) {
                $sectionAssignments[$sectionId] = [];
            }
            $sectionAssignments[$sectionId][] = $assignment;
        }
        $teacherWorkloads = array_map('count', $teacherAssignments);
        if (!empty($teacherWorkloads)) {
            $avgWorkload = array_sum($teacherWorkloads) / count($teacherWorkloads);
            $workloadVariance = 0;
            foreach ($teacherWorkloads as $workload) {
                $workloadVariance += pow($workload - $avgWorkload, 2);
            }
            $workloadScore = 100 / (1 + $workloadVariance);
            $score += $workloadScore;
        }
        foreach ($teacherAssignments as $teacherId => $assignments) {
            $consecutiveCount = $this->countConsecutivePeriods($assignments);
            $score += $consecutiveCount * 10;
        }
        foreach ($sectionAssignments as $sectionId => $assignments) {
            $gapCount = $this->countScheduleGaps($assignments);
            $score -= $gapCount * 5;
        }
        foreach ($sectionAssignments as $sectionId => $assignments) {
            $subjectDistribution = $this->evaluateSubjectDistribution($assignments);
            $score += $subjectDistribution * 15;
        }
        return $score;
    }

    /**
     * Count consecutive periods in assignments.
     *
     * @param array $assignments
     * @return int
     */
    protected function countConsecutivePeriods(array $assignments): int
    {
        $consecutiveCount = 0;
        $dayAssignments = [];
        foreach ($assignments as $assignment) {
            $day = $assignment['day_of_week'];
            if (!isset($dayAssignments[$day])) {
                $dayAssignments[$day] = [];
            }
            $dayAssignments[$day][] = $assignment['period_id'];
        }
        foreach ($dayAssignments as $periodIds) {
            sort($periodIds);
            for ($i = 0; $i < count($periodIds) - 1; $i++) {
                if ($periodIds[$i + 1] - $periodIds[$i] === 1) {
                    $consecutiveCount++;
                }
            }
        }
        return $consecutiveCount;
    }

    /**
     * Count gaps in a schedule.
     *
     * @param array $assignments
     * @return int
     */
    protected function countScheduleGaps(array $assignments): int
    {
        $gapCount = 0;
        $dayAssignments = [];
        foreach ($assignments as $assignment) {
            $day = $assignment['day_of_week'];
            if (!isset($dayAssignments[$day])) {
                $dayAssignments[$day] = [];
            }
            $dayAssignments[$day][] = $assignment['period_id'];
        }
        foreach ($dayAssignments as $periodIds) {
            sort($periodIds);
            for ($i = 0; $i < count($periodIds) - 1; $i++) {
                $gap = $periodIds[$i + 1] - $periodIds[$i] - 1;
                if ($gap > 0) {
                    $gapCount += $gap;
                }
            }
        }

        return $gapCount;
    }

    /**
     * Evaluate subject distribution across the week.
     *
     * @param array $assignments
     * @return float
     */
    protected function evaluateSubjectDistribution(array $assignments): float
    {
        $subjectAssignments = [];
        foreach ($assignments as $assignment) {
            $subjectId = $assignment['subject_id'];
            if (!isset($subjectAssignments[$subjectId])) {
                $subjectAssignments[$subjectId] = [];
            }
            $subjectAssignments[$subjectId][] = $assignment['day_of_week'];
        }
        $distributionScore = 0;
        foreach ($subjectAssignments as $subjectId => $days) {
            $uniqueDays = count(array_unique($days));
            $distributionScore += $uniqueDays;
            $dayCounts = array_count_values($days);
            foreach ($dayCounts as $day => $count) {
                if ($count > 1) {
                    $distributionScore -= ($count - 1) * 0.5;
                }
            }
        }
        return $distributionScore;
    }
}