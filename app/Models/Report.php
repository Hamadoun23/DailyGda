<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $fillable = [
        'project_id',
        'user_id',
        'report_date',
        'temperature',
        'weather',
        'page_number',
        'overall_progress',
        'notes',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'temperature' => 'decimal:1',
            'overall_progress' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
