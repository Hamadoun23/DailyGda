<?php

namespace App\Support;

use App\Models\DailyUpdate;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

final class ProjectStatistics
{
    /**
     * Synthèse statistiques du projet (tableau de bord / rapport PDF).
     *
     * @return array<string, mixed>
     */
    public static function build(Project $project, ReportPresentation $presentation, ?User $user = null): array
    {
        $forPartner = PartnerVisibility::filterForPartner($user);
        $tasks = self::tasksForStats($project, $forPartner);
        $overall = self::overallProgressFromTasks($tasks);
        $loc = $presentation->locale();

        $progressByPhase = $tasks
            ->groupBy(fn (Task $t) => $t->subPhase->phase_id)
            ->map(function (Collection $phaseTasks) use ($presentation, $forPartner) {
                /** @var Task $sample */
                $sample = $phaseTasks->first();
                $sum = $phaseTasks->sum(fn (Task $t) => $t->latestDailyUpdate?->progress ?? 0);
                $pct = $phaseTasks->isEmpty() ? 0 : (int) round($sum / $phaseTasks->count());

                $row = [
                    'phase_sort' => $sample->subPhase->phase->sort_order,
                    'phase' => $presentation->translate($sample->subPhase->phase->name, 'phases'),
                    'progress' => $pct,
                    'task_count' => $phaseTasks->count(),
                ];
                if (! $forPartner) {
                    $row['partner_hidden'] = (bool) $sample->subPhase->phase->hidden_from_partner;
                }

                return $row;
            })
            ->sortBy('phase_sort')
            ->values()
            ->map(fn (array $row) => [
                'phase' => $row['phase'],
                'progress' => $row['progress'],
                'task_count' => $row['task_count'],
                ...(! $forPartner ? ['partner_hidden' => (bool) ($row['partner_hidden'] ?? false)] : []),
            ])
            ->all();

        $done = $tasks->filter(fn (Task $t) => ($t->latestDailyUpdate?->status ?? 'non_demarre') === 'termine')->count();
        $inProgress = $tasks->filter(fn (Task $t) => ($t->latestDailyUpdate?->status ?? '') === 'en_cours')->count();
        $cancelled = $tasks->filter(fn (Task $t) => ($t->latestDailyUpdate?->status ?? '') === 'annule')->count();

        $recentActivity = DailyUpdate::query()
            ->forProject($project->id)
            ->with(['user', 'task.subPhase.phase'])
            ->orderByDesc('updated_at')
            ->limit(150)
            ->get()
            ->when($forPartner, fn (Collection $rows) => $rows->filter(
                fn (DailyUpdate $u) => $u->task && PartnerVisibility::isTaskVisibleToPartner($u->task)
            ))
            ->unique('task_id')
            ->take(5)
            ->map(function (DailyUpdate $u) use ($presentation, $loc) {
                $sp = $u->task->subPhase->name;
                $act = $u->task->activity;
                $taskName = $presentation->translate($sp, 'subphases').' — '.$presentation->translate($act, 'activities');

                return [
                    'task_id' => $u->task_id,
                    'ts' => $u->updated_at->toIso8601String(),
                    'time' => $u->updated_at->format('H:i'),
                    'task_name' => $taskName,
                    'action' => $presentation->dashboardRecentAction($u->status, $u->comment),
                    'progress' => $u->progress,
                    'user' => $u->user->name,
                    'status_label' => GdaStatus::label($u->status, $loc),
                ];
            })
            ->values()
            ->all();

        $statusOf = fn (Task $t): string => $t->latestDailyUpdate?->status ?? 'non_demarre';
        $statusCounts = [
            'non_demarre' => $tasks->filter(fn (Task $t) => $statusOf($t) === 'non_demarre')->count(),
            'en_cours' => $tasks->filter(fn (Task $t) => $statusOf($t) === 'en_cours')->count(),
            'termine' => $tasks->filter(fn (Task $t) => $statusOf($t) === 'termine')->count(),
            'annule' => $tasks->filter(fn (Task $t) => $statusOf($t) === 'annule')->count(),
        ];

        $subphaseRows = $tasks->groupBy('sub_phase_id')->map(function ($group) {
            /** @var Task $t */
            $t = $group->first();

            return [
                'phase' => $t->subPhase->phase->name,
                'subphase' => $t->subPhase->name,
                'phase_sort' => $t->subPhase->phase->sort_order,
                'subphase_sort' => $t->subPhase->sort_order,
                'avg_progress' => (int) round($group->avg(fn (Task $x) => $x->latestDailyUpdate?->progress ?? 0)),
                'task_count' => $group->count(),
            ];
        })->values()->sortBy(fn (array $r) => sprintf('%05d-%05d', $r['phase_sort'], $r['subphase_sort']))
            ->values()
            ->map(function (array $r) use ($presentation) {
                return [
                    'phase' => $presentation->translate($r['phase'], 'phases'),
                    'subphase' => $presentation->translate($r['subphase'], 'subphases'),
                    'avg_progress' => $r['avg_progress'],
                    'task_count' => $r['task_count'],
                ];
            })
            ->values()
            ->all();

        $activityRows = $tasks
            ->sortBy(fn (Task $t) => sprintf(
                '%05d-%05d-%05d',
                $t->subPhase->phase->sort_order,
                $t->subPhase->sort_order,
                $t->sort_order
            ))
            ->values()
            ->map(function (Task $t) use ($presentation, $loc, $forPartner) {
                $st = $t->latestDailyUpdate?->status ?? 'non_demarre';

                $row = [
                    'phase' => $presentation->translate($t->subPhase->phase->name, 'phases'),
                    'subphase' => $presentation->translate($t->subPhase->name, 'subphases'),
                    'activity' => $presentation->translate($t->activity, 'activities'),
                    'progress' => (int) ($t->latestDailyUpdate?->progress ?? 0),
                    'status' => $st,
                    'status_label' => GdaStatus::label($st, $loc),
                ];
                if (! $forPartner) {
                    $row['partner_hidden'] = ! PartnerVisibility::isTaskVisibleToPartner($t);
                }

                return $row;
            })
            ->all();

        return [
            'overall_progress' => $overall,
            'progress_by_phase' => $progressByPhase,
            'stats' => [
                'total' => $tasks->count(),
                'done' => $done,
                'in_progress' => $inProgress,
                'cancelled' => $cancelled,
            ],
            'recent_activity' => $recentActivity,
            'charts' => [
                'status_counts' => $statusCounts,
                'subphases' => $subphaseRows,
                'activities' => $activityRows,
            ],
        ];
    }

    /**
     * @return Collection<int, Task>
     */
    private static function tasksForStats(Project $project, bool $forPartner): Collection
    {
        $query = Task::query()
            ->forProject($project->id)
            ->with(['latestDailyUpdate', 'subPhase.phase']);

        if ($forPartner) {
            PartnerVisibility::applyToTaskQuery($query);
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, Task>  $tasks
     */
    private static function overallProgressFromTasks(Collection $tasks): int
    {
        if ($tasks->isEmpty()) {
            return 0;
        }

        $sum = $tasks->sum(fn (Task $task) => $task->latestDailyUpdate?->progress ?? 0);

        return (int) round($sum / $tasks->count());
    }
}
