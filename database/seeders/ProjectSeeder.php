<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        (new DailyConstrExcelImporter)->seedProject(base_path('dailyConstr.xlsx'));
    }
}
