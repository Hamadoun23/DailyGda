<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesProject;
use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\Project;
use App\Models\Report;
use App\Models\Task;
use App\Support\GdaStatus;
use App\Support\ReportPresentation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

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
        ]);

        $overall = $project->overallProgress();

        $report = Report::create([
            'project_id' => $project->id,
            'user_id' => $request->user()->id,
            'report_date' => $data['date'],
            'temperature' => $data['temperature'] ?? null,
            'weather' => $data['weather'] ?? null,
            'page_number' => $this->estimateReportPageNumber($project),
            'overall_progress' => $overall,
            'notes' => $data['notes'] ?? null,
            'generated_at' => now(),
        ]);

        $tasks = $this->tasksForPdf($project);

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
            'tasks' => $tasks,
        ], 201);
    }

    public function pdf(Request $request, Report $report)
    {
        $project = $this->resolveProject($request);
        abort_unless((int) $report->project_id === (int) $project->id, 404);

        $presentation = ReportPresentation::forLocale((string) $request->query('locale', 'fr'));
        $pdfRows = $this->buildPdfRows($project, $presentation);
        $pdfPhotoSections = $this->buildPdfPhotoSections($project, $presentation);

        $pdf = Pdf::loadView('reports.pdf', [
            'project' => $project,
            'report' => $report,
            'pdf_rows' => $pdfRows,
            'pdfPhotoSections' => $pdfPhotoSections,
            'projectTitle' => strtoupper($project->name),
            'copy' => $presentation->copy(),
            'presentation' => $presentation,
        ])->setPaper('a4', 'landscape');

        $filename = 'rapport-gda-'.$report->report_date->format('Y-m-d').'-'.$report->id.'.pdf';

        return $pdf->download($filename);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function tasksForPdf(Project $project): array
    {
        return $this->tasksForPdfCollection($project)->values()->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function tasksForPdfCollection(Project $project): Collection
    {
        return Task::query()
            ->forProject($project->id)
            ->with(['subPhase.phase', 'latestDailyUpdate'])
            ->get()
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

        $phaseCounts = $sorted->groupBy('phase')->map->count();
        $subCounts = $sorted->groupBy(fn (array $r) => $r['phase'].'|'.$r['subphase'])->map->count();

        $seenP = [];
        $seenS = [];
        $out = [];
        foreach ($sorted as $row) {
            $pk = $row['phase'];
            $sk = $pk.'|'.$row['subphase'];
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
            $out[] = array_merge($localized, [
                'show_phase_cell' => ! isset($seenP[$pk]),
                'phase_rowspan' => $phaseCounts[$pk],
                'show_subphase_cell' => ! isset($seenS[$sk]),
                'subphase_rowspan' => $subCounts[$sk],
            ]);
            $seenP[$pk] = true;
            $seenS[$sk] = true;
        }

        return $out;
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

                $mime = mime_content_type($full) ?: 'image/jpeg';
                $images[] = 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($full));
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

    protected function estimateReportPageNumber(Project $project): string
    {
        $taskCount = Task::query()->forProject($project->id)->count();
        $pages = max(1, (int) ceil($taskCount / 14));

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
