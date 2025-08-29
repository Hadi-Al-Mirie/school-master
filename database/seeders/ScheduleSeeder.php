<?php

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
        // ---------- Periods: 8 per day ----------
        if (Period::count() < 8) {
            $base = Carbon::createFromTime(8, 0, 0); // 08:00
            for ($i = 1; $i <= 8; $i++) {
                $start = (clone $base)->addHours($i - 1); // 08:00, 09:00, ...
                $end = (clone $start)->addHour();       // +1h
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

        // ---------- 3 stages, 5 classrooms each ----------
        $stageNames = ['Stage 1', 'Stage 2', 'Stage 3'];
        $sectionNames = ['A', 'B', 'C'];

        foreach ($stageNames as $si => $sname) {
            $stage = Stage::firstOrCreate(['name' => $sname]);

            for ($cj = 1; $cj <= 5; $cj++) {
                $classroom = Classroom::firstOrCreate(
                    ['name' => "S" . ($si + 1) . "-C" . $cj, 'stage_id' => $stage->id],
                    ['created_at' => now(), 'updated_at' => now()]
                );

                // 3 sections: A, B, C
                $sections = [];
                foreach ($sectionNames as $secName) {
                    $sections[] = Section::firstOrCreate(
                        ['name' => $secName, 'classroom_id' => $classroom->id],
                        ['created_at' => now(), 'updated_at' => now()]
                    );
                }

                // 8 subjects per classroom (amount 2..5)
                for ($k = 1; $k <= 8; $k++) {
                    $amount = rand(2, 5); // weekly periods per section
                    $subjName = "Sub-S" . ($si + 1) . "-C{$cj}-#{$k}";
                    $subject = Subject::firstOrCreate(
                        ['name' => $subjName, 'classroom_id' => $classroom->id],
                        ['amount' => $amount]
                    );

                    // 1 teacher per (classroom, subject)
                    $email = "t_s" . ($si + 1) . "_c{$cj}_{$k}@example.com";
                    $user = User::firstOrCreate(
                        ['email' => $email],
                        [
                            'first_name' => "T" . ($si + 1) . $cj . $k,
                            'last_name' => 'Teacher',
                            'role_id' => 2,
                            'password' => Hash::make('password'),
                            'remember_token' => Str::random(10),
                        ]
                    );
                    $teacher = Teacher::firstOrCreate(
                        ['user_id' => $user->id],
                        ['phone' => '0987654321']
                    );

                    // The same teacher teaches this subject to all 3 sections
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