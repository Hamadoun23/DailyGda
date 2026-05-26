<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesProject;
use App\Http\Controllers\Controller;
use App\Models\Phase;
use App\Models\Project;
use App\Models\SubPhase;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StructureOrderController extends Controller
{
    use ResolvesProject;

    public function reorderProjects(Request $request)
    {
        $order = $this->validatedOrder($request);
        $user = $request->user();

        $query = Project::query()->whereIn('id', $order);
        if ($user && ! $user->canViewAllProjects()) {
            $query->whereHas('users', fn ($rel) => $rel->whereKey($user->id));
        }

        $this->applySortOrder($query->pluck('id')->all(), $order, Project::class);

        return response()->json(['ok' => true]);
    }

    public function reorderPhases(Request $request, Project $project)
    {
        $this->authorizeProjectMember($request, $project);
        $order = $this->validatedOrder($request);

        $ids = $project->phases()->whereIn('id', $order)->pluck('id')->all();
        $this->applySortOrder($ids, $order, Phase::class);

        return response()->json(['ok' => true]);
    }

    public function reorderSubPhases(Request $request, Phase $phase)
    {
        $this->authorizeProjectMember($request, $phase->project);
        $order = $this->validatedOrder($request);

        $ids = $phase->subPhases()->whereIn('id', $order)->pluck('id')->all();
        $this->applySortOrder($ids, $order, SubPhase::class);

        return response()->json(['ok' => true]);
    }

    public function reorderTasks(Request $request, SubPhase $subPhase)
    {
        $this->authorizeProjectMember($request, $subPhase->phase->project);
        $order = $this->validatedOrder($request);

        $ids = $subPhase->tasks()->whereIn('id', $order)->pluck('id')->all();
        $this->applySortOrder($ids, $order, Task::class);

        return response()->json(['ok' => true]);
    }

    /**
     * @return list<int>
     */
    private function validatedOrder(Request $request): array
    {
        $data = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer', 'distinct'],
        ]);

        return array_values(array_map('intval', $data['order']));
    }

    /**
     * @param  list<int|string>  $foundIds
     * @param  list<int>  $order
     * @param  class-string  $modelClass
     */
    private function applySortOrder(array $foundIds, array $order, string $modelClass): void
    {
        $found = array_map('intval', $foundIds);
        $requested = array_values(array_unique($order));

        if (count($found) !== count($requested)) {
            abort(422, 'Ordre invalide : éléments introuvables ou projet incorrect.');
        }

        DB::transaction(function () use ($requested, $modelClass): void {
            foreach ($requested as $index => $id) {
                $modelClass::query()->whereKey($id)->update(['sort_order' => $index]);
            }
        });
    }
}
