<?php

namespace App\Support;

final class PdfImageEncoder
{
    /**
     * Image optimisée pour DomPDF : redimensionnement raisonnable + JPEG haute qualité.
     */
    public static function photoDataUri(string $absolutePath, int $maxEdge = 1600, int $jpegQuality = 92): ?string
    {
        if (! is_file($absolutePath)) {
            return null;
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

        return 'data:image/jpeg;base64,'.base64_encode($jpeg);
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
