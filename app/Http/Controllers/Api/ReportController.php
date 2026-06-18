<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesProject;
use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\Project;
use App\Models\Report;
use App\Models\Task;
use App\Support\GdaLocale;
use App\Support\GdaStatus;
use App\Support\PartnerVisibility;
use App\Support\PdfImageEncoder;
use App\Support\ProjectStatistics;
use App\Support\ReportChartStorage;
use App\Support\ReportPdfGenerator;
use App\Support\ReportPresentation;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ReportController extends Controller
{
    use ResolvesProject;

    /** @var list<string> */
    private const PHOTO_CATEGORIES = ['avant', 'pendant', 'apres', 'securite', 'qualite'];

    public function index(Request $request)
    {
        $project = $this->resolveProject($request);

        $reports = Report::query()
            ->where('project_id', $project->id)
            ->with('user')
            ->orderByDesc('generated_at')
            ->limit(50)
            ->get()
            ->map(fn (Report $r) => [
                'id' => $r->id,
                'report_date' => $r->report_date->toDateString(),
                'overall_progress' => $r->overall_progress,
                'temperature' => $r->temperature,
                'weather' => $r->weather,
                'page_number' => $r->page_number,
                'generated_at' => $r->generated_at?->toIso8601String(),
                'user_name' => $r->user->name,
            ]);

        return response()->json(['reports' => $reports]);
    }

    public function generate(Request $request)
    {
        $project = $this->resolveProject($request);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'temperature' => ['nullable', 'numeric'],
            'weather' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'chart_images' => ['nullable', 'array'],
            'chart_images.status' => ['nullable', 'string', 'max:12000000'],
            'chart_images.phase' => ['nullable', 'string', 'max:12000000'],
            'chart_images.sub' => ['nullable', 'string', 'max:12000000'],
            'chart_images.act' => ['nullable', 'string', 'max:12000000'],
        ]);

        $presentation = ReportPresentation::forLocale(GdaLocale::fromRequest($request));
        $statistics = ProjectStatistics::build($project, $presentation, $request->user(), excludePartnerHidden: true);

        $report = Report::create([
            'project_id' => $project->id,
            'user_id' => $request->user()->id,
            'report_date' => $data['date'],
            'temperature' => $data['temperature'] ?? null,
            'weather' => $data['weather'] ?? null,
            'page_number' => $this->estimateReportPageNumber($project),
            'overall_progress' => $statistics['overall_progress'],
            'notes' => $data['notes'] ?? null,
            'generated_at' => now(),
        ]);

        $chartImages = $this->sanitizeChartImages($data['chart_images'] ?? null);
        if ($chartImages !== []) {
            ReportChartStorage::persist($report->id, $chartImages);
            Cache::put('report_pdf_charts:'.$report->id, $chartImages, now()->addHours(24));
        }

        $tasks = $this->tasksForPdfCollection($project)
            ->map(fn (array $row) => $this->localizeTaskRowForApi($row, $presentation))
            ->values()
            ->all();

        return response()->json([
            'report' => [
                'id' => $report->id,
                'report_date' => $report->report_date->toDateString(),
                'temperature' => $report->temperature,
                'weather' => $report->weather,
                'page_number' => $report->page_number,
                'overall_progress' => $report->overall_progress,
                'notes' => $report->notes,
            ],
            'statistics' => $statistics,
            'tasks' => $tasks,
        ], 201);
    }

    public function pdf(Request $request, Report $report)
    {
        $project = $this->resolveProject($request);
        abort_unless((int) $report->project_id === (int) $project->id, 404);

        $locale = (string) $request->query('locale', '') !== ''
            ? (string) $request->query('locale', 'fr')
            : GdaLocale::fromRequest($request);
        $presentation = ReportPresentation::forLocale($locale);
        $forPartner = PartnerVisibility::filterForPartner($request->user());
        $statistics = ProjectStatistics::build($project, $presentation, $request->user(), excludePartnerHidden: true);
        $displayOverallProgress = (int) ($statistics['overall_progress'] ?? $report->overall_progress);
        $chartImages = ReportChartStorage::loadForPdf($report->id);
        if ($chartImages === []) {
            $chartImages = Cache::get('report_pdf_charts:'.$report->id, []);
        }
        // Graphiques figés à la génération (souvent admin) : ne pas les montrer au partenaire.
        if ($forPartner) {
            $chartImages = [];
        }
        $pdfRows = $this->buildPdfRows($project, $presentation);
        $pdfPhotoSections = $this->buildPdfPhotoSections($project, $presentation);

        $filename = 'rapport-gda-'.$report->report_date->format('Y-m-d').'-'.$report->id.'.pdf';

        try {
            return ReportPdfGenerator::download($filename, [
                'project' => $project,
                'report' => $report,
                'statistics' => $statistics,
                'display_overall_progress' => $displayOverallProgress,
                'show_admin_partner_markers' => false,
                'chartImages' => $chartImages,
                'pdf_rows' => $pdfRows,
                'pdfPhotoSections' => $pdfPhotoSections,
                'projectTitle' => strtoupper($project->name),
                'copy' => $presentation->copy(),
                'statsCopy' => $presentation->statsCopy(),
                'presentation' => $presentation,
            ], $presentation->copy()['page'] ?? 'Page');
        } catch (Throwable $e) {
            report($e);

            $message = config('app.debug')
                ? 'PDF : '.$e->getMessage()
                : 'PDF indisponible.';

            return response()->json(['message' => $message], 500);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function tasksForPdf(Project $project): array
    {
        return $this->tasksForPdfCollection($project)->values()->all();
    }

    /**
     * Tâches visibles partenaire uniquement (export PDF partageable).
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function tasksForPdfCollection(Project $project): Collection
    {
        $query = Task::query()
            ->forProject($project->id)
            ->with(['subPhase.phase', 'latestDailyUpdate']);

        PartnerVisibility::applyToTaskQuery($query);

        return $query->get()
            ->sortBy([
                fn (Task $a, Task $b) => $a->subPhase->phase->sort_order <=> $b->subPhase->phase->sort_order,
                fn (Task $a, Task $b) => $a->subPhase->sort_order <=> $b->subPhase->sort_order,
                fn (Task $a, Task $b) => $a->sort_order <=> $b->sort_order,
            ])
            ->values()
            ->map(function (Task $task) {
                $latest = $task->latestDailyUpdate;
                $status = $latest?->status ?? 'non_demarre';

                return [
                    'phase' => $task->subPhase->phase->name,
                    'phase_sort' => $task->subPhase->phase->sort_order,
                    'subphase' => $task->subPhase->name,
                    'subphase_sort' => $task->subPhase->sort_order,
                    'activity' => $task->activity,
                    'sort_order' => $task->sort_order,
                    'start_day' => $task->start_day,
                    'duration_days' => $task->duration_days,
                    'progress' => $latest?->progress ?? 0,
                    'status' => $status,
                    'status_label' => GdaStatus::labelFr($status),
                    'status_comment' => $status === 'annule' ? $latest?->comment : null,
                ];
            });
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buildPdfRows(Project $project, ?ReportPresentation $presentation = null): array
    {
        $presentation ??= ReportPresentation::forLocale('fr');
        $sorted = $this->tasksForPdfCollection($project)->sortBy([
            fn (array $a, array $b) => $a['phase_sort'] <=> $b['phase_sort'],
            fn (array $a, array $b) => $a['subphase_sort'] <=> $b['subphase_sort'],
            fn (array $a, array $b) => $a['sort_order'] <=> $b['sort_order'],
        ])->values();

        $out = [];
        foreach ($sorted as $row) {
            $localized = array_merge($row, [
                'phase' => $presentation->translate($row['phase'], 'phases'),
                'subphase' => $presentation->translate($row['subphase'], 'subphases'),
                'activity' => $presentation->translate($row['activity'], 'activities'),
                'status_label' => $presentation->statusLabel($row['status'], $row['status_label']),
                'status_comment' => $row['status_comment']
                    ? $presentation->translate($row['status_comment'], 'comments')
                    : null,
                'duration_label' => $presentation->durationLabel((int) $row['duration_days']),
                'start_label' => $presentation->formatTaskStartDate($project->start_date, (int) $row['start_day']),
            ]);
            $out[] = $localized;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function localizeTaskRowForApi(array $row, ReportPresentation $presentation): array
    {
        return array_merge($row, [
            'phase' => $presentation->translate($row['phase'], 'phases'),
            'subphase' => $presentation->translate($row['subphase'], 'subphases'),
            'activity' => $presentation->translate($row['activity'], 'activities'),
            'status_label' => $presentation->statusLabel($row['status'], GdaStatus::labelFr($row['status'])),
            'status_comment' => $row['status_comment']
                ? $presentation->translate($row['status_comment'], 'comments')
                : null,
        ]);
    }

    /**
     * @return list<array{category: string, title: string, count: int, images: list<string>}>
     */
    protected function buildPdfPhotoSections(Project $project, ReportPresentation $presentation): array
    {
        $sections = [];

        foreach (self::PHOTO_CATEGORIES as $category) {
            $photos = Photo::query()
                ->where('project_id', $project->id)
                ->where('category', $category)
                ->orderBy('created_at')
                ->get();

            if ($photos->isEmpty()) {
                continue;
            }

            $images = [];
            foreach ($photos as $photo) {
                $full = Storage::disk('public')->path($photo->path);
                if (! is_file($full)) {
                    continue;
                }

                $encoded = PdfImageEncoder::photoDataUri($full, 520, 88);
                if ($encoded !== null) {
                    $images[] = $encoded;
                }
            }

            if ($images === []) {
                continue;
            }

            $sections[] = [
                'category' => $category,
                'title' => $presentation->photoCategoryLabel($category),
                'count' => count($images),
                'images' => $images,
            ];
        }

        return $sections;
    }

    /**
     * @param  array<string, mixed>|null  $images
     * @return array<string, string>
     */
    protected function sanitizeChartImages(?array $images): array
    {
        if ($images === null || $images === []) {
            return [];
        }

        $allowed = ['status', 'phase', 'sub', 'act'];
        $out = [];
        foreach ($allowed as $key) {
            $value = $images[$key] ?? null;
            if (! is_string($value)) {
                continue;
            }
            if (! preg_match('#^data:image/(png|jpeg|jpg);base64,#i', $value)) {
                continue;
            }
            if (strlen($value) > 12_000_000) {
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    protected function estimateReportPageNumber(Project $project): string
    {
        $taskCountQuery = Task::query()->forProject($project->id);
        PartnerVisibility::applyToTaskQuery($taskCountQuery);
        $taskCount = $taskCountQuery->count();
        $pages = max(1, (int) ceil($taskCount / 14));
        if ($taskCount > 0) {
            $pages++;
        }

        $photoCategories = Photo::query()
            ->where('project_id', $project->id)
            ->distinct()
            ->pluck('category');

        foreach (self::PHOTO_CATEGORIES as $category) {
            if ($photoCategories->contains($category)) {
                $pages++;
            }
        }

        return '1/'.$pages;
    }
}
