<?php

namespace App\Observers;

use App\Models\DailyUpdate;
use App\Models\Phase;
use App\Models\Photo;
use App\Models\Project;
use App\Models\Report;
use App\Models\SubPhase;
use App\Models\Task;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

class RecordsModelActivity
{
    public function created(Model $model): void
    {
        $this->record($model, 'create');
    }

    public function updated(Model $model): void
    {
        if ($model->wasChanged()) {
            $this->record($model, 'update');
        }
    }

    /**
     * Projet : journaliser avant suppression (la ligne n’existe plus après « deleted »).
     */
    public function deleting(Model $model): void
    {
        if ($model instanceof Project) {
            $this->record($model, 'delete');
        }
    }

    public function deleted(Model $model): void
    {
        if ($model instanceof Project) {
            return;
        }

        $this->record($model, 'delete');
    }

    private function record(Model $model, string $verb): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        [$action, $description, $projectId, $subjectType, $subjectId] = $this->describe($model, $verb);
        if ($action === '') {
            return;
        }

        ActivityLogger::log($action, $description, $user, $projectId, $subjectType, $subjectId);
    }

    /**
     * @return array{0: string, 1: string, 2: ?int, 3: ?string, 4: ?int}
     */
    private function describe(Model $model, string $verb): array
    {
        $projectId = $this->resolveProjectId($model);

        if ($model instanceof Project) {
            $name = $model->name ?: '#'.$model->id;

            return match ($verb) {
                'create' => ['project.create', 'Création du projet « '.$name.' »', $projectId, 'project', $model->id],
                'update' => ['project.update', 'Modification du projet « '.$name.' »', $projectId, 'project', $model->id],
                'delete' => ['project.delete', 'Suppression du projet « '.$name.' »', $projectId, 'project', $model->id],
                default => ['', '', null, null, null],
            };
        }

        if ($model instanceof Phase) {
            $name = $model->name ?: '#'.$model->id;

            return match ($verb) {
                'create' => ['phase.create', 'Ajout de la phase « '.$name.' »', $projectId, 'phase', $model->id],
                'update' => ['phase.update', 'Modification de la phase « '.$name.' »', $projectId, 'phase', $model->id],
                'delete' => ['phase.delete', 'Suppression de la phase « '.$name.' »', $projectId, 'phase', $model->id],
                default => ['', '', null, null, null],
            };
        }

        if ($model instanceof SubPhase) {
            $name = $model->name ?: '#'.$model->id;

            return match ($verb) {
                'create' => ['subphase.create', 'Ajout de la sous-phase « '.$name.' »', $projectId, 'subphase', $model->id],
                'update' => ['subphase.update', 'Modification de la sous-phase « '.$name.' »', $projectId, 'subphase', $model->id],
                'delete' => ['subphase.delete', 'Suppression de la sous-phase « '.$name.' »', $projectId, 'subphase', $model->id],
                default => ['', '', null, null, null],
            };
        }

        if ($model instanceof Task) {
            $name = $model->activity ?: '#'.$model->id;
            return match ($verb) {
                'create' => ['task.create', 'Ajout de l’activité « '.$name.' »', $projectId, 'task', $model->id],
                'update' => ['task.update', 'Modification de « '.$name.' »', $projectId, 'task', $model->id],
                'delete' => ['task.delete', 'Suppression de l’activité « '.$name.' »', $projectId, 'task', $model->id],
                default => ['', '', null, null, null],
            };
        }

        if ($model instanceof DailyUpdate) {
            $date = $model->report_date?->format('d/m/Y') ?? '';
            $prog = $model->progress !== null ? $model->progress.' %' : '';

            return match ($verb) {
                'create' => ['daily.update', 'Saisie du jour ('.$date.') — avancement '.$prog, $projectId, 'daily_update', $model->id],
                'update' => ['daily.update', 'Mise à jour saisie du jour ('.$date.') — '.$prog, $projectId, 'daily_update', $model->id],
                'delete' => ['daily.update', 'Suppression saisie du jour #'.$model->id, $projectId, 'daily_update', $model->id],
                default => ['', '', null, null, null],
            };
        }

        if ($model instanceof Report) {
            $date = $model->report_date?->format('d/m/Y') ?? '';

            return match ($verb) {
                'create' => ['report.generate', 'Génération du rapport PDF — '.$date, $projectId, 'report', $model->id],
                'update' => ['report.generate', 'Modification du rapport — '.$date, $projectId, 'report', $model->id],
                'delete' => ['report.generate', 'Suppression du rapport #'.$model->id, $projectId, 'report', $model->id],
                default => ['', '', null, null, null],
            };
        }

        if ($model instanceof Photo) {
            $cat = $model->category ?: 'photo';

            return match ($verb) {
                'create' => ['photo.upload', 'Ajout d’une photo ('.$cat.')', $projectId, 'photo', $model->id],
                'update' => ['photo.upload', 'Modification de la photo #'.$model->id, $projectId, 'photo', $model->id],
                'delete' => ['photo.delete', 'Suppression de la photo #'.$model->id, $projectId, 'photo', $model->id],
                default => ['', '', null, null, null],
            };
        }

        return ['', '', null, null, null];
    }

    private function resolveProjectId(Model $model): ?int
    {
        if ($model instanceof Project) {
            return $model->id;
        }

        if ($model instanceof Phase) {
            return $model->project_id;
        }

        if ($model instanceof SubPhase) {
            if ($model->relationLoaded('phase')) {
                return $model->phase?->project_id;
            }

            return Phase::query()->whereKey($model->phase_id)->value('project_id');
        }

        if ($model instanceof Task) {
            $model->loadMissing('subPhase.phase');

            return $model->subPhase?->phase?->project_id;
        }

        if ($model instanceof DailyUpdate) {
            $model->loadMissing('task.subPhase.phase');

            return $model->task?->subPhase?->phase?->project_id;
        }

        if ($model instanceof Report || $model instanceof Photo) {
            return $model->project_id;
        }

        return null;
    }
}
