<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 64)->nullable()->after('id');
        });

        foreach (DB::table('users')->orderBy('id')->get() as $row) {
            $base = Str::slug($row->name) ?: 'user';
            $username = strtolower($base);
            $suffix = 1;
            while (DB::table('users')->where('username', $username)->where('id', '!=', $row->id)->exists()) {
                $username = strtolower($base).($suffix++);
            }
            DB::table('users')->where('id', $row->id)->update(['username' => $username]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 64)->nullable(false)->change();
            $table->unique('username');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NULL');
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->string('email')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->unique('email');
        });
    }
};
