<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesProject;
use App\Http\Controllers\Controller;
use App\Models\DailyUpdate;
use App\Models\Project;
use App\Models\Task;
use App\Support\GdaLocale;
use App\Support\GdaStatus;
use App\Support\ReportPresentation;
use App\Support\TaskProgressRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DailyUpdateController extends Controller
{
    use ResolvesProject;

    public function index(Request $request)
    {
        $project = $this->resolveProject($request);
        $date = $request->query('date', now()->toDateString());
        $presentation = ReportPresentation::forLocale(GdaLocale::fromRequest($request));
        $loc = $presentation->locale();

        $tasks = Task::query()
            ->forProject($project->id)
            ->with(['subPhase.phase', 'dailyUpdates' => fn ($q) => $q->forDate($date), 'latestDailyUpdate'])
            ->get()
            ->sortBy([
                fn (Task $a, Task $b) => $a->subPhase->phase->sort_order <=> $b->subPhase->phase->sort_order,
                fn (Task $a, Task $b) => $a->subPhase->sort_order <=> $b->subPhase->sort_order,
                fn (Task $a, Task $b) => $a->sort_order <=> $b->sort_order,
            ])
            ->values();

        $payload = $tasks->map(function (Task $task) use ($date, $presentation, $loc) {
            /** @var DailyUpdate|null $row */
            $row = $task->dailyUpdates->first();

            return [
                'task' => [
                    'id' => $task->id,
                    'phase_id' => $task->subPhase->phase_id,
                    'sub_phase_id' => $task->sub_phase_id,
                    'phase' => $presentation->translate($task->subPhase->phase->name, 'phases'),
                    'subphase' => $presentation->translate($task->subPhase->name, 'subphases'),
                    'activity' => $presentation->translate($task->activity, 'activities'),
                    'start_day' => $task->start_day,
                    'duration_days' => $task->duration_days,
                ],
                'daily_update' => $row ? [
                    'id' => $row->id,
                    'progress' => $row->progress,
                    'status' => $row->status,
                    'status_label' => GdaStatus::label($row->status, $loc),
                    'comment' => $row->comment,
                    'report_date' => $row->report_date->toDateString(),
                ] : null,
                'effective_progress' => $row?->progress ?? $task->latestDailyUpdate?->progress ?? 0,
                'effective_status' => $row?->status ?? $task->latestDailyUpdate?->status ?? 'non_demarre',
                'effective_status_label' => GdaStatus::label(
                    $row?->status ?? $task->latestDailyUpdate?->status ?? 'non_demarre',
                    $loc
                ),
                'effective_status_comment' => $this->localizedStatusComment(
                    $presentation,
                    $row?->status ?? $task->latestDailyUpdate?->status ?? 'non_demarre',
                    $row?->comment ?? $task->latestDailyUpdate?->comment
                ),
            ];
        });

        return response()->json(['date' => $date, 'items' => $payload]);
    }

    public function store(Request $request)
    {
        $project = $this->resolveProject($request);

        $data = $request->validate([
            'task_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'status' => ['nullable', 'in:'.implode(',', GdaStatus::SLUGS)],
            'comment' => ['nullable', 'string', 'max:5000'],
            'progress_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $task = $this->assertTaskInProject((int) $data['task_id'], $project);
        $task->load('latestDailyUpdate');

        $existing = DailyUpdate::query()
            ->where('task_id', $task->id)
            ->whereDate('report_date', $data['date'])
            ->first();

        $previousProgress = TaskProgressRecorder::previousProgressForTask($task, $existing);
        $newProgress = (int) $data['progress'];

        $status = $data['status'] ?? DailyUpdate::statusFromProgress($newProgress);
        $this->assertAnnuleComment($status, $data['comment'] ?? null);
        TaskProgressRecorder::assertAdminProgressNote(
            $request->user(),
            $previousProgress,
            $newProgress,
            $status,
            $data['progress_note'] ?? null,
        );

        $update = DailyUpdate::updateOrCreate(
            [
                'task_id' => $data['task_id'],
                'report_date' => $data['date'],
            ],
            [
                'user_id' => $request->user()->id,
                'progress' => $data['progress'],
                'status' => $status,
                'comment' => $data['comment'] ?? null,
            ]
        );

        TaskProgressRecorder::recordIfAdvanced(
            $request->user(),
            $task,
            $update,
            $previousProgress,
            $data['progress_note'] ?? null,
        );

        return response()->json($this->serializeDaily($update, $request), 201);
    }

    public function update(Request $request, DailyUpdate $daily)
    {
        $project = $this->resolveProject($request);
        $daily->load('task.subPhase.phase');
        abort_unless((int) $daily->task->subPhase->phase->project_id === (int) $project->id, 404);

        $data = $request->validate([
            'progress' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'status' => ['sometimes', 'in:'.implode(',', GdaStatus::SLUGS)],
            'comment' => ['nullable', 'string', 'max:5000'],
            'progress_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $task = $daily->task;
        $previousProgress = (int) $daily->progress;

        $nextStatus = array_key_exists('status', $data) ? $data['status'] : $daily->status;
        $nextComment = array_key_exists('comment', $data) ? $data['comment'] : $daily->comment;
        $nextProgress = array_key_exists('progress', $data) ? (int) $data['progress'] : (int) $daily->progress;

        $this->assertAnnuleComment($nextStatus, $nextComment);
        TaskProgressRecorder::assertAdminProgressNote(
            $request->user(),
            $previousProgress,
            $nextProgress,
            $nextStatus,
            $data['progress_note'] ?? null,
        );

        if (array_key_exists('progress', $data)) {
            $daily->progress = $data['progress'];
            if (! array_key_exists('status', $data)) {
                $daily->status = DailyUpdate::statusFromProgress((int) $data['progress']);
            }
        }
        if (array_key_exists('status', $data)) {
            $daily->status = $data['status'];
        }
        if (array_key_exists('comment', $data)) {
            $daily->comment = $data['comment'];
        }

        $daily->user_id = $request->user()->id;
        $daily->save();

        TaskProgressRecorder::recordIfAdvanced(
            $request->user(),
            $task,
            $daily,
            $previousProgress,
            $data['progress_note'] ?? null,
        );

        return response()->json($this->serializeDaily($daily, $request));
    }

    public function batch(Request $request)
    {
        $project = $this->resolveProject($request);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'updates' => ['required', 'array', 'min:1'],
            'updates.*.task_id' => ['required', 'integer'],
            'updates.*.progress' => ['required', 'integer', 'min:0', 'max:100'],
            'updates.*.status' => ['nullable', 'in:'.implode(',', GdaStatus::SLUGS)],
            'updates.*.comment' => ['nullable', 'string', 'max:5000'],
            'updates.*.progress_note' => ['nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($request, $project, $data): void {
            foreach ($data['updates'] as $row) {
                $task = $this->assertTaskInProject((int) $row['task_id'], $project);
                $task->load('latestDailyUpdate');

                $existing = DailyUpdate::query()
                    ->where('task_id', $task->id)
                    ->whereDate('report_date', $data['date'])
                    ->first();

                $previousProgress = TaskProgressRecorder::previousProgressForTask($task, $existing);
                $newProgress = (int) $row['progress'];
                $status = $row['status'] ?? DailyUpdate::statusFromProgress($newProgress);

                $this->assertAnnuleComment($status, $row['comment'] ?? null);
                TaskProgressRecorder::assertAdminProgressNote(
                    $request->user(),
                    $previousProgress,
                    $newProgress,
                    $status,
                    $row['progress_note'] ?? null,
                );

                $update = DailyUpdate::updateOrCreate(
                    [
                        'task_id' => $row['task_id'],
                        'report_date' => $data['date'],
                    ],
                    [
                        'user_id' => $request->user()->id,
                        'progress' => $row['progress'],
                        'status' => $status,
                        'comment' => $row['comment'] ?? null,
                    ]
                );

                TaskProgressRecorder::recordIfAdvanced(
                    $request->user(),
                    $task,
                    $update,
                    $previousProgress,
                    $row['progress_note'] ?? null,
                );
            }
        });

        return response()->json(['message' => 'Enregistré', 'count' => count($data['updates'])]);
    }

    protected function assertAnnuleComment(string $status, ?string $comment): void
    {
        if ($status !== 'annule') {
            return;
        }

        if (trim((string) $comment) === '') {
            throw ValidationException::withMessages([
                'comment' => 'Une description est obligatoire pour le statut Annulée.',
            ]);
        }
    }

    protected function statusCommentFor(string $status, ?string $comment): ?string
    {
        if ($status !== 'annule') {
            return null;
        }

        $comment = trim((string) $comment);

        return $comment === '' ? null : $comment;
    }

    protected function localizedStatusComment(ReportPresentation $presentation, string $status, ?string $comment): ?string
    {
        $raw = $this->statusCommentFor($status, $comment);
        if ($raw === null) {
            return null;
        }

        return $presentation->translate($raw, 'comments');
    }

    protected function assertTaskInProject(int $taskId, Project $project): Task
    {
        return Task::query()
            ->whereKey($taskId)
            ->forProject($project->id)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeDaily(DailyUpdate $u, Request $request): array
    {
        $presentation = ReportPresentation::forLocale(GdaLocale::fromRequest($request));
        $loc = $presentation->locale();

        return [
            'id' => $u->id,
            'task_id' => $u->task_id,
            'report_date' => $u->report_date->toDateString(),
            'progress' => $u->progress,
            'status' => $u->status,
            'status_label' => GdaStatus::label($u->status, $loc),
            'comment' => $u->comment,
        ];
    }
}
