<?php

namespace App\Services\Dashboard\Schedule;

use App\Models\SectionSchedule;
use Illuminate\Support\Collection;
use App\Models\SectionSubject;
class ConstraintChecker
{
    /**
     * Check if a teacher is already assigned to a specific time slot.
     *
     * @param int $teacherId
     * @param string $dayOfWeek
     * @param int $periodId
     * @return bool
     */
    public function hasTeacherConflict(int $teacherId, string $dayOfWeek, int $periodId): bool
    {
        return SectionSchedule::where('teacher_id', $teacherId)
            ->where('day_of_week', $dayOfWeek)
            ->where('period_id', $periodId)
            ->whereNotNull('subject_id')
            ->exists();
    }

    /**
     * Check if a section already has a subject assigned to a specific time slot.
     *
     * @param int $sectionId
     * @param string $dayOfWeek
     * @param int $periodId
     * @return bool
     */
    public function hasSectionConflict(int $sectionId, string $dayOfWeek, int $periodId): bool
    {
        return SectionSchedule::where('section_id', $sectionId)
            ->where('day_of_week', $dayOfWeek)
            ->where('period_id', $periodId)
            ->whereNotNull('subject_id')
            ->exists();
    }

    /**
     * Check if a teacher is available at a specific time slot.
     *
     * @param int $teacherId
     * @param string $dayOfWeek
     * @param int $periodId
     * @param Collection $teacherAvailabilities
     * @return bool
     */
    public function isTeacherAvailable(int $teacherId, string $dayOfWeek, int $periodId, Collection $teacherAvailabilities): bool
    {
        return $teacherAvailabilities
            ->where('teacher_id', $teacherId)
            ->where('day_of_week', $dayOfWeek)
            ->where('period_id', $periodId)
            ->isNotEmpty();
    }

    /**
     * Check if a teacher can teach a specific section subject (qualification check).
     *
     * @param int $teacherId
     * @param int $sectionSubjectId
     * @return bool
     */
    public function teacherCanTeach(int $teacherId, int $sectionSubjectId): bool
    {
        return SectionSubject::where('teacher_id', $teacherId)
            ->where('section_subject_id', $sectionSubjectId)
            ->exists();
    }

    /**
     * Validate a complete schedule against all constraints.
     *
     * @param array $schedules
     * @param Collection $teacherAvailabilities
     * @param Collection $sectionSubjects
     * @return array
     */
    public function validateSchedule(array $schedules, Collection $teacherAvailabilities, Collection $sectionSubjects): array
    {
        $errors = [];
        $teacherAssignments = [];
        $sectionAssignments = [];

        foreach ($schedules as $index => $schedule) {
            $teacherId = $schedule['teacher_id'];
            $sectionId = $schedule['section_id'];
            $sectionSubjectId = $this->findSectionSubjectId($sectionId, $schedule['subject_id'], $sectionSubjects);
            $dayOfWeek = $schedule['day_of_week'];
            $periodId = $schedule['period_id'];
            $slotKey = $dayOfWeek . '_' . $periodId;

            // Check teacher availability
            if (!$this->isTeacherAvailable($teacherId, $dayOfWeek, $periodId, $teacherAvailabilities)) {
                $errors[] = "Teacher ID $teacherId is not available on $dayOfWeek at period $periodId";
            }

            // Check teacher qualification
            if ($sectionSubjectId && !$this->teacherCanTeach($teacherId, $sectionSubjectId)) {
                $errors[] = "Teacher ID $teacherId is not qualified to teach subject ID {$schedule['subject_id']} for section ID $sectionId";
            }

            // Check teacher conflict
            if (isset($teacherAssignments[$teacherId][$slotKey])) {
                $errors[] = "Teacher ID $teacherId has a conflict on $dayOfWeek at period $periodId";
            } else {
                $teacherAssignments[$teacherId][$slotKey] = true;
            }

            // Check section conflict
            if (isset($sectionAssignments[$sectionId][$slotKey])) {
                $errors[] = "Section ID $sectionId has a conflict on $dayOfWeek at period $periodId";
            } else {
                $sectionAssignments[$sectionId][$slotKey] = true;
            }
        }

        return $errors;
    }

    /**
     * Find the section subject ID for a given section and subject.
     *
     * @param int $sectionId
     * @param int $subjectId
     * @param Collection $sectionSubjects
     * @return int|null
     */
    protected function findSectionSubjectId(int $sectionId, int $subjectId, Collection $sectionSubjects): ?int
    {
        $sectionSubject = $sectionSubjects
            ->where('section_id', $sectionId)
            ->where('subject_id', $subjectId)
            ->first();

        return $sectionSubject ? $sectionSubject->id : null;
    }
}