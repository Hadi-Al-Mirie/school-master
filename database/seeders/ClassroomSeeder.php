<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Classroom;
use App\Models\Stage;

class ClassroomSeeder extends Seeder
{
    public function run()
    {

        $stages = Stage::all();
        $supervisorIds = [1, 2];

        foreach ($stages as $stage) {
            for ($i = 1; $i <= 2; $i++) {
                Classroom::create([
                    'name' => "{$stage->name} Room " . ($i + 1),
                    'stage_id' => $stage->id,
                ]);
            }
        }
    }
}
