<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskProgressNote extends Model
{
    protected $fillable = [
        'task_id',
        'user_id',
        'daily_update_id',
        'progress',
        'previous_progress',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'progress' => 'integer',
            'previous_progress' => 'integer',
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

    public function dailyUpdate(): BelongsTo
    {
        return $this->belongsTo(DailyUpdate::class);
    }
}
