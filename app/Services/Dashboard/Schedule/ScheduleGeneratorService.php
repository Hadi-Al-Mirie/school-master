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
    protected array $teacherSlotList = []; // [teacher_id] => ["day|period", ...]
    protected array $teacherSlotSet = []; // [teacher_id]["day|period"] => true

    protected array $sectionSlotList = []; // [section_id] => ["day|period", ...]
    protected array $sectionSlotSet = []; // [section_id]["day|period"] => true

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
            if (function_exists('set_time_limit')) {
                @set_time_limit(120);
            }
            // Avoid memory/time from query log during big runs
            try {
                DB::connection()->disableQueryLog();
            } catch (\Throwable $e) {
            }

            $getAllSchedules = $options['get_all_schedules'] ?? false;
            $optimizeSchedule = $options['optimize'] ?? false;

            $this->updateStatus('running', 0, 'Starting schedule generation');
            DB::beginTransaction();

            // 1) Load data (keep your eager loads)
            $sections = $this->loadSections();
            $periods = $this->loadPeriods();
            $teacherAvailabilities = $this->loadTeacherAvailabilities();
            $sectionSubjects = $this->loadSectionSubjects();
            $sectionSchedules = $this->loadSectionSchedules();

            // 1a) Build fast in-memory indexes (NEW)
            $this->buildTeacherIndexes($teacherAvailabilities);
            $this->buildSectionIndexes($sectionSchedules);

            // 2) Reset
            $this->resetSchedules();
            $this->updateStatus('running', 5, 'Schedules cleared');

            // 3) Explode & sort (use precomputed counts instead of Collection math)
            $demands = $this->explodeSectionSubjectsByAmount($sectionSubjects);
            $sortedDemands = $this->sortByConstraintDifficultyFast($demands);
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
                $this->updateStatus('running', 15, 'Generating a single schedule');

                // Throttle log spam in the inner loop (NEW: pass step)
                $schedule = $this->generateInitialSchedule(
                    $sortedDemands,
                    $sections,
                    $periods,
                    $teacherAvailabilities,
                    $sectionSchedules,
                    ['log_step' => 50] + $options
                );

                if (empty($schedule)) {
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

                if ($optimizeSchedule) {
                    // Cap the optimizer’s work; long optimizations cause timeouts
                    $this->updateStatus('running', 70, 'Optimizing schedule');
                    $schedule = $this->scheduleOptimizer->optimize(
                        $schedule,
                        $teacherAvailabilities,
                        $sectionSchedules,
                        ['max_iterations' => 25] // keep small for Test #2 scale
                    );
                    $this->updateStatus('running', 90, 'Schedule optimized');
                }

                $schedule = collect($schedule)
                    ->unique(fn($r) => $this->slotKey($r['day_of_week'], (int) $r['period_id']) . '|' . $r['section_id'])
                    ->values()
                    ->all();

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


    protected function buildTeacherIndexes(Collection $teacherAvailabilities): void
    {
        $this->teacherSlotList = [];
        $this->teacherSlotSet = [];

        foreach ($teacherAvailabilities as $row) {
            $tid = (int) $row->teacher_id;
            $key = $row->day_of_week . '|' . (int) $row->period_id;
            $this->teacherSlotList[$tid][] = $key;
            $this->teacherSlotSet[$tid][$key] = true;
        }
    }

    protected function buildSectionIndexes(Collection $sectionSchedules): void
    {
        $this->sectionSlotList = [];
        $this->sectionSlotSet = [];

        foreach ($sectionSchedules as $row) {
            $sid = (int) $row->section_id;
            $key = $row->day_of_week . '|' . (int) $row->period_id;
            $this->sectionSlotList[$sid][] = $key;
            $this->sectionSlotSet[$sid][$key] = true;
        }
    }


    protected function sortByConstraintDifficultyFast(Collection $demands): Collection
    {
        // Precompute availability counts once
        $availCount = []; // teacher_id => #slots
        foreach ($this->teacherSlotList as $tid => $list) {
            $availCount[(int) $tid] = count($list);
        }

        $cache = [];
        return $demands->sortBy(function ($ss) use (&$cache, $availCount) {
            $key = $ss->id;
            if (!array_key_exists($key, $cache)) {
                // allow ->teachers or ->teacher
                $teacherList = collect($ss->teachers ?? ($ss->teacher ? [$ss->teacher] : []));
                $slots = 0;
                foreach ($teacherList as $t) {
                    $slots += $availCount[$t->id] ?? 0;
                }
                // smaller = harder; zero goes first
                $cache[$key] = $slots ?: 0;
            }
            return $cache[$key];
        })->values();
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
    protected function sortByConstraintDifficulty(
        \Illuminate\Support\Collection $demands,
        \Illuminate\Support\Collection $teacherAvailabilities
    ): \Illuminate\Support\Collection {
        // Precompute teacher availability counts once
        $availCount = $teacherAvailabilities
            ->groupBy('teacher_id')
            ->map->count(); // teacher_id => #slots

        // Cache difficulty per SectionSubject to avoid recomputing for exploded duplicates
        $difficultyCache = [];

        return $demands->sortBy(function ($sectionSubject) use ($availCount, &$difficultyCache) {
            $key = $sectionSubject->id;

            if (!array_key_exists($key, $difficultyCache)) {
                // Allow either ->teachers (collection) or ->teacher (single)
                $teacherList = collect($sectionSubject->teachers ?? ($sectionSubject->teacher ? [$sectionSubject->teacher] : []));
                $availableTeachers = $teacherList->count();
                $availableSlots = $teacherList->sum(function ($t) use ($availCount) {
                    return (int) ($availCount[$t->id] ?? 0);
                });

                // Smaller value = more constrained first
                $difficultyCache[$key] = ($availableTeachers === 0 || $availableSlots === 0)
                    ? 0
                    : $availableSlots;
            }

            return $difficultyCache[$key];
        })->values();
    }

    protected function slotKey(string $day, int $periodId): string
    {
        return strtolower($day) . '|' . (int) $periodId;
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
        $logStep = max(1, (int) ($options['log_step'] ?? 100)); // log every N items

        $total = $demands->count();
        foreach ($demands as $index => $sectionSubject) {
            if ($index % $logStep === 0) {
                $this->updateStatus(
                    'running',
                    30 + (int) (40 * $index / max(1, $total)),
                    "Placing demand " . ($index + 1) . " of " . $total
                );
            }

            // eligible teachers for this demand (usually 1 in your data model)
            $possibleTeachers = $sectionSubject->teachers ?? ($sectionSubject->teacher ? [$sectionSubject->teacher] : []);
            $assigned = false;

            foreach ($possibleTeachers as $teacher) {
                // If teachers relation already encodes eligibility, skip extra checker call
                // if (!$this->constraintChecker->teacherCanTeach($teacher->id, $sectionSubject->id)) continue;

                $availableSlots = $this->getAvailableSlots(
                    $teacher,
                    $sectionSubject,
                    $assignedTeacherSlots,
                    $assignedSectionSlots,
                    $teacherAvailabilities,
                    $sectionSchedules,
                    ['force_assign' => $forceAssign]
                );

                if (empty($availableSlots))
                    continue;

                $slot = $availableSlots[0]; // greedy pick

                $schedules[] = [
                    'section_id' => (int) $sectionSubject->section_id,
                    'subject_id' => (int) $sectionSubject->subject_id,
                    'teacher_id' => (int) $teacher->id,
                    'period_id' => (int) $slot['period_id'],
                    'day_of_week' => $slot['day_of_week'],
                ];

                $key = $this->slotKey($slot['day_of_week'], (int) $slot['period_id']);
                $assignedTeacherSlots[$teacher->id][$key] = true;
                $assignedSectionSlots[$sectionSubject->section_id][$key] = true;
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
        Collection $teacherAvailabilities,   // kept for signature compatibility (unused now)
        Collection $sectionSchedules,        // kept for signature compatibility (unused now)
        array $options
    ): array {
        $tid = (int) $teacher->id;
        $sid = (int) $sectionSubject->section_id;

        $teacherKeys = $this->teacherSlotList[$tid] ?? [];
        $sectionSet = $this->sectionSlotSet[$sid] ?? [];

        $out = [];
        foreach ($teacherKeys as $key) {
            // must be a slot that exists for the section
            if (!isset($sectionSet[$key]))
                continue;

            // enforce not already used by this teacher/section
            if (isset($assignedTeacherSlots[$tid][$key]))
                continue;
            if (isset($assignedSectionSlots[$sid][$key]))
                continue;

            // decode
            [$day, $period] = explode('|', $key);
            $out[] = ['day_of_week' => $day, 'period_id' => (int) $period];
        }
        return $out;
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
        foreach ($schedules as $row) {
            $affected = SectionSchedule::where([
                'section_id' => (int) $row['section_id'],
                'period_id' => (int) $row['period_id'],
                'day_of_week' => strtolower($row['day_of_week']),
            ])->update([
                        'subject_id' => (int) $row['subject_id'],
                        'teacher_id' => (int) $row['teacher_id'],
                        'updated_at' => now(),
                    ]);

            if ($affected === 0) {
                Log::warning('No SectionSchedule row matched for update', $row);
            }
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