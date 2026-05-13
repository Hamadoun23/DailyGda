<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('personal_access_tokens')->delete();
        DB::table('daily_updates')->delete();
        DB::table('photos')->delete();
        DB::table('reports')->delete();
        DB::table('project_user')->delete();
        DB::table('users')->delete();

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->default('admin')->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['chef_chantier', 'ingenieur', 'controle_qualite', 'direction'])
                ->default('chef_chantier')
                ->after('password');
        });
    }
};
