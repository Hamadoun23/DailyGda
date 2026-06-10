<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Task extends Model
{
    protected $fillable = [
        'sub_phase_id',
        'activity',
        'start_day',
        'duration_days',
        'sort_order',
        'hidden_from_partner',
    ];

    protected function casts(): array
    {
        return [
            'start_day' => 'integer',
            'duration_days' => 'integer',
            'sort_order' => 'integer',
            'hidden_from_partner' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public function scopeForProject(Builder $query, int $projectId): Builder
    {
        return $query->whereHas('subPhase.phase', fn ($q) => $q->where('project_id', $projectId));
    }

    public function subPhase(): BelongsTo
    {
        return $this->belongsTo(SubPhase::class);
    }

    public function dailyUpdates(): HasMany
    {
        return $this->hasMany(DailyUpdate::class);
    }

    public function progressNotes(): HasMany
    {
        return $this->hasMany(TaskProgressNote::class)->orderByDesc('created_at');
    }

    public function latestDailyUpdate(): HasOne
    {
        return $this->hasOne(DailyUpdate::class)->latestOfMany(['report_date', 'id']);
    }

    public function latestProgress(): ?DailyUpdate
    {
        /** @var DailyUpdate|null */
        return $this->latestDailyUpdate()->first();
    }

    public function progressOnDate(string $date): ?DailyUpdate
    {
        return $this->dailyUpdates()
            ->whereDate('report_date', $date)
            ->first();
    }
}
