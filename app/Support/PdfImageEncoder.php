<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

final class PdfImageEncoder
{
    private const CACHE_DIR = 'report-photo-cache';

    /**
     * Image optimisée pour le PDF (redimensionnement raisonnable + JPEG haute qualité).
     * Le résultat (JPEG déjà redimensionné) est mis en cache sur disque par fichier source,
     * pour éviter de redécoder/redimensionner la même photo à chaque génération de rapport.
     */
    public static function photoDataUri(string $absolutePath, int $maxEdge = 1600, int $jpegQuality = 92): ?string
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        $cacheKey = self::cacheKey($absolutePath, $maxEdge, $jpegQuality);
        $cached = self::readCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        if (! function_exists('imagecreatefromstring')) {
            return self::rawDataUri($absolutePath);
        }

        $blob = @file_get_contents($absolutePath);
        if ($blob === false || $blob === '') {
            return null;
        }

        $src = @imagecreatefromstring($blob);
        if ($src === false) {
            return self::rawDataUri($absolutePath);
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $maxSide = max($w, $h);
        if ($maxSide > $maxEdge) {
            $scale = $maxEdge / $maxSide;
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $dst = imagecreatetruecolor($nw, $nh);
            imagealphablending($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);
            $src = $dst;
            $w = $nw;
            $h = $nh;
        }

        ob_start();
        imagejpeg($src, null, min(100, max(60, $jpegQuality)));
        imagedestroy($src);
        $jpeg = ob_get_clean();

        if ($jpeg === false || $jpeg === '') {
            return self::rawDataUri($absolutePath);
        }

        self::writeCache($cacheKey, $jpeg);

        return 'data:image/jpeg;base64,'.base64_encode($jpeg);
    }

    private static function cacheKey(string $absolutePath, int $maxEdge, int $jpegQuality): string
    {
        $stat = @stat($absolutePath);
        $fingerprint = implode('|', [
            $absolutePath,
            $stat['mtime'] ?? 0,
            $stat['size'] ?? 0,
            $maxEdge,
            $jpegQuality,
        ]);

        return sha1($fingerprint);
    }

    private static function readCache(string $cacheKey): ?string
    {
        $relative = self::CACHE_DIR.'/'.$cacheKey.'.jpg';
        if (! Storage::disk('local')->exists($relative)) {
            return null;
        }

        $jpeg = Storage::disk('local')->get($relative);
        if ($jpeg === null || $jpeg === '') {
            return null;
        }

        return 'data:image/jpeg;base64,'.base64_encode($jpeg);
    }

    private static function writeCache(string $cacheKey, string $jpeg): void
    {
        Storage::disk('local')->put(self::CACHE_DIR.'/'.$cacheKey.'.jpg', $jpeg);
    }

    private static function rawDataUri(string $absolutePath): ?string
    {
        $blob = @file_get_contents($absolutePath);
        if ($blob === false) {
            return null;
        }
        $mime = mime_content_type($absolutePath) ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode($blob);
    }
}
