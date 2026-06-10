<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesProject;
use App\Http\Controllers\Controller;
use App\Models\Phase;
use App\Models\SubPhase;
use Illuminate\Http\Request;

class SubPhaseController extends Controller
{
    use ResolvesProject;

    public function store(Request $request, Phase $phase)
    {
        $this->authorizeProjectMember($request, $phase->project);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $next = ($phase->subPhases()->max('sort_order') ?? -1) + 1;

        $sub = $phase->subPhases()->create([
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? $next,
        ]);

        return response()->json([
            'sub_phase' => [
                'id' => $sub->id,
                'phase_id' => $sub->phase_id,
                'name' => $sub->name,
                'sort_order' => $sub->sort_order,
            ],
        ], 201);
    }

    public function update(Request $request, SubPhase $subPhase)
    {
        $this->authorizeProjectMember($request, $subPhase->phase->project);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'hidden_from_partner' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('hidden_from_partner', $data)) {
            abort_unless($request->user()?->canViewAllProjects(), 403, 'Réservé aux administrateurs.');
        }

        $subPhase->update($data);

        return response()->json([
            'sub_phase' => [
                'id' => $subPhase->id,
                'phase_id' => $subPhase->phase_id,
                'name' => $subPhase->name,
                'sort_order' => $subPhase->sort_order,
            ],
        ]);
    }

    public function destroy(Request $request, SubPhase $subPhase)
    {
        $this->authorizeProjectMember($request, $subPhase->phase->project);
        $subPhase->delete();

        return response()->json(['message' => 'Sous-phase supprimée']);
    }
}
