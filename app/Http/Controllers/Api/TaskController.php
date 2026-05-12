<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesProject;
use App\Http\Controllers\Controller;
use App\Models\SubPhase;
use App\Models\Task;
use App\Support\GdaStatus;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    use ResolvesProject;

    public function index(Request $request)
    {
        $project = $this->resolveProject($request);

        $query = Task::query()
            ->forProject($project->id)
            ->with(['subPhase.phase', 'latestDailyUpdate']);

        if ($request->filled('phase_id')) {
            $query->whereHas('subPhase', fn ($q) => $q->where('phase_id', (int) $request->query('phase_id')));
        }

        if ($request->filled('sub_phase_id')) {
            $query->where('sub_phase_id', (int) $request->query('sub_phase_id'));
        }

        if ($request->filled('status')) {
            $status = $request->query('status');
            if ($status === 'non_demarre') {
                $query->where(function ($q) {
                    $q->whereDoesntHave('dailyUpdates')
                        ->orWhereHas('latestDailyUpdate', fn ($q2) => $q2->where('status', 'non_demarre'));
                });
            } else {
                $query->whereHas('latestDailyUpdate', fn ($q2) => $q2->where('status', $status));
            }
        }

        $tasks = $query->get()
            ->sortBy([
                fn (Task $a, Task $b) => $a->subPhase->phase->sort_order <=> $b->subPhase->phase->sort_order,
                fn (Task $a, Task $b) => $a->subPhase->sort_order <=> $b->subPhase->sort_order,
                fn (Task $a, Task $b) => $a->sort_order <=> $b->sort_order,
            ])
            ->values();

        return response()->json([
            'tasks' => $tasks->map(fn (Task $t) => $this->serializeTask($t)),
        ]);
    }

    public function store(Request $request)
    {
        $project = $this->resolveProject($request);

        $data = $request->validate([
            'sub_phase_id' => ['required', 'integer', 'exists:sub_phases,id'],
            'activity' => ['required', 'string', 'max:500'],
            'start_day' => ['nullable', 'integer', 'min:1'],
            'duration_days' => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $sub = SubPhase::query()->with('phase')->findOrFail($data['sub_phase_id']);
        abort_unless((int) $sub->phase->project_id === (int) $project->id, 422);

        $next = (Task::query()->where('sub_phase_id', $sub->id)->max('sort_order') ?? -1) + 1;

        $task = Task::create([
            'sub_phase_id' => $sub->id,
            'activity' => $data['activity'],
            'start_day' => $data['start_day'] ?? 1,
            'duration_days' => $data['duration_days'] ?? 1,
            'sort_order' => $data['sort_order'] ?? $next,
        ]);

        return response()->json([
            'task' => $this->serializeTask($task->load(['subPhase.phase', 'latestDailyUpdate'])),
        ], 201);
    }

    public function show(Request $request, Task $task)
    {
        $project = $this->resolveProject($request);
        abort_unless((int) $task->subPhase->phase->project_id === (int) $project->id, 404);

        $task->load([
            'subPhase.phase',
            'latestDailyUpdate',
            'dailyUpdates' => fn ($q) => $q->orderByDesc('report_date')->orderByDesc('id')->with('user'),
        ]);

        $history = $task->dailyUpdates->map(fn ($u) => [
            'id' => $u->id,
            'report_date' => $u->report_date->toDateString(),
            'progress' => $u->progress,
            'status' => $u->status,
            'status_label' => GdaStatus::labelFr($u->status),
            'comment' => $u->comment,
            'user_name' => $u->user?->name,
            'updated_at' => $u->updated_at->toIso8601String(),
        ]);

        return response()->json([
            'task' => $this->serializeTask($task),
            'daily_updates' => $history,
        ]);
    }

    public function update(Request $request, Task $task)
    {
        $project = $this->resolveProject($request);
        abort_unless((int) $task->subPhase->phase->project_id === (int) $project->id, 404);

        $data = $request->validate([
            'activity' => ['sometimes', 'string', 'max:500'],
            'start_day' => ['sometimes', 'integer', 'min:1'],
            'duration_days' => ['sometimes', 'integer', 'min:1'],
        ]);

        $task->update($data);

        return response()->json([
            'task' => $this->serializeTask($task->fresh(['subPhase.phase', 'latestDailyUpdate'])),
        ]);
    }

    public function destroy(Request $request, Task $task)
    {
        $project = $this->resolveProject($request);
        abort_unless((int) $task->subPhase->phase->project_id === (int) $project->id, 404);
        $task->delete();

        return response()->json(['message' => 'Activité supprimée']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeTask(Task $task): array
    {
        $task->loadMissing('subPhase.phase');
        $latest = $task->latestDailyUpdate;
        $status = $latest?->status ?? 'non_demarre';

        return [
            'id' => $task->id,
            'phase_id' => $task->subPhase->phase_id,
            'sub_phase_id' => $task->sub_phase_id,
            'phase' => $task->subPhase->phase->name,
            'subphase' => $task->subPhase->name,
            'activity' => $task->activity,
            'start_day' => $task->start_day,
            'duration_days' => $task->duration_days,
            'progress' => $latest?->progress ?? 0,
            'status' => $status,
            'status_label' => GdaStatus::labelFr($status),
            'status_comment' => $status === 'annule' ? ($latest?->comment) : null,
        ];
    }
}
