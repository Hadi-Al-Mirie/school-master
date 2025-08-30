<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run()
    {
        $this->call([
            RoleSeeder::class,
            AdminSeeder::class,
            StageSeeder::class,
                // ScheduleSeeder::class,
            SupervisorSeeder::class,
            YearSeeder::class,
            SemesterSeeder::class,
            ClassroomSeeder::class,
            SubjectSeeder::class,
            SectionSeeder::class,
            StudentSeeder::class,
            TeacherSeeder::class,
            EmployeeSeeder::class,
            SectionSubjectSeeder::class,
            QuizSeeder::class,
            ExamGroupSeeder::class,
            AttendanceTypeSeeder::class,
            AttendanceSeeder::class,
            NoteSeeder::class,
            DictationSeeder::class,
            PeriodSeeder::class,
            SectionScheduleSeeder::class,
            EventSeeder::class,
            TeacherPopularitySeeder::class,
            TeacherAvailabilitySeeder::class,
        ]);
    }
}