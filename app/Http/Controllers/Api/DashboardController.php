<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesProject;
use App\Http\Controllers\Controller;
use App\Models\DailyUpdate;
use App\Models\Task;
use App\Support\GdaStatus;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ResolvesProject;

    public function index(Request $request)
    {
        $project = $this->resolveProject($request);

        $tasks = Task::query()
            ->forProject($project->id)
            ->with(['latestDailyUpdate', 'subPhase.phase'])
            ->get();

        $overall = $project->overallProgress();

        $progressByPhase = [];
        foreach ($project->progressByPhase() as $phaseName => $pct) {
            $count = $tasks->filter(fn (Task $t) => $t->subPhase->phase->name === $phaseName)->count();
            $progressByPhase[] = [
                'phase' => $phaseName,
                'progress' => $pct,
                'task_count' => $count,
            ];
        }

        $done = $tasks->filter(fn (Task $t) => ($t->latestDailyUpdate?->status ?? 'non_demarre') === 'termine')->count();
        $inProgress = $tasks->filter(fn (Task $t) => ($t->latestDailyUpdate?->status ?? '') === 'en_cours')->count();
        $cancelled = $tasks->filter(fn (Task $t) => ($t->latestDailyUpdate?->status ?? '') === 'annule')->count();

        $recent = DailyUpdate::query()
            ->forProject($project->id)
            ->with(['user', 'task.subPhase.phase'])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        $recentActivity = $recent->map(function (DailyUpdate $u) {
            $action = $u->status === 'annule' && $u->comment
                ? 'Annulée · '.mb_substr($u->comment, 0, 80).(mb_strlen($u->comment) > 80 ? '…' : '')
                : ($u->comment
                    ? 'Commentaire · '.mb_substr($u->comment, 0, 80).(mb_strlen($u->comment) > 80 ? '…' : '')
                    : 'Progression mise à jour');

            return [
                'ts' => $u->updated_at->toIso8601String(),
                'time' => $u->updated_at->format('H:i'),
                'task_name' => $u->task->subPhase->name.' — '.$u->task->activity,
                'action' => $action,
                'progress' => $u->progress,
                'user' => $u->user->name,
                'status_label' => GdaStatus::labelFr($u->status),
            ];
        });

        return response()->json([
            'overall_progress' => $overall,
            'progress_by_phase' => $progressByPhase,
            'stats' => [
                'total' => $tasks->count(),
                'done' => $done,
                'in_progress' => $inProgress,
                'cancelled' => $cancelled,
            ],
            'recent_activity' => $recentActivity,
        ]);
    }
}
