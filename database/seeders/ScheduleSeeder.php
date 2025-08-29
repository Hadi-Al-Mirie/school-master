<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use App\Models\Stage;
use App\Models\Classroom;
use App\Models\Section;
use App\Models\Period;
use App\Models\User;
use App\Models\Teacher;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SectionSubject;
use Carbon\Carbon;
class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        if (Period::count() < 5) {
            $base = Carbon::createFromTime(8, 0, 0);
            for ($i = 1; $i <= 5; $i++) {
                $start = (clone $base)->addHours($i - 1);
                $end = (clone $start)->addHour();
                Period::firstOrCreate(['id' => $i], ['name' => 'P' . $i, 'order' => $i, 'start_time' => $start->toTimeString(), 'end_time' => $end->toTimeString(), 'created_at' => now(), 'updated_at' => now(),]);
            }
        }
        $stage = Stage::firstOrCreate(['name' => 'Primary']);
        $classroom = Classroom::firstOrCreate(
            ['name' => 'C1', 'stage_id' => $stage->id],
            ['created_at' => now(), 'updated_at' => now()]
        );
        $section = Section::firstOrCreate(
            ['name' => 'A', 'classroom_id' => $classroom->id],
            ['created_at' => now(), 'updated_at' => now()]
        );
        $u1 = User::firstOrCreate(
            ['email' => 't1@example.com'],
            [
                'first_name' => 'Alice',
                'last_name' => 'Teacher',
                'role_id' => 2,
                'password' => Hash::make('password'),
                'remember_token' => Str::random(10),
            ]
        );
        $t1 = Teacher::firstOrCreate(['user_id' => $u1->id, 'phone' => '0987654321']);
        $u2 = User::firstOrCreate(
            ['email' => 't2@example.com'],
            [
                'first_name' => 'Bob',
                'last_name' => 'Teacher',
                'role_id' => 2,
                'password' => Hash::make('password'),
                'remember_token' => Str::random(10),
            ]
        );
        $t2 = Teacher::firstOrCreate(['user_id' => $u2->id, 'phone' => '0987654321']);
        $math = Subject::firstOrCreate(['name' => 'Math', 'classroom_id' => $classroom->id], ['amount' => 3]);
        $arabic = Subject::firstOrCreate(['name' => 'Arabic', 'classroom_id' => $classroom->id], ['amount' => 2]);
        SectionSubject::firstOrCreate([
            'section_id' => $section->id,
            'subject_id' => $math->id,
            'teacher_id' => $t1->id,
        ]);
        SectionSubject::firstOrCreate([
            'section_id' => $section->id,
            'subject_id' => $arabic->id,
            'teacher_id' => $t2->id,
        ]);
    }
}
/*
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

use App\Models\Stage;
use App\Models\Classroom;
use App\Models\Section;
use App\Models\Period;
use App\Models\User;
use App\Models\Teacher;
use App\Models\Subject;
use App\Models\SectionSubject;

class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Periods: 4 per day (08:00–12:00) ----
        if (Period::count() < 4) {
            $base = Carbon::createFromTime(8, 0, 0);
            for ($i = 1; $i <= 4; $i++) {
                $start = (clone $base)->addHours($i - 1);
                $end = (clone $start)->addHour();
                Period::firstOrCreate(['id' => $i], [
                    'name' => 'P' . $i,
                    'order' => $i,
                    'start_time' => $start->toTimeString(),
                    'end_time' => $end->toTimeString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // ---- 2 stages × 2 classrooms each ----
        $stageNames = ['Stage A', 'Stage B'];
        $sectionNames = ['A', 'B'];
        $subjectsDef = [
            ['name' => 'Math', 'amount' => 3],
            ['name' => 'Science', 'amount' => 3],
            ['name' => 'Arabic', 'amount' => 2],
        ];
        // Per section: 3+3+2 = 8 weekly lessons. With our init (2 days × 4 periods) -> 8 slots/section -> tight fit.

        foreach ($stageNames as $si => $sname) {
            $stage = Stage::firstOrCreate(['name' => $sname]);

            for ($cj = 1; $cj <= 2; $cj++) {
                $classroom = Classroom::firstOrCreate(
                    ['name' => "S" . ($si + 1) . "-C{$cj}", 'stage_id' => $stage->id],
                    ['created_at' => now(), 'updated_at' => now()]
                );

                // Sections A & B
                $sections = [];
                foreach ($sectionNames as $secName) {
                    $sections[] = Section::firstOrCreate(
                        ['name' => $secName, 'classroom_id' => $classroom->id],
                        ['created_at' => now(), 'updated_at' => now()]
                    );
                }

                // 3 subjects per classroom; 1 teacher per subject; same teacher teaches both sections
                foreach ($subjectsDef as $k => $def) {
                    $subject = Subject::firstOrCreate(
                        ['name' => $def['name'], 'classroom_id' => $classroom->id],
                        ['amount' => $def['amount']]
                    );

                    $email = "t_s" . ($si + 1) . "_c{$cj}_" . strtolower($def['name']) . "@example.com";
                    $user = User::firstOrCreate(
                        ['email' => $email],
                        [
                            'first_name' => $def['name'] . 'T',
                            'last_name' => "S" . ($si + 1) . "C{$cj}",
                            'role_id' => 2,
                            'password' => Hash::make('password'),
                            'remember_token' => Str::random(10),
                        ]
                    );
                    $teacher = Teacher::firstOrCreate(['user_id' => $user->id], ['phone' => '0987654321']);

                    // Assign subject to both sections with this teacher
                    foreach ($sections as $sec) {
                        SectionSubject::firstOrCreate([
                            'section_id' => $sec->id,
                            'subject_id' => $subject->id,
                            'teacher_id' => $teacher->id,
                        ]);
                    }
                }
            }
        }
    }
}
*/
