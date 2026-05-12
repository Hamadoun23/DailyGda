<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phase_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('sub_phase_id')->nullable()->after('id')->constrained('sub_phases')->cascadeOnDelete();
        });

        $tasks = DB::table('tasks')->orderBy('id')->get();
        $subPhaseIds = [];

        foreach ($tasks as $task) {
            $key = $task->phase_id.'|'.$task->subphase;
            if (! isset($subPhaseIds[$key])) {
                $subPhaseIds[$key] = DB::table('sub_phases')->insertGetId([
                    'phase_id' => $task->phase_id,
                    'name' => $task->subphase,
                    'sort_order' => (int) $task->sort_order,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('tasks')->where('id', $task->id)->update(['sub_phase_id' => $subPhaseIds[$key]]);
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['phase_id']);
            $table->dropColumn(['phase_id', 'subphase']);
        });
    }

    public function down(): void
    {
        throw new \RuntimeException('Migration irreversible : restaurer une sauvegarde si nécessaire.');
    }
};
