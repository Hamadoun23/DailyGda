<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyUpdate extends Model
{
    protected $fillable = [
        'task_id',
        'user_id',
        'report_date',
        'progress',
        'status',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'progress' => 'integer',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function statusFromProgress(int $progress): string
    {
        if ($progress >= 100) {
            return 'termine';
        }
        if ($progress > 0) {
            return 'en_cours';
        }

        return 'non_demarre';
    }

    /**
     * @param  Builder<DailyUpdate>  $query
     * @return Builder<DailyUpdate>
     */
    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('report_date', $date);
    }

    /**
     * @param  Builder<DailyUpdate>  $query
     * @return Builder<DailyUpdate>
     */
    public function scopeForProject(Builder $query, int $projectId): Builder
    {
        return $query->whereHas('task.subPhase.phase', fn ($q) => $q->where('project_id', $projectId));
    }
}
