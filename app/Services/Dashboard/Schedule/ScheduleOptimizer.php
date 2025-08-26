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
        // Set default options
        $maxIterations = $options['max_iterations'] ?? 100;
        $timeout = $options['timeout'] ?? 60; // seconds

        $startTime = microtime(true);
        $currentIteration = 0;
        $bestSchedule = $schedules;
        $bestScore = $this->evaluateSchedule($schedules);

        // Load section subjects for constraint validation
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
            // Create a modified schedule
            $candidateSchedule = $this->createCandidateSchedule(
                $bestSchedule,
                $teacherAvailabilities,
                $sectionSchedules,
                $sectionSubjects
            );

            // Validate the candidate schedule against hard constraints
            $errors = $this->constraintChecker->validateSchedule(
                $candidateSchedule,
                $teacherAvailabilities,
                $sectionSubjects
            );

            // Only consider valid schedules
            if (empty($errors)) {
                // Evaluate the candidate schedule
                $candidateScore = $this->evaluateSchedule($candidateSchedule);

                // If the candidate is better, keep it
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

        // Randomly select two assignments to swap
        if (count($candidateSchedule) >= 2) {
            $index1 = array_rand($candidateSchedule);
            $index2 = array_rand($candidateSchedule);

            // Ensure we're not swapping the same assignment
            while ($index1 === $index2) {
                $index2 = array_rand($candidateSchedule);
            }

            // Get the two assignments
            $assignment1 = $candidateSchedule[$index1];
            $assignment2 = $candidateSchedule[$index2];

            // Try different swap strategies
            $swapStrategy = rand(0, 2);

            switch ($swapStrategy) {
                case 0:
                    // Swap periods and days
                    $tempPeriod = $assignment1['period_id'];
                    $tempDay = $assignment1['day_of_week'];

                    $candidateSchedule[$index1]['period_id'] = $assignment2['period_id'];
                    $candidateSchedule[$index1]['day_of_week'] = $assignment2['day_of_week'];

                    $candidateSchedule[$index2]['period_id'] = $tempPeriod;
                    $candidateSchedule[$index2]['day_of_week'] = $tempDay;
                    break;

                case 1:
                    // Swap teachers
                    $tempTeacher = $assignment1['teacher_id'];
                    $candidateSchedule[$index1]['teacher_id'] = $assignment2['teacher_id'];
                    $candidateSchedule[$index2]['teacher_id'] = $tempTeacher;
                    break;

                case 2:
                    // Find alternative slots for one assignment
                    $assignment = $assignment1;
                    $sectionId = $assignment['section_id'];
                    $teacherId = $assignment['teacher_id'];

                    // Get teacher availabilities
                    $availabilities = $teacherAvailabilities
                        ->where('teacher_id', $teacherId)
                        ->map(function ($availability) {
                            return [
                                'period_id' => $availability->period_id,
                                'day_of_week' => $availability->day_of_week
                            ];
                        });

                    // Get section schedules
                    $sectionSlots = $sectionSchedules
                        ->where('section_id', $sectionId)
                        ->map(function ($schedule) {
                            return [
                                'period_id' => $schedule->period_id,
                                'day_of_week' => $schedule->day_of_week
                            ];
                        });

                    // Find intersection of teacher availability and section schedule slots
                    $possibleSlots = $availabilities->filter(function ($teacherSlot) use ($sectionSlots) {
                        return $sectionSlots->contains(function ($sectionSlot) use ($teacherSlot) {
                            return $sectionSlot['period_id'] == $teacherSlot['period_id'] &&
                                $sectionSlot['day_of_week'] == $teacherSlot['day_of_week'];
                        });
                    });

                    // If we found alternative slots, randomly select one
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

        // Group assignments by teacher
        $teacherAssignments = [];
        foreach ($schedule as $assignment) {
            $teacherId = $assignment['teacher_id'];
            if (!isset($teacherAssignments[$teacherId])) {
                $teacherAssignments[$teacherId] = [];
            }
            $teacherAssignments[$teacherId][] = $assignment;
        }

        // Group assignments by section
        $sectionAssignments = [];
        foreach ($schedule as $assignment) {
            $sectionId = $assignment['section_id'];
            if (!isset($sectionAssignments[$sectionId])) {
                $sectionAssignments[$sectionId] = [];
            }
            $sectionAssignments[$sectionId][] = $assignment;
        }

        // Evaluate teacher workload balance
        $teacherWorkloads = array_map('count', $teacherAssignments);
        if (!empty($teacherWorkloads)) {
            $avgWorkload = array_sum($teacherWorkloads) / count($teacherWorkloads);
            $workloadVariance = 0;

            foreach ($teacherWorkloads as $workload) {
                $workloadVariance += pow($workload - $avgWorkload, 2);
            }

            // Lower variance is better (more balanced workload)
            $workloadScore = 100 / (1 + $workloadVariance);
            $score += $workloadScore;
        }

        // Evaluate consecutive periods for teachers (prefer consecutive periods)
        foreach ($teacherAssignments as $teacherId => $assignments) {
            $consecutiveCount = $this->countConsecutivePeriods($assignments);
            $score += $consecutiveCount * 10;
        }

        // Evaluate gaps in section schedules (fewer gaps is better)
        foreach ($sectionAssignments as $sectionId => $assignments) {
            $gapCount = $this->countScheduleGaps($assignments);
            $score -= $gapCount * 5;
        }

        // Evaluate subject distribution across the week (more distributed is better)
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

        // Group by day
        $dayAssignments = [];
        foreach ($assignments as $assignment) {
            $day = $assignment['day_of_week'];
            if (!isset($dayAssignments[$day])) {
                $dayAssignments[$day] = [];
            }
            $dayAssignments[$day][] = $assignment['period_id'];
        }

        // Count consecutive periods for each day
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

        // Group by day
        $dayAssignments = [];
        foreach ($assignments as $assignment) {
            $day = $assignment['day_of_week'];
            if (!isset($dayAssignments[$day])) {
                $dayAssignments[$day] = [];
            }
            $dayAssignments[$day][] = $assignment['period_id'];
        }

        // Count gaps for each day
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
        // Group by subject
        $subjectAssignments = [];
        foreach ($assignments as $assignment) {
            $subjectId = $assignment['subject_id'];
            if (!isset($subjectAssignments[$subjectId])) {
                $subjectAssignments[$subjectId] = [];
            }
            $subjectAssignments[$subjectId][] = $assignment['day_of_week'];
        }

        $distributionScore = 0;

        // Calculate distribution score for each subject
        foreach ($subjectAssignments as $subjectId => $days) {
            // Count unique days
            $uniqueDays = count(array_unique($days));

            // More unique days is better
            $distributionScore += $uniqueDays;

            // Penalize multiple lessons of the same subject on the same day
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
