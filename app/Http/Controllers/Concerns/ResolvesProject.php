<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Project;
use Illuminate\Http\Request;

trait ResolvesProject
{
    protected function resolveProject(Request $request): Project
    {
        $user = $request->user();
        $raw = $request->header('X-Project-Id') ?? $request->query('project_id');

        if ($raw !== null && $raw !== '') {
            $project = Project::query()->findOrFail((int) $raw);
        } else {
            $project = Project::query()->orderBy('id')->firstOrFail();
        }

        if ($user && ! $user->isDirection() && ! $user->projects()->whereKey($project->id)->exists()) {
            abort(403, 'Accès non autorisé à ce projet.');
        }

        return $project;
    }

    protected function authorizeProjectMember(Request $request, Project $project): void
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        if ($user->isDirection()) {
            return;
        }

        abort_unless($user->projects()->whereKey($project->id)->exists(), 403, 'Accès non autorisé à ce projet.');
    }
}
