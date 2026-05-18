<?php

namespace App\Support;

use App\Models\Photo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class PhotoStorage
{
    public static function ensurePublicRoot(): void
    {
        Storage::disk('public')->makeDirectory('photos');
    }

    /**
     * Enregistre le fichier tel quel. Aucune recompression pour les photos < 8 Mo (ex. 58.jpg ~2,3 Mo).
     */
    public static function storeUploaded(UploadedFile $file, string $category): string
    {
        $directory = 'photos/'.$category;
        Storage::disk('public')->makeDirectory($directory);

        return (string) $file->store($directory, 'public');
    }

    public static function absolutePath(Photo $photo): ?string
    {
        if (! $photo->path) {
            return null;
        }

        $path = str_replace('\\', '/', $photo->path);

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->path($path);
    }

    public static function mimeForPath(string $absolutePath): string
    {
        return match (strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'jpg', 'jpeg' => 'image/jpeg',
            default => mime_content_type($absolutePath) ?: 'application/octet-stream',
        };
    }
}
