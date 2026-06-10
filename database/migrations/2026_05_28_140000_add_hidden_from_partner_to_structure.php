<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phases', function (Blueprint $table) {
            $table->boolean('hidden_from_partner')->default(false)->after('sort_order');
        });

        Schema::table('sub_phases', function (Blueprint $table) {
            $table->boolean('hidden_from_partner')->default(false)->after('sort_order');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('hidden_from_partner')->default(false)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('phases', function (Blueprint $table) {
            $table->dropColumn('hidden_from_partner');
        });

        Schema::table('sub_phases', function (Blueprint $table) {
            $table->dropColumn('hidden_from_partner');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('hidden_from_partner');
        });
    }
};
