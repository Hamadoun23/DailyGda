<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesProject;
use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Support\PhotoStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PhotoController extends Controller
{
    use ResolvesProject;

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

        $data = $request->validate([
            'photo' => ['required', 'file', 'image', 'max:20480'],
            'category' => ['required', 'in:avant,pendant,apres,securite,qualite'],
            'caption' => ['nullable', 'string', 'max:500'],
            'taken_at' => ['nullable', 'date'],
        ]);

        PhotoStorage::ensurePublicRoot();
        Storage::disk('public')->makeDirectory('photos/'.$data['category']);

        $file = $request->file('photo');
        $path = $file->store('photos/'.$data['category'], 'public');

        if (! $path || ! Storage::disk('public')->exists($path)) {
            return response()->json([
                'message' => 'Impossible d\'enregistrer le fichier sur le serveur. Vérifiez les droits du dossier storage.',
            ], 500);
        }

        $photo = Photo::create([
            'project_id' => $project->id,
            'user_id' => $request->user()->id,
            'category' => $data['category'],
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'caption' => $data['caption'] ?? null,
            'taken_at' => $data['taken_at'] ?? null,
            'file_size' => $file->getSize(),
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
}
