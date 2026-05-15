<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ActivityLogger
{
    /**
     * @param  array<string, mixed>|null  $properties
     */
    public static function log(
        string $action,
        string $description,
        ?User $user = null,
        ?int $projectId = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?array $properties = null,
        ?Request $request = null,
    ): void {
        $user ??= auth()->user();

        ActivityLog::query()->create([
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'project_id' => $projectId,
            'description' => Str::limit($description, 500, ''),
            'properties' => $properties,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent() ? Str::limit($request->userAgent(), 500, '') : null,
            'created_at' => now(),
        ]);
    }

    public static function login(User $user, Request $request, bool $viaApi = false): void
    {
        self::log(
            'login',
            'Connexion'.($viaApi ? ' (API)' : ' (web)').' — '.$user->name.' (@'.$user->username.')',
            $user,
            null,
            'user',
            $user->id,
            ['role' => $user->role],
            $request,
        );
    }

    public static function logout(User $user, Request $request, bool $viaApi = false): void
    {
        self::log(
            'logout',
            'Déconnexion'.($viaApi ? ' (API)' : ' (web)').' — '.$user->name,
            $user,
            null,
            'user',
            $user->id,
            null,
            $request,
        );
    }

    public static function loginFailed(Request $request, string $username): void
    {
        self::log(
            'login_failed',
            'Échec de connexion — identifiant « '.$username.' »',
            null,
            null,
            null,
            null,
            ['username' => $username],
            $request,
        );
    }

    /**
     * Journalise une requête API mutante réussie (POST, PUT, PATCH, DELETE).
     */
    public static function logApiMutation(Request $request, int $statusCode): void
    {
        $user = $request->user();
        if (! $user || $statusCode >= 400) {
            return;
        }

        $method = $request->method();
        if (! in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        $path = trim($request->path(), '/');
        if (in_array($path, ['api/login', 'api/logout', 'api/activity-logs'], true)) {
            return;
        }

        if (str_starts_with($path, 'api/activity-logs')) {
            return;
        }

        [$action, $description, $subjectType, $subjectId, $projectId] = self::describeApiRoute($request);

        if ($action === '') {
            return;
        }

        self::log(
            $action,
            $description,
            $user,
            $projectId,
            $subjectType,
            $subjectId,
            ['method' => $method, 'path' => $path],
            $request,
        );
    }

    /**
     * @return array{0: string, 1: string, 2: ?string, 3: ?int, 4: ?int}
     */
    private static function describeApiRoute(Request $request): array
    {
        $path = trim($request->path(), '/');
        $method = $request->method();
        $body = $request->all();
        $projectId = self::resolveProjectIdFromRequest($request);

        if ($path === 'api/projects' && $method === 'POST') {
            $name = is_string($body['name'] ?? null) ? $body['name'] : 'projet';

            return ['project.create', 'Création du projet « '.$name.' »', 'project', null, null];
        }

        if (preg_match('#^api/projects/(\d+)$#', $path, $m)) {
            $id = (int) $m[1];
            if ($method === 'PUT') {
                $name = is_string($body['name'] ?? null) ? $body['name'] : '#'.$id;

                return ['project.update', 'Modification du projet « '.$name.' »', 'project', $id, $id];
            }
            if ($method === 'DELETE') {
                return ['project.delete', 'Suppression du projet #'.$id, 'project', $id, $id];
            }
        }

        if (preg_match('#^api/projects/(\d+)/phases$#', $path, $m) && $method === 'POST') {
            $name = is_string($body['name'] ?? null) ? $body['name'] : 'phase';

            return ['phase.create', 'Ajout de la phase « '.$name.' »', 'phase', null, (int) $m[1]];
        }

        if (preg_match('#^api/phases/(\d+)$#', $path, $m)) {
            $id = (int) $m[1];
            if ($method === 'PUT') {
                $name = is_string($body['name'] ?? null) ? $body['name'] : '#'.$id;

                return ['phase.update', 'Modification de la phase « '.$name.' »', 'phase', $id, $projectId];
            }
            if ($method === 'DELETE') {
                return ['phase.delete', 'Suppression de la phase #'.$id, 'phase', $id, $projectId];
            }
        }

        if (preg_match('#^api/phases/(\d+)/sub-phases$#', $path, $m) && $method === 'POST') {
            $name = is_string($body['name'] ?? null) ? $body['name'] : 'sous-phase';

            return ['subphase.create', 'Ajout de la sous-phase « '.$name.' »', 'subphase', null, $projectId];
        }

        if (preg_match('#^api/sub-phases/(\d+)$#', $path, $m)) {
            $id = (int) $m[1];
            if ($method === 'PUT') {
                $name = is_string($body['name'] ?? null) ? $body['name'] : '#'.$id;

                return ['subphase.update', 'Modification de la sous-phase « '.$name.' »', 'subphase', $id, $projectId];
            }
            if ($method === 'DELETE') {
                return ['subphase.delete', 'Suppression de la sous-phase #'.$id, 'subphase', $id, $projectId];
            }
        }

        if ($path === 'api/tasks' && $method === 'POST') {
            $act = is_string($body['activity'] ?? null) ? $body['activity'] : 'activité';

            return ['task.create', 'Ajout de l’activité « '.$act.' »', 'task', null, $projectId];
        }

        if (preg_match('#^api/tasks/(\d+)$#', $path, $m)) {
            $id = (int) $m[1];
            if ($method === 'PUT') {
                $act = is_string($body['activity'] ?? null) ? $body['activity'] : 'tâche #'.$id;
                $prog = isset($body['progress']) ? ' — '.$body['progress'].' %' : '';

                return ['task.update', 'Modification de « '.$act.' »'.$prog, 'task', $id, $projectId];
            }
            if ($method === 'DELETE') {
                return ['task.delete', 'Suppression de l’activité #'.$id, 'task', $id, $projectId];
            }
        }

        if ($path === 'api/daily' && $method === 'POST') {
            $date = is_string($body['date'] ?? null) ? $body['date'] : '';
            $prog = isset($body['progress']) ? $body['progress'].' %' : '';

            return ['daily.update', 'Saisie du jour'.($date ? ' ('.$date.')' : '').' — avancement '.$prog, 'daily_update', null, $projectId];
        }

        if (preg_match('#^api/daily/(\d+)$#', $path, $m) && $method === 'PUT') {
            return ['daily.update', 'Mise à jour saisie du jour #'.$m[1], 'daily_update', (int) $m[1], $projectId];
        }

        if ($path === 'api/daily/batch' && $method === 'POST') {
            $count = is_array($body['updates'] ?? null) ? count($body['updates']) : 0;
            $date = is_string($body['date'] ?? null) ? $body['date'] : '';

            return ['daily.batch', 'Enregistrement groupé saisie du jour'.($date ? ' ('.$date.')' : '').' — '.$count.' tâche(s)', 'daily_update', null, $projectId];
        }

        if ($path === 'api/reports/generate' && $method === 'POST') {
            $date = is_string($body['date'] ?? null) ? $body['date'] : '';

            return ['report.generate', 'Génération du rapport PDF'.($date ? ' — '.$date : ''), 'report', null, $projectId];
        }

        if ($path === 'api/photos' && $method === 'POST') {
            $cat = is_string($body['category'] ?? null) ? $body['category'] : 'photo';

            return ['photo.upload', 'Ajout d’une photo ('.$cat.')', 'photo', null, $projectId];
        }

        if (preg_match('#^api/photos/(\d+)$#', $path, $m) && $method === 'DELETE') {
            return ['photo.delete', 'Suppression de la photo #'.$m[1], 'photo', (int) $m[1], $projectId];
        }

        return ['', '', null, null, $projectId];
    }

    private static function resolveProjectIdFromRequest(Request $request): ?int
    {
        $header = $request->header('X-Project-Id');
        if ($header !== null && $header !== '' && is_numeric($header)) {
            return (int) $header;
        }

        $routeProject = $request->route('project');
        if ($routeProject instanceof \App\Models\Project) {
            return $routeProject->id;
        }

        if (is_numeric($routeProject)) {
            return (int) $routeProject;
        }

        return null;
    }
}
