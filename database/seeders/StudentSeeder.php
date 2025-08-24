<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Student;
use Illuminate\Support\Facades\Hash;
use App\Models\Role;
class StudentSeeder extends Seeder
{
    public function run()
    {
        // Example: create 10 students
        for ($i = 1; $i <= 10; $i++) {
            $user = User::create([
                'first_name' => "StudentFirst{$i}",
                'last_name' => "StudentLast{$i}",
                'email' => "student{$i}@example.com",
                'password' => Hash::make('password'),
                'role_id' => Role::where('name', 'student')->first()->id,
            ]);

            Student::create([
                'user_id' => $user->id,
                'father_name' => "Father{$i}",
                'mother_name' => "Mother{$i}",
                'father_number' => "010000000{$i}",
                'mother_number' => "010000000{$i}",
                'cashed_points' => 0.00,
                'gender' => $i % 2 ? 'male' : 'female',
                'location' => "City {$i}",
                'birth_day' => now()->subYears(10 + $i),
                'diseases' => null,
                'special_notes' => null,
                'stage_id' => 1,
                'classroom_id' => 1,
                'section_id' => 1,
            ]);
        }
    }
}
