<?php

namespace App\Support;

use App\Models\Photo;
use Illuminate\Support\Facades\Storage;

final class PhotoStorage
{
    public static function ensurePublicRoot(): void
    {
        Storage::disk('public')->makeDirectory('photos');
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
