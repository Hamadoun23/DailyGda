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
    $value = trim($value ?? '');
    if ($this->locale !== 'en' || $value === '') {
      return $value;
    }

    $map = self::structureMaps()[$kind] ?? [];
    if (isset($map[$value])) {
      return $map[$value];
    }

    if ($kind === 'comments') {
      foreach ($map as $fr => $en) {
        if (mb_strtolower($fr) === mb_strtolower($value)) {
          return $en;
        }
      }

      return GdaAutoTranslate::translate($value, 'fr', 'en');
    }

    return $value;
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

  /**
   * Libellés section statistiques du rapport (PDF / aperçu).
   *
   * @return array<string, mixed>
   */
  public function statsCopy(): array
  {
    $xl = $this->dashboardExcelLabels();

    if ($this->locale === 'en') {
      return [
        'section_title' => 'Data & statistics',
        'kpi_total' => $xl['summary_total_tasks'],
        'kpi_done' => $xl['summary_done'],
        'kpi_in_progress' => $xl['summary_in_progress'],
        'kpi_cancelled' => $xl['summary_cancelled'],
        'overall' => $xl['summary_overall'],
        'chart_status' => $xl['chart_status_title'],
        'chart_phase' => $xl['chart_phase_title'],
        'chart_sub' => $xl['chart_sub_title'],
        'chart_act' => $xl['chart_act_title'],
        'data_section' => 'Daily report data',
        'col_count' => $xl['summary_count'],
        'col_progress' => $xl['phases_col_progress'],
        'col_tasks' => $xl['phases_col_count'],
      ];
    }

    return [
      'section_title' => 'Données & statistiques',
      'kpi_total' => $xl['summary_total_tasks'],
      'kpi_done' => $xl['summary_done'],
      'kpi_in_progress' => $xl['summary_in_progress'],
      'kpi_cancelled' => $xl['summary_cancelled'],
      'overall' => $xl['summary_overall'],
      'chart_status' => $xl['chart_status_title'],
      'chart_phase' => $xl['chart_phase_title'],
      'chart_sub' => $xl['chart_sub_title'],
      'chart_act' => $xl['chart_act_title'],
      'data_section' => 'Données du rapport',
      'col_count' => $xl['summary_count'],
      'col_progress' => $xl['phases_col_progress'],
      'col_tasks' => $xl['phases_col_count'],
    ];
  }

  /**
   * Légende « activité récente » (tableau de bord / Excel).
   */
  public function dashboardRecentAction(string $status, ?string $comment): string
  {
    $comment = trim((string) $comment);
    if ($status === 'annule' && $comment !== '') {
      $body = $this->locale === 'en' ? $this->translate($comment, 'comments') : $comment;
      $prefix = $this->locale === 'en' ? 'Cancelled · ' : 'Annulée · ';
      $suffix = mb_strlen($body) > 80 ? mb_substr($body, 0, 80).'…' : $body;

      return $prefix.$suffix;
    }
    if ($comment !== '') {
      $body = $this->locale === 'en' ? $this->translate($comment, 'comments') : $comment;
      $prefix = $this->locale === 'en' ? 'Comment · ' : 'Commentaire · ';
      $suffix = mb_strlen($body) > 80 ? mb_substr($body, 0, 80).'…' : $body;

      return $prefix.$suffix;
    }

    return $this->locale === 'en' ? 'Progress updated' : 'Progression mise à jour';
  }

  /**
   * Libellés feuilles Excel, en-têtes et graphiques (export tableau de bord).
   *
   * @return array<string, string>
   */
  public function dashboardExcelLabels(): array
  {
    if ($this->locale === 'en') {
      return [
        'sheet_summary' => 'Summary',
        'sheet_phases' => 'Phases',
        'sheet_subphases' => 'Sub-phases',
        'sheet_activities' => 'Activities',
        'sheet_recent' => 'Recent activity',
        'summary_project' => 'Project',
        'summary_export' => 'Export',
        'summary_overall' => 'Overall progress (%)',
        'summary_total_tasks' => 'Total tasks',
        'summary_done' => 'Completed',
        'summary_in_progress' => 'In progress',
        'summary_cancelled' => 'Cancelled',
        'summary_status' => 'Status',
        'summary_count' => 'Count',
        'status_nd' => 'Not started',
        'status_ip' => 'In progress',
        'status_ok' => 'Completed',
        'status_cancel' => 'Cancelled',
        'phases_col_phase' => 'Phase',
        'phases_col_progress' => 'Progress (%)',
        'phases_col_count' => 'No. of activities',
        'sub_col_phase' => 'Phase',
        'sub_col_sub' => 'Sub-phase',
        'sub_col_avg' => 'Avg. progress (%)',
        'sub_col_count' => 'No. of activities',
        'act_col_phase' => 'Phase',
        'act_col_sub' => 'Sub-phase',
        'act_col_activity' => 'Activity',
        'act_col_progress' => 'Progress (%)',
        'act_col_status' => 'Status',
        'act_col_chart_label' => 'Chart label',
        'recent_col_time' => 'Time',
        'recent_col_task' => 'Task',
        'recent_col_detail' => 'Detail',
        'recent_col_progress' => 'Progress (%)',
        'recent_col_user' => 'User',
        'recent_col_status' => 'Status',
        'chart_legend_count' => 'Count',
        'chart_axis_percent' => 'Percentage',
        'chart_status_title' => 'Breakdown by status',
        'chart_phase_title' => 'Progress by phase (%)',
        'chart_sub_title' => 'Sub-phases — average progress',
        'chart_act_title' => 'Activities — progress',
        'chart_series_progress_pct' => 'Progress (%)',
        'chart_series_avg_progress_pct' => 'Avg. progress (%)',
      ];
    }

    return [
      'sheet_summary' => 'Synthèse',
      'sheet_phases' => 'Phases',
      'sheet_subphases' => 'Sous-phases',
      'sheet_activities' => 'Activités',
      'sheet_recent' => 'Activité récente',
      'summary_project' => 'Projet',
      'summary_export' => 'Export',
      'summary_overall' => 'Progression globale (%)',
      'summary_total_tasks' => 'Tâches totales',
      'summary_done' => 'Terminées',
      'summary_in_progress' => 'En cours',
      'summary_cancelled' => 'Annulées',
      'summary_status' => 'Statut',
      'summary_count' => 'Nombre',
      'status_nd' => 'Non démarrées',
      'status_ip' => 'En cours',
      'status_ok' => 'Terminées',
      'status_cancel' => 'Annulées',
      'phases_col_phase' => 'Phase',
      'phases_col_progress' => 'Progression (%)',
      'phases_col_count' => 'Nb activités',
      'sub_col_phase' => 'Phase',
      'sub_col_sub' => 'Sous-phase',
      'sub_col_avg' => 'Progression moy. (%)',
      'sub_col_count' => 'Nb activités',
      'act_col_phase' => 'Phase',
      'act_col_sub' => 'Sous-phase',
      'act_col_activity' => 'Activité',
      'act_col_progress' => 'Progression (%)',
      'act_col_status' => 'Statut',
      'act_col_chart_label' => 'Libellé (graph)',
      'recent_col_time' => 'Heure',
      'recent_col_task' => 'Tâche',
      'recent_col_detail' => 'Détail',
      'recent_col_progress' => 'Progression (%)',
      'recent_col_user' => 'Utilisateur',
      'recent_col_status' => 'Statut',
      'chart_legend_count' => 'Nombre',
      'chart_axis_percent' => 'Pourcentage',
      'chart_status_title' => 'Répartition par statut',
      'chart_phase_title' => 'Avancement par phase (%)',
      'chart_sub_title' => 'Sous-phases — progression moyenne',
      'chart_act_title' => 'Activités — progression',
      'chart_series_progress_pct' => 'Progression (%)',
      'chart_series_avg_progress_pct' => 'Progression moy. (%)',
    ];
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
