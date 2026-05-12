<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class ReportPresentation
{
  /** @var array<string, array<string, string>>|null */
  private static ?array $structureMaps = null;

  public function __construct(private readonly string $locale = 'fr') {}

  public static function forLocale(string $locale): self
  {
    return new self($locale === 'en' ? 'en' : 'fr');
  }

  public function locale(): string
  {
    return $this->locale;
  }

  /**
   * @return array<string, mixed>
   */
  public function copy(): array
  {
    if ($this->locale === 'en') {
      return [
        'progress_title' => 'PROGRESS',
        'report_title' => 'DAILY SITE PROGRESS REPORT',
        'overall_progress' => 'Overall Project Progress',
        'date' => 'Date',
        'temperature' => 'Temperature',
        'weather' => 'Weather',
        'page' => 'Page',
        'photos_prefix' => 'Photos',
        'photos_category' => 'During works',
        'cols' => [
          'phase' => 'Phase',
          'subphase' => 'Sub-phase',
          'activity' => 'Activity',
          'start' => 'Start',
          'progress' => 'Progress',
          'duration' => 'Duration',
          'status' => 'Status',
        ],
      ];
    }

    return [
      'progress_title' => 'Avancement',
      'report_title' => 'Rapport journalier de chantier',
      'overall_progress' => 'Avancement global du projet',
      'date' => 'Date',
      'temperature' => 'Température',
      'weather' => 'Météo',
      'page' => 'Page',
      'photos_prefix' => 'Photos',
      'photos_category' => 'Pendant travaux',
      'cols' => [
        'phase' => 'Phase',
        'subphase' => 'Sous-phase',
        'activity' => 'Activité',
        'start' => 'Début',
        'progress' => 'Avancement',
        'duration' => 'Durée',
        'status' => 'Statut',
      ],
    ];
  }

  public function translate(?string $value, string $kind): string
  {
    $value = $value ?? '';
    if ($this->locale !== 'en' || $value === '') {
      return $value;
    }

    return self::structureMaps()[$kind][$value] ?? $value;
  }

  public function statusLabel(string $status, string $frenchLabel): string
  {
    if ($this->locale !== 'en') {
      return $frenchLabel;
    }

    return match ($status) {
      'termine' => 'Completed',
      'en_cours' => 'In progress',
      'annule' => 'Cancelled',
      default => 'Not started',
    };
  }

  public function durationLabel(int $days): string
  {
    return $this->locale === 'en' ? $days.' d' : $days.'j';
  }

  public function formatTaskStartDate(?CarbonInterface $projectStartDate, int $startDay): string
  {
    $base = $projectStartDate
      ? Carbon::parse($projectStartDate)->startOfDay()
      : Carbon::today();

    return $base->copy()->addDays(max(0, $startDay - 1))->format('d/m/Y');
  }

  public function photoCategoryLabel(string $category): string
  {
    $labels = [
      'avant' => ['fr' => 'Avant travaux', 'en' => 'Before works'],
      'pendant' => ['fr' => 'Pendant travaux', 'en' => 'During works'],
      'apres' => ['fr' => 'Après travaux', 'en' => 'After works'],
      'securite' => ['fr' => 'Sécurité', 'en' => 'Safety'],
      'qualite' => ['fr' => 'Contrôle qualité', 'en' => 'Quality control'],
    ];

    $entry = $labels[$category] ?? ['fr' => $category, 'en' => $category];

    return $this->locale === 'en' ? $entry['en'] : $entry['fr'];
  }

  /**
   * @return array<string, array<string, string>>
   */
  private static function structureMaps(): array
  {
    if (self::$structureMaps !== null) {
      return self::$structureMaps;
    }

    $path = resource_path('data/report-structure-en.json');
    $decoded = json_decode((string) file_get_contents($path), true);

    return self::$structureMaps = is_array($decoded) ? $decoded : [];
  }
}
