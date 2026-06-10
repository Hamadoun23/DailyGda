<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Phase extends Model
{
    protected $fillable = [
        'project_id',
        'name',
        'sort_order',
        'hidden_from_partner',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'hidden_from_partner' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function subPhases(): HasMany
    {
        return $this->hasMany(SubPhase::class)->orderBy('sort_order');
    }

    /**
     * @return HasManyThrough<Task, SubPhase>
     */
    public function tasks(): HasManyThrough
    {
        return $this->hasManyThrough(Task::class, SubPhase::class, 'phase_id', 'sub_phase_id', 'id', 'id');
    }

    public function progress(): int
    {
        $tasks = $this->tasks()->with('latestDailyUpdate')->get();
        if ($tasks->isEmpty()) {
            return 0;
        }

        $sum = $tasks->sum(fn (Task $task) => $task->latestDailyUpdate?->progress ?? 0);

        return (int) round($sum / $tasks->count());
    }
}
