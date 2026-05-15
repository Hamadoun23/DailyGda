<?php

namespace App\Support;

final class GdaStatus
{
    /** @var array<string, string> */
    public const LABELS_FR = [
        'non_demarre' => 'Non démarré',
        'en_cours' => 'En cours',
        'termine' => 'Terminé',
        'annule' => 'Annulée',
    ];

    /** @var list<string> */
    public const SLUGS = ['non_demarre', 'en_cours', 'termine', 'annule'];

    public static function labelFr(string $status): string
    {
        return self::LABELS_FR[$status] ?? $status;
    }

    public static function label(string $status, string $locale = 'fr'): string
    {
        if ($locale === 'en') {
            return match ($status) {
                'non_demarre' => 'Not started',
                'en_cours' => 'In progress',
                'termine' => 'Completed',
                'annule' => 'Cancelled',
                default => $status,
            };
        }

        return self::labelFr($status);
    }
}
