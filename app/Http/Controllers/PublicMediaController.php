<?php

namespace App\Http\Controllers;

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

        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $absolute = Storage::disk('public')->path($path);
        $mime = match (strtolower(pathinfo($absolute, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'jpg', 'jpeg' => 'image/jpeg',
            default => mime_content_type($absolute) ?: 'application/octet-stream',
        };

        return response()->file($absolute, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
