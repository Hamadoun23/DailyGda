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
     * Enregistre le fichier tel quel.
     */
    public static function storeUploaded(UploadedFile $file, string $category): string
    {
        $directory = 'photos/'.$category;
        Storage::disk('public')->makeDirectory($directory);

        return (string) $file->store($directory, 'public');
    }

    /**
     * Contournement hébergement : upload JSON base64 quand $_FILES est vide (multipart bloqué).
     */
    public static function storeFromBase64(string $base64, string $filename, string $category): string
    {
        $base64 = preg_replace('#^data:image/[^;]+;base64,#i', '', trim($base64));
        $base64 = str_replace(["\r", "\n", ' '], '', $base64);

        $bytes = base64_decode($base64, true);
        if ($bytes === false || strlen($bytes) < 100) {
            throw new \InvalidArgumentException('Données image invalides (base64).');
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION) ?: 'jpg');
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $ext = 'jpg';
        }

        $directory = 'photos/'.$category;
        Storage::disk('public')->makeDirectory($directory);

        $relative = $directory.'/'.Str::uuid()->toString().'.'.$ext;
        Storage::disk('public')->put($relative, $bytes);

        return $relative;
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
