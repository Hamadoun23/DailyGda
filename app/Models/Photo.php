<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Photo extends Model
{
    protected $fillable = [
        'project_id',
        'user_id',
        'category',
        'path',
        'original_name',
        'caption',
        'taken_at',
        'file_size',
    ];

    protected $appends = ['url'];

    protected function casts(): array
    {
        return [
            'taken_at' => 'date',
            'file_size' => 'integer',
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

    public function getUrlAttribute(): string
    {
        if (! $this->id) {
            return '';
        }

        // Chemin relatif : le front le résout via GDA_API_BASE (fiable en prod / sous-dossier).
        return '/api/photos/'.$this->id.'/file';
    }
}
