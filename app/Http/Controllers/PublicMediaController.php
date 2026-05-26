<?php

namespace App\Http\Controllers;

use App\Support\PhotoStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sert les fichiers du disque « public » sans dépendre du lien symbolique public/storage.
 * Indispensable sur certains hébergements où storage:link n'est pas disponible.
 */
class PublicMediaController extends Controller
{
    public function show(Request $request, string $path): BinaryFileResponse
    {
        $path = str_replace(['\\', '..'], ['/', ''], $path);
        $path = ltrim($path, '/');

        if ($path === '' || ! str_starts_with($path, 'photos/')) {
            abort(404);
        }

        if (! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $absolute = Storage::disk('public')->path($path);
        $mime = PhotoStorage::mimeForPath($absolute);

        return response()->file($absolute, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
