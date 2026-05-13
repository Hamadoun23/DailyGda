<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ProjectSeeder::class,
            PhaseAndTaskSeeder::class,
        ]);

        $project = Project::query()->firstOrFail();
        $project->users()->sync(User::query()->pluck('id')->all());
    }
}
