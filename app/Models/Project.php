<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'name',
        'description',
        'client',
        'start_date',
        'end_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function phases(): HasMany
    {
        return $this->hasMany(Phase::class)->orderBy('sort_order');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Moyenne des derniers progress enregistrés par tâche (sans historique = 0).
     */
    public function overallProgress(): int
    {
        $tasks = Task::query()
            ->forProject($this->id)
            ->with('latestDailyUpdate')
            ->get();

        if ($tasks->isEmpty()) {
            return 0;
        }

        $sum = $tasks->sum(fn (Task $task) => $task->latestDailyUpdate?->progress ?? 0);

        return (int) round($sum / $tasks->count());
    }

    /**
     * @return array<string, int>
     */
    public function progressByPhase(): array
    {
        $out = [];
        foreach ($this->phases()->orderBy('sort_order')->get() as $phase) {
            $out[$phase->name] = $phase->progress();
        }

        return $out;
    }
}
