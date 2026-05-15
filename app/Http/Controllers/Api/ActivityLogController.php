<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        if (
            ($user = $request->user())
            && (int) $request->query('page', 1) === 1
            && ! $request->filled('q')
            && ! $request->filled('action')
            && ! $request->filled('from')
            && ! $request->filled('to')
        ) {
            ActivityLogger::log(
                'logs.view',
                'Consultation du journal d’activité',
                $user,
                null,
                null,
                null,
                null,
                $request,
            );
        }

        $perPage = min(100, max(10, (int) $request->query('per_page', 50)));
        $page = max(1, (int) $request->query('page', 1));

        $query = ActivityLog::query()
            ->with(['user:id,name,username,role', 'project:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->query('user_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->query('action'));
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', (int) $request->query('project_id'));
        }

        if ($request->filled('q')) {
            $q = '%'.$request->query('q').'%';
            $query->where('description', 'like', $q);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'logs' => collect($paginator->items())->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'subject_type' => $log->subject_type,
                'subject_id' => $log->subject_id,
                'project_id' => $log->project_id,
                'project_name' => $log->project?->name,
                'user_id' => $log->user_id,
                'user_name' => $log->user?->name,
                'user_username' => $log->user?->username,
                'user_role' => $log->user?->role,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toIso8601String(),
                'created_at_fmt' => $log->created_at?->format('d/m/Y H:i'),
            ])->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'filters' => [
                'actions' => ActivityLog::query()
                    ->select('action')
                    ->distinct()
                    ->orderBy('action')
                    ->pluck('action'),
            ],
        ]);
    }
}
