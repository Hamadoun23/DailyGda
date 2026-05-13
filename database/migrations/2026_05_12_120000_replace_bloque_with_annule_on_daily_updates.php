<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE daily_updates MODIFY status ENUM('non_demarre', 'en_cours', 'termine', 'bloque', 'annule') NOT NULL DEFAULT 'non_demarre'");
        DB::table('daily_updates')->where('status', 'bloque')->update(['status' => 'annule']);
        DB::statement("ALTER TABLE daily_updates MODIFY status ENUM('non_demarre', 'en_cours', 'termine', 'annule') NOT NULL DEFAULT 'non_demarre'");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE daily_updates MODIFY status ENUM('non_demarre', 'en_cours', 'termine', 'bloque', 'annule') NOT NULL DEFAULT 'non_demarre'");
        DB::table('daily_updates')->where('status', 'annule')->update(['status' => 'bloque']);
        DB::statement("ALTER TABLE daily_updates MODIFY status ENUM('non_demarre', 'en_cours', 'termine', 'bloque') NOT NULL DEFAULT 'non_demarre'");
    }
};
