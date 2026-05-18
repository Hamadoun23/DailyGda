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
     * Stocke la photo. Compression uniquement si très lourde (> 8 Mo par défaut) ou très grande résolution.
     * Les fichiers type 58.jpg (~2,3 Mo, 6000×4000) sont conservés tels quels si sous le seuil.
     */
    public static function storeUploaded(UploadedFile $file, string $category): string
    {
        $directory = 'photos/'.$category;
        Storage::disk('public')->makeDirectory($directory);

        $compressAbove = (int) config('gda.photo_compress_above_bytes', 8 * 1024 * 1024);
        $maxEdge = (int) config('gda.photo_max_edge', 4096);
        $targetBytes = (int) config('gda.photo_jpeg_target_bytes', 5 * 1024 * 1024);

        $size = $file->getSize();
        if ($size <= $compressAbove) {
            return (string) $file->store($directory, 'public');
        }

        if (! function_exists('imagecreatefromstring')) {
            return (string) $file->store($directory, 'public');
        }

        $blob = @file_get_contents($file->getRealPath());
        if ($blob === false || $blob === '') {
            return (string) $file->store($directory, 'public');
        }

        $src = @imagecreatefromstring($blob);
        if ($src === false) {
            return (string) $file->store($directory, 'public');
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $needsResize = max($w, $h) > $maxEdge;

        if (! $needsResize && strlen($blob) <= $compressAbove) {
            imagedestroy($src);

            return (string) $file->store($directory, 'public');
        }

        if ($needsResize) {
            $scale = $maxEdge / max($w, $h);
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $dst = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);
            $src = $dst;
        }

        $name = Str::uuid()->toString().'.jpg';
        $relative = $directory.'/'.$name;
        $absolute = Storage::disk('public')->path($relative);

        $quality = 90;
        do {
            imagejpeg($src, $absolute, $quality);
            if (! is_file($absolute)) {
                break;
            }
            if (filesize($absolute) <= $targetBytes) {
                break;
            }
            $quality -= 5;
        } while ($quality >= 60);

        imagedestroy($src);

        if (! is_file($absolute) || filesize($absolute) === 0) {
            return (string) $file->store($directory, 'public');
        }

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
