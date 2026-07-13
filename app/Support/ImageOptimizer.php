<?php

namespace App\Support;

final class ImageOptimizer
{
    /**
     * Redimensionne/recompresse une image sur disque si elle dépasse les limites données.
     * Écrase le fichier en place uniquement si le résultat est effectivement plus léger.
     *
     * @return int Octets économisés (0 si rien n'a changé)
     */
    public static function optimizeInPlace(string $absolutePath, int $maxEdge = 2000, int $jpegQuality = 78): int
    {
        if (! is_file($absolutePath) || ! function_exists('imagecreatefromstring')) {
            return 0;
        }

        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        if (! in_array($ext, ['jpg', 'jpeg'], true)) {
            // PNG/WebP non touchés : ré-encoder en JPEG changerait le format réel du
            // fichier alors que l'extension (et le Content-Type servi) resteraient .png/.webp.
            return 0;
        }

        $originalSize = filesize($absolutePath);
        if ($originalSize === false) {
            return 0;
        }

        $blob = @file_get_contents($absolutePath);
        if ($blob === false || $blob === '') {
            return 0;
        }

        $src = @imagecreatefromstring($blob);
        if ($src === false) {
            return 0;
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
        }

        ob_start();
        imagejpeg($src, null, min(100, max(50, $jpegQuality)));
        imagedestroy($src);
        $jpeg = ob_get_clean();

        if ($jpeg === false || $jpeg === '' || strlen($jpeg) >= $originalSize) {
            return 0;
        }

        if (@file_put_contents($absolutePath, $jpeg) === false) {
            return 0;
        }
        clearstatcache(true, $absolutePath);

        return $originalSize - strlen($jpeg);
    }
}
