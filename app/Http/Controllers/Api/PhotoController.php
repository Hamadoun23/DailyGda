<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesProject;
use App\Http\Controllers\Controller;
use App\Models\Photo;
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
        $project = $this->resolveProject($request);
        abort_unless((int) $photo->project_id === (int) $project->id, 404);

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
        $maxKb = (int) config('gda.photo_max_upload_kb', 65536);

        $data = $request->validate([
            'category' => ['required', 'in:avant,pendant,apres,securite,qualite'],
            'photo' => ['nullable', 'file', 'max:'.$maxKb],
            'photo_base64' => ['nullable', 'string', 'max:'.((int) config('gda.photo_max_base64_chars', 28_000_000))],
            'photo_name' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:500'],
            'taken_at' => ['nullable', 'date'],
        ], [
            'photo.max' => 'La photo dépasse '.(int) floor($maxKb / 1024).' Mo.',
        ]);

        PhotoStorage::ensurePublicRoot();

        $originalName = 'photo.jpg';
        $path = null;

        try {
            if ($request->hasFile('photo')) {
                $file = $request->file('photo');
                if (! $file->isValid()) {
                    throw ValidationException::withMessages([
                        'photo' => [$file->getErrorMessage() ?: $this->uploadIniHint()],
                    ]);
                }
                $ext = $this->resolveExtension($file);
                if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                    throw ValidationException::withMessages([
                        'photo' => ['Format non supporté. Utilisez JPG, PNG, GIF ou WebP.'],
                    ]);
                }
                $path = PhotoStorage::storeUploaded($file, $data['category']);
                $originalName = $file->getClientOriginalName() ?: $originalName;
            } elseif ($request->filled('photo_base64')) {
                $originalName = (string) ($data['photo_name'] ?? 'photo.jpg');
                $path = PhotoStorage::storeFromBase64(
                    (string) $request->input('photo_base64'),
                    $originalName,
                    $data['category'],
                );
            } else {
                throw ValidationException::withMessages([
                    'photo' => [$this->missingFileMessage($request)],
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
            'category' => $data['category'],
            'path' => $path,
            'original_name' => $originalName,
            'caption' => $data['caption'] ?? null,
            'taken_at' => $data['taken_at'] ?? null,
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

    private function uploadIniHint(): string
    {
        return 'Upload PHP refusé (upload_max_filesize='.ini_get('upload_max_filesize')
            .', post_max_size='.ini_get('post_max_size').').';
    }

    private function missingFileMessage(Request $request): string
    {
        $phpUpload = ini_get('upload_max_filesize');
        $phpPost = ini_get('post_max_size');
        $filesKeys = array_keys($_FILES);
        $photoErr = $_FILES['photo']['error'] ?? null;

        $hint = 'PHP web : upload_max_filesize='.$phpUpload.', post_max_size='.$phpPost;
        if ($photoErr !== null) {
            $hint .= ', erreur fichier='.$photoErr;
        }
        if ($filesKeys === []) {
            $hint .= ' — $_FILES vide (multipart souvent bloqué sur cet hébergeur ; utilisez l’envoi base64).';
        }

        if ($request->filled('category') && ! $request->hasFile('photo') && ! $request->filled('photo_base64')) {
            return 'Aucune image reçue. '.$hint;
        }

        return 'Aucun fichier reçu. '.$hint;
    }
}
