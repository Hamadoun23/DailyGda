<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesProject;
use App\Http\Controllers\Controller;
use App\Models\DailyUpdate;
use App\Models\Project;
use App\Models\Task;
use App\Support\GdaStatus;
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

        $payload = $tasks->map(function (Task $task) use ($date) {
            /** @var DailyUpdate|null $row */
            $row = $task->dailyUpdates->first();

            return [
                'task' => [
                    'id' => $task->id,
                    'phase_id' => $task->subPhase->phase_id,
                    'sub_phase_id' => $task->sub_phase_id,
                    'phase' => $task->subPhase->phase->name,
                    'subphase' => $task->subPhase->name,
                    'activity' => $task->activity,
                    'start_day' => $task->start_day,
                    'duration_days' => $task->duration_days,
                ],
                'daily_update' => $row ? [
                    'id' => $row->id,
                    'progress' => $row->progress,
                    'status' => $row->status,
                    'status_label' => GdaStatus::labelFr($row->status),
                    'comment' => $row->comment,
                    'report_date' => $row->report_date->toDateString(),
                ] : null,
                'effective_progress' => $row?->progress ?? $task->latestDailyUpdate?->progress ?? 0,
                'effective_status' => $row?->status ?? $task->latestDailyUpdate?->status ?? 'non_demarre',
                'effective_status_label' => GdaStatus::labelFr($row?->status ?? $task->latestDailyUpdate?->status ?? 'non_demarre'),
                'effective_status_comment' => $this->statusCommentFor(
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
        ]);

        $this->assertTaskInProject((int) $data['task_id'], $project);

        $status = $data['status'] ?? DailyUpdate::statusFromProgress((int) $data['progress']);
        $this->assertAnnuleComment($status, $data['comment'] ?? null);

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

        return response()->json($this->serializeDaily($update), 201);
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
        ]);

        $nextStatus = array_key_exists('status', $data) ? $data['status'] : $daily->status;
        $nextComment = array_key_exists('comment', $data) ? $data['comment'] : $daily->comment;
        $this->assertAnnuleComment($nextStatus, $nextComment);

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

        return response()->json($this->serializeDaily($daily));
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
        ]);

        DB::transaction(function () use ($request, $project, $data): void {
            foreach ($data['updates'] as $row) {
                $this->assertTaskInProject((int) $row['task_id'], $project);
                $status = $row['status'] ?? DailyUpdate::statusFromProgress((int) $row['progress']);
                $this->assertAnnuleComment($status, $row['comment'] ?? null);
                DailyUpdate::updateOrCreate(
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
    protected function serializeDaily(DailyUpdate $u): array
    {
        return [
            'id' => $u->id,
            'task_id' => $u->task_id,
            'report_date' => $u->report_date->toDateString(),
            'progress' => $u->progress,
            'status' => $u->status,
            'status_label' => GdaStatus::labelFr($u->status),
            'comment' => $u->comment,
        ];
    }
}
