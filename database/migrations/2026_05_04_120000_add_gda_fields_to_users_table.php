<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['chef_chantier', 'ingenieur', 'controle_qualite', 'direction'])
                ->default('chef_chantier')
                ->after('password');
            $table->string('avatar_initials', 3)->nullable()->after('role');
            $table->boolean('is_active')->default(true)->after('avatar_initials');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'avatar_initials', 'is_active']);
        });
    }
};
