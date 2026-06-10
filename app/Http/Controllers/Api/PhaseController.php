<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesProject;
use App\Http\Controllers\Controller;
use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use App\Support\GdaLocale;
use App\Support\ReportPresentation;
use Illuminate\Http\Request;

class PhaseController extends Controller
{
    use ResolvesProject;

    public function index(Request $request, Project $project)
    {
        $this->authorizeProjectMember($request, $project);
        $presentation = ReportPresentation::forLocale(GdaLocale::fromRequest($request));

        $phases = $project->phases()
            ->orderBy('sort_order')
            ->with([
                'subPhases' => fn ($q) => $q->orderBy('sort_order')->with([
                    'tasks' => fn ($tq) => $tq->orderBy('sort_order'),
                ]),
            ])
            ->get();

        $subPhasesCount = $phases->sum(fn (Phase $phase) => $phase->subPhases->count());
        $tasksCount = $phases->sum(
            fn (Phase $phase) => $phase->subPhases->sum(fn ($subPhase) => $subPhase->tasks->count())
        );

        return response()->json([
            'meta' => [
                'phases_count' => $phases->count(),
                'sub_phases_count' => $subPhasesCount,
                'tasks_count' => $tasksCount,
            ],
            'phases' => $phases->map(fn (Phase $phase) => [
                'id' => $phase->id,
                'project_id' => $phase->project_id,
                'name' => $presentation->translate($phase->name, 'phases'),
                'sort_order' => $phase->sort_order,
                'hidden_from_partner' => (bool) $phase->hidden_from_partner,
                'sub_phases' => $phase->subPhases->map(fn ($sp) => [
                    'id' => $sp->id,
                    'phase_id' => $sp->phase_id,
                    'name' => $presentation->translate($sp->name, 'subphases'),
                    'sort_order' => $sp->sort_order,
                    'hidden_from_partner' => (bool) $sp->hidden_from_partner,
                    'tasks' => $sp->tasks->map(fn (Task $t) => [
                        'id' => $t->id,
                        'sub_phase_id' => $t->sub_phase_id,
                        'activity' => $presentation->translate($t->activity, 'activities'),
                        'start_day' => $t->start_day,
                        'duration_days' => $t->duration_days,
                        'sort_order' => $t->sort_order,
                        'hidden_from_partner' => (bool) $t->hidden_from_partner,
                    ]),
                ]),
            ]),
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $this->authorizeProjectMember($request, $project);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $next = ($project->phases()->max('sort_order') ?? -1) + 1;

        $phase = $project->phases()->create([
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? $next,
        ]);

        return response()->json([
            'phase' => [
                'id' => $phase->id,
                'project_id' => $phase->project_id,
                'name' => $phase->name,
                'sort_order' => $phase->sort_order,
            ],
        ], 201);
    }

    public function update(Request $request, Phase $phase)
    {
        $this->authorizeProjectMember($request, $phase->project);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'hidden_from_partner' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('hidden_from_partner', $data)) {
            abort_unless($request->user()?->canViewAllProjects(), 403, 'Réservé aux administrateurs.');
        }

        $phase->update($data);

        return response()->json([
            'phase' => [
                'id' => $phase->id,
                'project_id' => $phase->project_id,
                'name' => $phase->name,
                'sort_order' => $phase->sort_order,
            ],
        ]);
    }

    public function destroy(Request $request, Phase $phase)
    {
        $this->authorizeProjectMember($request, $phase->project);
        $phase->delete();

        return response()->json(['message' => 'Phase supprimée']);
    }
}
