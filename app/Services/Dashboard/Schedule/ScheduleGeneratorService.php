<?php

namespace App\Services\Dashboard\Schedule;

use App\Models\Period;
use App\Models\Section;
use App\Models\SectionSchedule;
use App\Models\SectionSubject;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAvailabilities;
use App\Models\TeacherSectionSubject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScheduleGeneratorService
{
    protected $constraintChecker;
    protected $scheduleOptimizer;
    protected $status = [
        'state' => 'idle',
        'progress' => 0,
        'message' => 'Ready to generate schedule',
        'errors' => [],
    ];

    /**
     * Create a new service instance.
     *
     * @param ConstraintChecker $constraintChecker
     * @param ScheduleOptimizer $scheduleOptimizer
     */
    public function __construct(
        ConstraintChecker $constraintChecker,
        ScheduleOptimizer $scheduleOptimizer
    ) {
        $this->constraintChecker = $constraintChecker;
        $this->scheduleOptimizer = $scheduleOptimizer;
    }

    /**
     * Generate a new schedule.
     *
     * @param array $options
     * @return array
     */
    public function generate(array $options = []): array
    {
        try {
            // Extract manager choices from options
            $getAllSchedules = $options['get_all_schedules'] ?? false;
            $optimizeSchedule = $options['optimize'] ?? false;

            $this->updateStatus('running', 0, 'Starting schedule generation');

            DB::beginTransaction();

            // 1) Load data
            $sections = $this->loadSections();
            $periods = $this->loadPeriods();
            $teacherAvailabilities = $this->loadTeacherAvailabilities();
            $sectionSubjects = $this->loadSectionSubjects();
            $sectionSchedules = $this->loadSectionSchedules();

            // 2) Reset
            $this->resetSchedules();
            $this->updateStatus('running', 5, 'Schedules cleared');

            // 3) Explode & sort
            $demands = $this->explodeSectionSubjectsByAmount($sectionSubjects);
            $sortedDemands = $this->sortByConstraintDifficulty($demands);
            $this->updateStatus('running', 10, 'Demands prepared for scheduling');

            // 4) Generate schedule based on manager's choice
            if ($getAllSchedules) {
                // Generate all valid schedules using backtracking
                $this->updateStatus('running', 15, 'Generating all valid schedules');
                $allSchedules = [];
                $this->backtrack(
                    0,
                    $sortedDemands,
                    [],
                    [],
                    [],
                    $teacherAvailabilities,
                    $sectionSchedules,
                    $allSchedules
                );

                if (empty($allSchedules)) {
                    // No valid schedules found, suggest missing availabilities
                    $suggestions = $this->collectMinimalSuggestions(
                        $sortedDemands,
                        $teacherAvailabilities,
                        $sectionSchedules
                    );

                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => 'No valid schedules found. Here are suggested teacher availability additions.',
                        'suggestions' => $suggestions,
                    ];
                }

                // Save all valid schedules (or limit to a reasonable number)
                $maxSchedulesToSave = min(count($allSchedules), 10); // Limit to 10 schedules
                $savedSchedules = [];

                for ($i = 0; $i < $maxSchedulesToSave; $i++) {
                    $savedSchedules[] = $allSchedules[$i];
                }

                DB::commit();
                return [
                    'success' => true,
                    'message' => 'Found ' . count($allSchedules) . ' valid schedules. Showing ' . $maxSchedulesToSave,
                    'schedules' => $savedSchedules,
                    'total_count' => count($allSchedules)
                ];
            } else {
                // Generate a single schedule using initial assignment
                $this->updateStatus('running', 15, 'Generating a single schedule');
                $schedule = $this->generateInitialSchedule(
                    $sortedDemands,
                    $sections,
                    $periods,
                    $teacherAvailabilities,
                    $sectionSchedules,
                    $options
                );

                if (empty($schedule)) {
                    // No valid schedule found, suggest missing availabilities
                    $suggestions = $this->collectMinimalSuggestions(
                        $sortedDemands,
                        $teacherAvailabilities,
                        $sectionSchedules
                    );

                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => 'Could not generate a valid schedule. Here are suggested teacher availability additions.',
                        'suggestions' => $suggestions,
                    ];
                }

                // Optimize if requested
                if ($optimizeSchedule) {
                    $this->updateStatus('running', 70, 'Optimizing schedule');
                    $schedule = $this->scheduleOptimizer->optimize(
                        $schedule,
                        $teacherAvailabilities,
                        $sectionSchedules,
                        ['max_iterations' => 100]
                    );
                    $this->updateStatus('running', 90, 'Schedule optimized');
                }

                // Save the schedule
                $this->saveSchedule($schedule);

                DB::commit();
                return [
                    'success' => true,
                    'message' => $optimizeSchedule ? 'Generated and optimized schedule' : 'Generated schedule',
                    'schedule' => $schedule,
                ];
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Schedule generation failed: {$e->getMessage()}");
            return [
                'success' => false,
                'message' => 'Schedule generation failed: ' . $e->getMessage()
            ];
        }
    }



    /**
     * Recursively build all valid assignments.
     *
     * @param int        $idx
     * @param Collection $demands
     * @param array      $currentSchedule    Accumulates assignments so far
     * @param array      $assignedTeacher    [teacher_id][day_period] = true
     * @param array      $assignedSection    [section_id][day_period] = true
     * @param Collection $teacherAvailabilities
     * @param Collection $sectionSchedules
     * @param array      &$allSchedules      Collected valid schedules
     * @return void
     */
    protected function backtrack(
        int $idx,
        Collection $demands,
        array $currentSchedule,
        array $assignedTeacher,
        array $assignedSection,
        Collection $teacherAvailabilities,
        Collection $sectionSchedules,
        array &$allSchedules
    ): void {
        // if all demands placed, record one full schedule
        if ($idx >= $demands->count()) {
            $allSchedules[] = $currentSchedule;
            return;
        }

        if (count($allSchedules) >= 100) {
            return;
        }
        $demand = $demands[$idx];
        $sectionId = $demand->section_id;
        $subjectId = $demand->subject_id;

        foreach ($demand->teachers as $teacher) {
            // qualification
            if (!$this->constraintChecker->teacherCanTeach($teacher->id, $demand->id)) {
                continue;
            }

            // find available slots
            $slots = $this->getAvailableSlots(
                $teacher,
                $demand,
                $assignedTeacher,
                $assignedSection,
                $teacherAvailabilities,
                $sectionSchedules,
                ['force_assign' => false]
            );

            foreach ($slots as $slot) {
                $key = "{$slot['day_of_week']}_{$slot['period_id']}";

                // mark
                $assignedTeacher[$teacher->id][$key] = true;
                $assignedSection[$sectionId][$key] = true;

                $currentSchedule[] = [
                    'section_id' => $sectionId,
                    'subject_id' => $subjectId,
                    'teacher_id' => $teacher->id,
                    'period_id' => $slot['period_id'],
                    'day_of_week' => $slot['day_of_week'],
                ];

                // recurse
                $this->backtrack(
                    $idx + 1,
                    $demands,
                    $currentSchedule,
                    $assignedTeacher,
                    $assignedSection,
                    $teacherAvailabilities,
                    $sectionSchedules,
                    $allSchedules
                );

                // undo
                array_pop($currentSchedule);
                unset($assignedTeacher[$teacher->id][$key]);
                unset($assignedSection[$sectionId][$key]);
            }
        }
    }




    /**
     * For each unmet demand, gather all possible (teacher, slot) that could satisfy it.
     * Then compute a greedy hitting‑set of those pairs to cover every demand with the fewest additions.
     *
     * @param Collection $demands
     * @param Collection $teacherAvailabilities
     * @param Collection $sectionSchedules
     * @return array  List of ['teacher_id'=>…, 'day_of_week'=>…, 'period_id'=>…] suggestions
     */
    protected function collectMinimalSuggestions(
        Collection $demands,
        Collection $teacherAvailabilities,
        Collection $sectionSchedules
    ): array {
        // 1) Build demand → optionPairs map
        $demandOptions = [];  // [demandIdx => [ "T|D|P", ... ] ]
        foreach ($demands as $i => $demand) {
            $opts = [];
            foreach ($demand->teachers as $teacher) {
                // find any sectionSlots for this section
                $sectionSlots = $sectionSchedules
                    ->where('section_id', $demand->section_id)
                    ->map(fn($s) => [
                        'period_id' => $s->period_id,
                        'day_of_week' => $s->day_of_week
                    ]);
                // for every slot, if teacher is NOT already available there:
                foreach ($sectionSlots as $slot) {
                    $key = "{$teacher->id}|{$slot['day_of_week']}|{$slot['period_id']}";
                    if (
                        $teacherAvailabilities
                            ->where('teacher_id', $teacher->id)
                            ->where('day_of_week', $slot['day_of_week'])
                            ->where('period_id', $slot['period_id'])
                            ->isEmpty()
                    ) {
                        $opts[$key] = [
                            'teacher_id' => $teacher->id,
                            'day_of_week' => $slot['day_of_week'],
                            'period_id' => $slot['period_id']
                        ];
                    }
                }
            }
            $demandOptions[$i] = $opts;
        }

        // 2) Greedy hitting‑set: choose the option that covers the most uncovered demands
        $uncovered = array_keys($demandOptions);
        $suggestions = [];
        while (!empty($uncovered)) {
            // tally coverage of each option
            $coverage = [];  // [optKey => count]
            foreach ($uncovered as $i) {
                foreach (array_keys($demandOptions[$i]) as $optKey) {
                    $coverage[$optKey] = ($coverage[$optKey] ?? 0) + 1;
                }
            }
            // pick option with max coverage
            arsort($coverage);
            [$bestOptKey,] = array_slice($coverage, 0, 1, true);
            if (!isset($bestOptKey)) {
                break; // no available options
            }
            // record suggestion
            $parts = explode('|', $bestOptKey);
            $suggestions[] = [
                'teacher_id' => (int) $parts[0],
                'day_of_week' => $parts[1],
                'period_id' => (int) $parts[2]
            ];
            // remove all demands covered by this option
            $newUncovered = [];
            foreach ($uncovered as $i) {
                if (!array_key_exists($bestOptKey, $demandOptions[$i])) {
                    $newUncovered[] = $i;
                }
            }
            $uncovered = $newUncovered;
        }

        return $suggestions;
    }

    /**
     * Reset all generated schedules.
     *
     * @return bool
     */
    public function reset(): bool
    {
        try {
            $this->updateStatus('running', 0, 'Resetting schedules');
            $this->resetSchedules();
            $this->updateStatus('completed', 100, 'All schedules have been reset');
            return true;
        } catch (\Exception $e) {
            Log::error('Schedule reset failed: ' . $e->getMessage(), ['exception' => $e]);
            $this->updateStatus('failed', 0, 'Schedule reset failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the current generation status.
     *
     * @return array
     */
    public function getStatus(): array
    {
        return $this->status;
    }

    /**
     * Export the generated schedule.
     *
     * @param string $format
     * @return array
     */
    public function export(string $format = 'json'): array
    {
        $schedules = SectionSchedule::with(['section', 'period', 'subject', 'teacher'])
            ->whereNotNull('subject_id')
            ->whereNotNull('teacher_id')
            ->get();

        if ($format === 'json') {
            return $this->exportAsJson($schedules);
        }

        return [
            'format' => $format,
            'data' => $schedules->toArray()
        ];
    }

    /**
     * Load all sections.
     *
     * @return Collection
     */
    protected function loadSections(): Collection
    {
        return Section::with('classroom')->get();
    }

    /**
     * Load all periods.
     *
     * @return Collection
     */
    protected function loadPeriods(): Collection
    {
        return Period::orderBy('order')->get();
    }

    /**
     * Load all teacher availabilities.
     *
     * @return Collection
     */
    protected function loadTeacherAvailabilities(): Collection
    {
        return TeacherAvailabilities::with('teacher', 'period')->get();
    }

    /**
     * Load all section subjects.
     *
     * @return Collection
     */
    protected function loadSectionSubjects(): Collection
    {
        return SectionSubject::with(['section', 'subject', 'teachers'])->get();
    }

    /**
     * Load all section schedules.
     *
     * @return Collection
     */
    protected function loadSectionSchedules(): Collection
    {
        return SectionSchedule::all();
    }

    /**
     * Reset existing schedules (clear teacher and subject assignments).
     *
     * @return void
     */
    protected function resetSchedules(): void
    {
        SectionSchedule::query()->update([
            'subject_id' => null,
            'teacher_id' => null
        ]);
    }

    /**
     * Explode section subjects by their weekly lesson count (amount).
     *
     * @param Collection $sectionSubjects
     * @return Collection
     */
    protected function explodeSectionSubjectsByAmount(Collection $sectionSubjects): Collection
    {
        $demands = collect();

        foreach ($sectionSubjects as $sectionSubject) {
            $amount = $sectionSubject->subject->amount ?? 1;

            // Create multiple instances based on the amount
            for ($i = 0; $i < $amount; $i++) {
                $demands->push($sectionSubject);
            }
        }

        return $demands;
    }

    /**
     * Sort demands by constraint difficulty.
     *
     * @param Collection $demands
     * @return Collection
     */
    protected function sortByConstraintDifficulty(Collection $demands): Collection
    {
        // Sort section subjects by the number of available slots (fewer slots first)
        return $demands->sortBy(function ($sectionSubject) {
            $availableTeachers = $sectionSubject->teachers->count();
            $availableSlots = 0;

            foreach ($sectionSubject->teachers as $teacher) {
                $availableSlots += TeacherAvailabilities::where('teacher_id', $teacher->id)->count();
            }

            // If no teachers or no available slots, this is the most constrained
            if ($availableTeachers === 0 || $availableSlots === 0) {
                return 0;
            }

            // Return average slots per teacher (lower means more constrained)
            return $availableSlots / $availableTeachers;
        });
    }

    /**
     * Generate initial schedule using constraint-based approach.
     *
     * @param Collection $demands
     * @param Collection $sections
     * @param Collection $periods
     * @param Collection $teacherAvailabilities
     * @param Collection $sectionSchedules
     * @return array
     */
    protected function generateInitialSchedule(
        Collection $demands,
        Collection $sections,
        Collection $periods,
        Collection $teacherAvailabilities,
        Collection $sectionSchedules,
        array $options
    ): array {
        $schedules = [];
        $assignedTeacherSlots = [];
        $assignedSectionSlots = [];
        $forceAssign = $options['force_assign'] ?? false;
        // Process each demand
        foreach ($demands as $index => $sectionSubject) {
            $this->updateStatus(
                'running',
                30 + (40 * $index / $demands->count()),
                "Processing subject {$sectionSubject->subject->name} for section {$sectionSubject->section->name}"
            );

            // Get possible teachers for this section subject
            $possibleTeachers = $sectionSubject->teachers;
            $assigned = false;

            foreach ($possibleTeachers as $teacher) {
                if (!$this->constraintChecker->teacherCanTeach($teacher->id, $sectionSubject->id)) {
                    continue;
                }

                // Pass the force_assign flag down
                $availableSlots = $this->getAvailableSlots(
                    $teacher,
                    $sectionSubject,
                    $assignedTeacherSlots,
                    $assignedSectionSlots,
                    $teacherAvailabilities,
                    $sectionSchedules,
                    ['force_assign' => $forceAssign]
                );

                if ($availableSlots->isEmpty()) {
                    continue;
                }

                // Assign the first available slot
                $slot = $availableSlots->first();
                $schedules[] = [
                    'section_id' => $sectionSubject->section_id,
                    'subject_id' => $sectionSubject->subject_id,
                    'teacher_id' => $teacher->id,
                    'period_id' => $slot['period_id'],
                    'day_of_week' => $slot['day_of_week']
                ];

                $key = "{$slot['day_of_week']}_{$slot['period_id']}";
                $assignedTeacherSlots[$teacher->id][$key] = true;
                $assignedSectionSlots[$sectionSubject->section_id][$key] = true;
                $assigned = true;
                break;
            }

            if (!$assigned) {
                $this->status['errors'][] =
                    "Could not assign {$sectionSubject->subject->name} to section {$sectionSubject->section->name}";
            }
        }


        return $schedules;
    }

    /**
     * Get available slots for a teacher and section subject.
     *
     * @param Teacher $teacher
     * @param SectionSubject $sectionSubject
     * @param array $assignedTeacherSlots
     * @param array $assignedSectionSlots
     * @param Collection $teacherAvailabilities
     * @param Collection $sectionSchedules
     * @return Collection
     */
    protected function getAvailableSlots(
        Teacher $teacher,
        SectionSubject $sectionSubject,
        array $assignedTeacherSlots,
        array $assignedSectionSlots,
        Collection $teacherAvailabilities,
        Collection $sectionSchedules,
        array $options
    ): Collection {
        // 1) Teacher’s declared availabilities
        $teacherSlots = $teacherAvailabilities
            ->where('teacher_id', $teacher->id)
            ->map(fn($a) => [
                'period_id' => $a->period_id,
                'day_of_week' => $a->day_of_week,
            ]);

        // 2) Section’s schedule slots
        $sectionSlots = $sectionSchedules
            ->where('section_id', $sectionSubject->section_id)
            ->map(fn($s) => [
                'period_id' => $s->period_id,
                'day_of_week' => $s->day_of_week,
            ]);

        // 3) Compute intersection or force‑assign fallback
        if (!empty($options['force_assign']) && $teacherSlots->isEmpty()) {
            $available = $sectionSlots;
        } else {
            $available = $teacherSlots->filter(
                fn($tSlot) =>
                $sectionSlots->contains(
                    fn($sSlot) =>
                    $sSlot['period_id'] === $tSlot['period_id']
                    && $sSlot['day_of_week'] === $tSlot['day_of_week']
                )
            );
        }

        // 4) Exclude already‑assigned teacher slots
        $available = $available->reject(
            fn($slot) =>
            isset($assignedTeacherSlots[$teacher->id]["{$slot['day_of_week']}_{$slot['period_id']}"])
        );

        // 5) Exclude already‑assigned section slots
        $available = $available->reject(
            fn($slot) =>
            isset($assignedSectionSlots[$sectionSubject->section_id]["{$slot['day_of_week']}_{$slot['period_id']}"])
        );

        return $available;
    }




    protected function collectChoices(
        Collection $demands,
        Collection $sections,
        Collection $periods,
        Collection $teacherAvailabilities,
        Collection $sectionSchedules
    ): array {
        $choices = [];

        foreach ($demands as $sectionSubject) {
            $sectionId = $sectionSubject->section_id;
            $sectionName = $sectionSubject->section->name;
            $subjectName = $sectionSubject->subject->name;

            foreach ($sectionSubject->teachers as $teacher) {
                // Build list of ALL section slots for that section
                $slots = $sectionSchedules
                    ->where('section_id', $sectionId)
                    ->map(fn($s) => [
                        'period_id' => $s->period_id,
                        'day_of_week' => $s->day_of_week,
                    ])
                    ->unique()
                    ->values()
                    ->all();

                $choices[] = [
                    'teacher_id' => $teacher->id,
                    'teacher_name' => $teacher->user->first_name . ' ' . $teacher->user->last_name,
                    'section_id' => $sectionId,
                    'section_name' => $sectionName,
                    'subject' => $subjectName,
                    'available_slots' => $slots,
                ];
            }
        }

        return $choices;
    }



    /**
     * Save the generated schedule to the database.
     *
     * @param array $schedules
     * @return void
     */
    protected function saveSchedule(array $schedules): void
    {
        foreach ($schedules as $schedule) {
            SectionSchedule::where([
                'section_id' => $schedule['section_id'],
                'period_id' => $schedule['period_id'],
                'day_of_week' => $schedule['day_of_week']
            ])->update([
                        'subject_id' => $schedule['subject_id'],
                        'teacher_id' => $schedule['teacher_id']
                    ]);
        }
    }

    /**
     * Get statistics about the generated schedule.
     *
     * @return array
     */
    protected function getScheduleStats(): array
    {
        $totalSlots = SectionSchedule::count();
        $assignedSlots = SectionSchedule::whereNotNull('subject_id')->whereNotNull('teacher_id')->count();
        $unassignedSlots = $totalSlots - $assignedSlots;

        return [
            'total_slots' => $totalSlots,
            'assigned_slots' => $assignedSlots,
            'unassigned_slots' => $unassignedSlots,
            'assignment_rate' => $totalSlots > 0 ? round(($assignedSlots / $totalSlots) * 100, 2) : 0,
            'errors' => $this->status['errors']
        ];
    }

    /**
     * Export schedules as JSON.
     *
     * @param Collection $schedules
     * @return array
     */
    protected function exportAsJson(Collection $schedules): array
    {
        $result = [];

        foreach ($schedules as $schedule) {
            $sectionName = $schedule->section->name;
            $dayOfWeek = $schedule->day_of_week;
            $periodName = $schedule->period->name;
            $periodTime = $schedule->period->start_time . ' - ' . $schedule->period->end_time;

            if (!isset($result[$sectionName])) {
                $result[$sectionName] = [];
            }

            if (!isset($result[$sectionName][$dayOfWeek])) {
                $result[$sectionName][$dayOfWeek] = [];
            }

            $result[$sectionName][$dayOfWeek][] = [
                'period' => $periodName,
                'time' => $periodTime,
                'subject' => $schedule->subject->name,
                'teacher' => $schedule->teacher->name
            ];
        }

        return [
            'format' => 'json',
            'data' => $result
        ];
    }

    /**
     * Update the current status.
     *
     * @param string $state
     * @param int $progress
     * @param string $message
     * @return void
     */
    protected function updateStatus(string $state, int $progress, string $message): void
    {
        $this->status['state'] = $state;
        $this->status['progress'] = $progress;
        $this->status['message'] = $message;

        Log::info("Schedule generator: $message", ['state' => $state, 'progress' => $progress]);
    }



}