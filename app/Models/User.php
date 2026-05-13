<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if (is_string($user->username) && $user->username !== '') {
                $user->username = Str::lower(trim($user->username));
            }
        });
    }

    public const ROLE_ADMIN = 'admin';

    public const ROLE_PARTNER = 'partner';

    protected $fillable = [
        'username',
        'name',
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

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isPartner(): bool
    {
        return $this->role === self::ROLE_PARTNER;
    }

    /**
     * Voir tous les projets (sans filtre pivot) et accès gestion.
     */
    public function canViewAllProjects(): bool
    {
        return $this->isAdmin();
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
