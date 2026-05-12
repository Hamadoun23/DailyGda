<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('report_date');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->enum('status', ['non_demarre', 'en_cours', 'termine', 'annule'])->default('non_demarre');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['task_id', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_updates');
    }
};
