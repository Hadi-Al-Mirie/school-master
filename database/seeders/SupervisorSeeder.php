<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Supervisor;
use Illuminate\Support\Facades\Hash;
use App\Models\Role;
class SupervisorSeeder extends Seeder
{
    public function run()
    {
        // Example: create 2 supervisors
        for ($i = 1; $i <= 1; $i++) {
            $user = User::create([
                'first_name' => "SupervisorFirst{$i}",
                'last_name' => "SupervisorLast{$i}",
                'email' => "supervisor{$i}@example.com",
                'password' => Hash::make('password'),
                'role_id' => Role::where('name', 'supervisor')->first()->id,
            ]);

            Supervisor::create([
                'user_id' => $user->id,
                'phone' => "013000000{$i}",
                'stage_id' => $i,
            ]);
        }
    }
}
