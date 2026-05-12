<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

class PhaseAndTaskSeeder extends Seeder
{
  public function run(): void
  {
    $project = Project::query()->orderBy('id')->firstOrFail();

    $counts = (new DailyConstrExcelImporter)->import($project);

    $this->command?->info(sprintf(
      'Import dailyConstr.xlsx : %d phases, %d sous-phases, %d activités.',
      $counts['phases'],
      $counts['sub_phases'],
      $counts['tasks']
    ));
  }
}
