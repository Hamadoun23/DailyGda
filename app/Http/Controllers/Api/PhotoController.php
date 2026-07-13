<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesProject;
use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Support\ImageOptimizer;
use App\Support\PhotoStorage;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PhotoController extends Controller
{
    use ResolvesProject;

    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function index(Request $request)
    {
        $project = $this->resolveProject($request);

        $query = Photo::query()
            ->where('project_id', $project->id)
            ->with('user')
            ->orderByDesc('created_at');

        if ($request->filled('category')) {
            $request->validate(['category' => 'in:avant,pendant,apres,securite,qualite']);
            $query->where('category', $request->query('category'));
        }

        $photos = $query->get()->map(fn (Photo $p) => [
            'id' => $p->id,
            'url' => $p->url,
            'path' => str_replace('\\', '/', (string) $p->path),
            'category' => $p->category,
            'caption' => $p->caption,
            'taken_at' => $p->taken_at?->toDateString(),
            'original_name' => $p->original_name,
            'user_name' => $p->user->name,
            'created_at' => $p->created_at->toIso8601String(),
        ]);

        return response()->json(['photos' => $photos]);
    }

    public function file(Request $request, Photo $photo): BinaryFileResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $photo->loadMissing('project');
        $project = $photo->project;
        abort_unless($project, 404);

        if (! $user->canViewAllProjects() && ! $user->projects()->whereKey($project->id)->exists()) {
            abort(403, 'Accès non autorisé à ce projet.');
        }

        $absolute = PhotoStorage::absolutePath($photo);
        abort_unless($absolute !== null && is_file($absolute), 404);

        return response()->file($absolute, [
            'Content-Type' => PhotoStorage::mimeForPath($absolute),
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function store(Request $request)
    {
        $project = $this->resolveProject($request);

        $base = $request->validate([
            'category' => ['required', 'in:avant,pendant,apres,securite,qualite'],
            'caption' => ['nullable', 'string', 'max:500'],
            'taken_at' => ['nullable', 'date'],
        ]);

        PhotoStorage::ensurePublicRoot();

        $originalName = 'photo.jpg';
        $path = null;

        try {
            // Priorité : base64 JSON (ne passe pas par $_FILES / règle « file »)
            if ($request->filled('photo_base64')) {
                $request->validate([
                    'photo_base64' => ['required', 'string'],
                    'photo_name' => ['nullable', 'string', 'max:255'],
                ]);

                $originalName = (string) ($request->input('photo_name') ?: 'photo.jpg');
                $path = PhotoStorage::storeFromBase64(
                    (string) $request->input('photo_base64'),
                    $originalName,
                    $base['category'],
                );
            } elseif ($request->hasFile('photo')) {
                $file = $request->file('photo');
                if (! $file->isValid()) {
                    throw ValidationException::withMessages([
                        'photo' => [$file->getErrorMessage() ?: 'Fichier refusé par PHP (upload_max_filesize).'],
                    ]);
                }
                $ext = $this->resolveExtension($file);
                if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                    throw ValidationException::withMessages([
                        'photo' => ['Format non supporté. Utilisez JPG, PNG, GIF ou WebP.'],
                    ]);
                }
                $path = PhotoStorage::storeUploaded($file, $base['category']);
                $originalName = $file->getClientOriginalName() ?: $originalName;
            } else {
                throw ValidationException::withMessages([
                    'photo' => ['Aucune image reçue. Rechargez la page (Ctrl+F5) puis réessayez.'],
                ]);
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['photo' => [$e->getMessage()]]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Erreur lors de l\'enregistrement : '.$e->getMessage(),
            ], 500);
        }

        if (! $path || ! Storage::disk('public')->exists($path)) {
            return response()->json([
                'message' => 'Impossible d\'enregistrer le fichier. Vérifiez les droits sur storage/app/public.',
            ], 500);
        }

        $photo = Photo::create([
            'project_id' => $project->id,
            'user_id' => $request->user()->id,
            'category' => $base['category'],
            'path' => $path,
            'original_name' => $originalName,
            'caption' => $base['caption'] ?? null,
            'taken_at' => $base['taken_at'] ?? null,
            'file_size' => Storage::disk('public')->size($path),
        ]);

        return response()->json([
            'id' => $photo->id,
            'url' => $photo->url,
            'path' => str_replace('\\', '/', (string) $photo->path),
            'category' => $photo->category,
            'taken_at' => $photo->taken_at?->toDateString(),
        ], 201);
    }

    /**
     * Compresse rétroactivement les photos déjà stockées (admin, déclenché depuis le navigateur —
     * utile en hébergement mutualisé sans accès shell). Traite par lots pour éviter les timeouts PHP.
     */
    public function optimizeExisting(Request $request)
    {
        $limit = max(1, min((int) $request->query('limit', 200), 500));
        $minBytes = 400 * 1024;

        $query = Photo::query()
            ->whereNotNull('path')
            ->where(function ($q) use ($minBytes) {
                $q->where('file_size', '>', $minBytes)->orWhereNull('file_size');
            })
            ->orderBy('id');

        $photos = $query->limit($limit)->get();

        $optimized = 0;
        $savedBytes = 0;
        foreach ($photos as $photo) {
            $absolute = Storage::disk('public')->path($photo->path);
            $saved = ImageOptimizer::optimizeInPlace($absolute);
            if ($saved > 0) {
                $optimized++;
                $savedBytes += $saved;
                $photo->update(['file_size' => Storage::disk('public')->size($photo->path)]);
            } else {
                // Pas de gain possible (déjà compressée, PNG/WebP, etc.) : ne plus la re-scanner.
                $photo->update(['file_size' => Storage::disk('public')->size($photo->path)]);
            }
        }

        return response()->json([
            'scanned' => $photos->count(),
            'optimized' => $optimized,
            'saved_kb' => round($savedBytes / 1024),
            'has_more' => $photos->count() === $limit,
        ]);
    }

    public function destroy(Request $request, Photo $photo)
    {
        $project = $this->resolveProject($request);
        abort_unless((int) $photo->project_id === (int) $project->id, 404);

        if ($photo->path && Storage::disk('public')->exists($photo->path)) {
            Storage::disk('public')->delete($photo->path);
        }
        $photo->delete();

        return response()->json(['message' => 'Photo supprimée']);
    }

    private function resolveExtension(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: '');
        if ($ext !== '') {
            return $ext;
        }

        return match ($file->getMimeType()) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => '',
        };
    }
}
