<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

final class ReportChartStorage
{
    private const KEYS = ['status', 'phase', 'sub', 'act'];

    public static function diskDir(int $reportId): string
    {
        return 'report-charts/'.$reportId;
    }

    /**
     * @param  array<string, string>  $images  data:image/...;base64,...
     * @return array<string, string>  chemins relatifs enregistrés
     */
    public static function persist(int $reportId, array $images): array
    {
        $dir = self::diskDir($reportId);
        Storage::disk('local')->makeDirectory($dir);
        $saved = [];

        foreach (self::KEYS as $key) {
            $value = $images[$key] ?? null;
            if (! is_string($value) || $value === '') {
                continue;
            }
            $relative = self::saveDataUri($reportId, $key, $value);
            if ($relative !== null) {
                $saved[$key] = $relative;
            }
        }

        return $saved;
    }

    /**
     * @return array<string, string>  data URIs prêts pour DomPDF
     */
    public static function loadForPdf(int $reportId): array
    {
        $out = [];
        foreach (self::KEYS as $key) {
            foreach (['png', 'jpg', 'jpeg'] as $ext) {
                $relative = self::diskDir($reportId).'/'.$key.'.'.$ext;
                if (! Storage::disk('local')->exists($relative)) {
                    continue;
                }
                $uri = self::fileToDataUri(Storage::disk('local')->path($relative));
                if ($uri !== null) {
                    $out[$key] = $uri;
                }
                break;
            }
        }

        return $out;
    }

    public static function saveDataUri(int $reportId, string $key, string $dataUri): ?string
    {
        if (! preg_match('#^data:image/(png|jpeg|jpg);base64,(.+)$#is', $dataUri, $m)) {
            return null;
        }

        $bytes = base64_decode($m[2], true);
        if ($bytes === false || strlen($bytes) < 500) {
            return null;
        }

        $ext = stripos($m[1], 'png') !== false ? 'png' : 'jpg';
        $relative = self::diskDir($reportId).'/'.$key.'.'.$ext;
        Storage::disk('local')->put($relative, $bytes);

        return $relative;
    }

    private static function fileToDataUri(string $absolutePath): ?string
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        $blob = @file_get_contents($absolutePath);
        if ($blob === false || $blob === '') {
            return null;
        }

        $mime = match (strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => mime_content_type($absolutePath) ?: 'image/png',
        };

        return 'data:'.$mime.';base64,'.base64_encode($blob);
    }
}
