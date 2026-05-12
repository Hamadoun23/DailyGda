<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_CHEF = 'chef_chantier';

    public const ROLE_INGENIEUR = 'ingenieur';

    public const ROLE_CONTROLE = 'controle_qualite';

    public const ROLE_DIRECTION = 'direction';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'avatar_initials',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function dailyUpdates(): HasMany
    {
        return $this->hasMany(DailyUpdate::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withTimestamps();
    }

    public function isDirection(): bool
    {
        return $this->role === self::ROLE_DIRECTION;
    }

    /**
     * Moyenne des pourcentages saisis par cet utilisateur pour une date donnée (updates du jour).
     */
    public function getCurrentProgressAttribute(): int
    {
        $date = now()->toDateString();
        $avg = $this->dailyUpdates()
            ->whereDate('report_date', $date)
            ->avg('progress');

        return (int) round((float) ($avg ?? 0));
    }
}
