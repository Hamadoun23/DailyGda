<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubPhase extends Model
{
    protected $fillable = [
        'phase_id',
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

    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('sort_order');
    }
}
