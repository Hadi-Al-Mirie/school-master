<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Note;
use App\Models\User;
use App\Models\Student;
use App\Models\Semester;
use Nette\Utils\Random;
use PhpParser\Node\Scalar\Float_;

class NoteSeeder extends Seeder
{
    public function run()
    {


        $users = User::all()->pluck('id')->toArray();
        $students = Student::all()->pluck('id')->toArray();
        $semesters = Semester::all()->pluck('id')->toArray();

        // create 20 notes
        for ($i = 1; $i <= 20; $i++) {
            Note::create([
                'by_id' => $users[array_rand($users)],
                'student_id' => $students[array_rand($students)],
                'semester_id' => $semesters[array_rand($semesters)],
                'reason' => "Auto-generated note #{$i}",
                'type' => (rand(0, 1) ? 'positive' : 'negative'),
                'status' => 'approved',
                'value' => random_int(-10, 10) + (mt_rand() / mt_getrandmax()),
            ]);
        }
    }
}
