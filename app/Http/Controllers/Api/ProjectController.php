<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesProject;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    use ResolvesProject;

    public function index(Request $request)
    {
        $user = $request->user();
        $q = Project::query()->orderBy('sort_order')->orderBy('id');

        if ($user && ! $user->canViewAllProjects()) {
            $q->whereHas('users', fn ($rel) => $rel->whereKey($user->id));
        }

        $projects = $q->get()->map(fn (Project $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'description' => $p->description,
            'client' => $p->client,
            'start_date' => $p->start_date?->toDateString(),
            'end_date' => $p->end_date?->toDateString(),
            'status' => $p->status,
            'overall_progress' => $p->overallProgress(),
            'tasks_count' => Task::query()->forProject($p->id)->count(),
        ]);

        return response()->json(['projects' => $projects]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'client' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'in:planifie,en_cours,termine,suspendu'],
        ]);

        $data['status'] = $data['status'] ?? 'planifie';
        $data['sort_order'] = (Project::query()->max('sort_order') ?? -1) + 1;

        $project = Project::create($data);

        $request->user()->projects()->syncWithoutDetaching([$project->id]);

        return response()->json([
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'client' => $project->client,
                'start_date' => $project->start_date?->toDateString(),
                'end_date' => $project->end_date?->toDateString(),
                'status' => $project->status,
                'overall_progress' => $project->overallProgress(),
                'tasks_count' => 0,
            ],
        ], 201);
    }

    public function show(Request $request)
    {
        $project = $this->resolveProject($request);

        return response()->json([
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'client' => $project->client,
                'start_date' => $project->start_date?->toDateString(),
                'end_date' => $project->end_date?->toDateString(),
                'status' => $project->status,
                'overall_progress' => $project->overallProgress(),
                'tasks_count' => Task::query()->forProject($project->id)->count(),
            ],
        ]);
    }

    public function update(Request $request, int $projectId)
    {
        $project = $this->findProjectOrFail($projectId);
        $this->authorizeProjectMember($request, $project);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'client' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', 'in:planifie,en_cours,termine,suspendu'],
        ]);

        $project->update($data);

        return response()->json([
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'client' => $project->client,
                'start_date' => $project->start_date?->toDateString(),
                'end_date' => $project->end_date?->toDateString(),
                'status' => $project->status,
                'overall_progress' => $project->overallProgress(),
                'tasks_count' => Task::query()->forProject($project->id)->count(),
            ],
        ]);
    }

    public function destroy(Request $request, int $projectId)
    {
        $project = $this->findProjectOrFail($projectId);
        $this->authorizeProjectMember($request, $project);
        $project->delete();

        return response()->json(['message' => 'Projet supprimé']);
    }

    protected function findProjectOrFail(int $projectId): Project
    {
        $project = Project::query()->find($projectId);

        if (! $project) {
            abort(404, 'Ce projet est introuvable ou a déjà été supprimé.');
        }

        return $project;
    }
}
