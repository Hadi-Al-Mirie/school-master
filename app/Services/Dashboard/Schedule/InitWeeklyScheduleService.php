<?php

namespace App\Services\Dashboard\Schedule;

use App\Models\TeacherAvailabilities;
use App\Models\SectionSchedule;
use App\Models\Section;
use App\Models\Period;
use Illuminate\Support\Facades\DB;

class InitWeeklyScheduleService
{
    /**
     * Replace teacher availabilities per (teacher, day) with provided period_ids.
     * Returns a summary array.
     */
    public function syncTeacherAvailabilities(array $items): array
    {
        $summary = [];
        foreach ($items as $row) {
            $teacherId = (int) $row['teacher_id'];
            $day = $row['day_of_week'];
            $periodIds = collect($row['period_ids'])->unique()->values()->all();
            TeacherAvailabilities::where('teacher_id', $teacherId)
                ->where('day_of_week', $day)
                ->delete();
            $bulk = collect($periodIds)->map(fn($pid) => [
                'teacher_id' => $teacherId,
                'day_of_week' => $day,
                'period_id' => (int) $pid,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();
            if (!empty($bulk)) {
                TeacherAvailabilities::insert($bulk);
            }
            $summary[] = [
                'teacher_id' => $teacherId,
                'day_of_week' => $day,
                'count' => count($bulk),
            ];
        }
        return $summary;
    }

    /**
     * Initialize section_schedules for each classroom's sections:
     * For each day: create N empty slots (subject_id/teacher_id = null)
     * using the first N Period IDs (ordered ASC).
     * Existing entries for the targeted sections+days are replaced.
     */
    public function initializeSectionSchedules(array $classroomsConfig): array
    {
        $summary = [];
        $periodIds = Period::orderBy('id')->pluck('id')->all();

        foreach ($classroomsConfig as $cfg) {
            $classroomId = (int) $cfg['classroom_id'];
            /** @var \Illuminate\Support\Collection<int,\App\Models\Section> $sections */
            $sections = Section::where('classroom_id', $classroomId)->pluck('id');
            $ppd = $cfg['periods_per_day'] ?? [];
            $createdRows = 0;
            foreach ($sections as $sectionId) {
                foreach ($ppd as $day => $count) {
                    $count = (int) $count;
                    SectionSchedule::where('section_id', $sectionId)
                        ->where('day_of_week', $day)
                        ->delete();
                    if ($count <= 0)
                        continue;
                    $usePeriods = array_slice($periodIds, 0, $count);
                    $bulk = collect($usePeriods)->map(fn($pid) => [
                        'section_id' => $sectionId,
                        'period_id' => (int) $pid,
                        'day_of_week' => $day,
                        'subject_id' => null,
                        'teacher_id' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->all();
                    if (!empty($bulk)) {
                        SectionSchedule::insert($bulk);
                        $createdRows += count($bulk);
                    }
                }
            }
            $summary[] = [
                'classroom_id' => $classroomId,
                'sections_count' => $sections->count(),
                'rows_created' => $createdRows,
            ];
        }
        return $summary;
    }

    /**
     * Master operation within a single transaction.
     */
    public function initialize(array $teacherAvailabilities, array $classroomsConfig): array
    {
        return DB::transaction(function () use ($teacherAvailabilities, $classroomsConfig) {
            $availSummary = $this->syncTeacherAvailabilities($teacherAvailabilities);
            $schedSummary = $this->initializeSectionSchedules($classroomsConfig);

            return [
                'teacher_availability_summary' => $availSummary,
                'section_schedules_summary' => $schedSummary,
            ];
        });
    }
}
