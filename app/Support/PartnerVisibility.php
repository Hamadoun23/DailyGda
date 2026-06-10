<?php

namespace App\Support;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class PartnerVisibility
{
    public static function filterForPartner(?User $user): bool
    {
        return $user !== null && $user->isPartner();
    }

    /**
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public static function applyToTaskQuery(Builder $query): Builder
    {
        return $query
            ->where('tasks.hidden_from_partner', false)
            ->whereHas('subPhase', function (Builder $subQuery): void {
                $subQuery
                    ->where('hidden_from_partner', false)
                    ->whereHas('phase', fn (Builder $phaseQuery) => $phaseQuery->where('hidden_from_partner', false));
            });
    }

    public static function isTaskVisibleToPartner(Task $task): bool
    {
        $task->loadMissing('subPhase.phase');

        if ($task->hidden_from_partner) {
            return false;
        }

        $sub = $task->subPhase;
        if (! $sub || $sub->hidden_from_partner) {
            return false;
        }

        $phase = $sub->phase;

        return $phase && ! $phase->hidden_from_partner;
    }

    /**
     * @return array{hidden_from_partner: bool, phase_hidden_from_partner: bool, subphase_hidden_from_partner: bool, partner_hidden: bool}
     */
    public static function taskVisibilityPayload(Task $task): array
    {
        $task->loadMissing('subPhase.phase');
        $phase = $task->subPhase?->phase;
        $sub = $task->subPhase;

        return [
            'hidden_from_partner' => (bool) $task->hidden_from_partner,
            'phase_hidden_from_partner' => (bool) ($phase?->hidden_from_partner),
            'subphase_hidden_from_partner' => (bool) ($sub?->hidden_from_partner),
            'partner_hidden' => ! self::isTaskVisibleToPartner($task),
        ];
    }
}
