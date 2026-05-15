<?php

namespace App\Support;

use App\Models\DailyUpdate;
use App\Models\Task;
use App\Models\TaskProgressNote;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class TaskProgressRecorder
{
    /**
     * @throws ValidationException
     */
    public static function assertAdminProgressNote(
        User $user,
        int $previousProgress,
        int $newProgress,
        string $status,
        ?string $progressNote,
    ): void {
        if (! $user->isAdmin()) {
            return;
        }

        if ($status === 'annule') {
            return;
        }

        if ($newProgress <= $previousProgress) {
            return;
        }

        if (trim((string) $progressNote) === '') {
            throw ValidationException::withMessages([
                'progress_note' => 'Une description est obligatoire pour justifier l’avancement.',
            ]);
        }
    }

    public static function recordIfAdvanced(
        User $user,
        Task $task,
        DailyUpdate $daily,
        int $previousProgress,
        ?string $progressNote,
    ): void {
        if (! $user->isAdmin()) {
            return;
        }

        if ($daily->status === 'annule' || $daily->progress <= $previousProgress) {
            return;
        }

        $body = trim((string) $progressNote);
        if ($body === '') {
            return;
        }

        TaskProgressNote::query()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'daily_update_id' => $daily->id,
            'progress' => $daily->progress,
            'previous_progress' => $previousProgress,
            'body' => $body,
        ]);
    }

    public static function previousProgressForTask(Task $task, ?DailyUpdate $existingForDate = null): int
    {
        if ($existingForDate) {
            return (int) $existingForDate->progress;
        }

        return (int) ($task->latestDailyUpdate?->progress ?? 0);
    }
}
