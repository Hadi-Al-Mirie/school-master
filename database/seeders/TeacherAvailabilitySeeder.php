<?php

namespace Database\Seeders;

use App\Models\TeacherAvailabilities;
use Illuminate\Database\Seeder;
use App\Models\Teacher;
use App\Models\Period;

class TeacherAvailabilitySeeder extends Seeder
{
    public function run()
    {
        $days = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday'];
        $teachers = Teacher::all();
        $periods = Period::all();

        foreach ($teachers as $teacher) {
            foreach ($days as $day) {
                foreach ($periods as $period) {
                    // randomly mark as available half the time
                    if (rand(0, 1)) {
                        TeacherAvailabilities::create([
                            'teacher_id' => $teacher->id,
                            'period_id' => $period->id,
                            'day_of_week' => $day,
                        ]);
                    }
                }
            }
        }
    }
}
