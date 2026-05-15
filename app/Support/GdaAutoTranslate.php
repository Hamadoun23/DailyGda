<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Traduction automatique FR → EN pour textes libres (commentaires d’annulation, etc.).
 * Utilise MyMemory (gratuit, sans clé) avec cache applicatif.
 */
final class GdaAutoTranslate
{
    public static function enabled(): bool
    {
        return filter_var(config('gda.auto_translate', true), FILTER_VALIDATE_BOOL);
    }

    public static function translate(string $text, string $from = 'fr', string $to = 'en'): string
    {
        $text = trim($text);
        if ($text === '' || $from === $to || ! self::enabled()) {
            return $text;
        }

        $cacheKey = 'gda_tr:'.hash('sha256', $from.'|'.$to.'|'.$text);

        return Cache::remember($cacheKey, now()->addDays(90), function () use ($text, $from, $to) {
            $translated = self::fetchFromMyMemory($text, $from, $to);

            return $translated ?? $text;
        });
    }

    private static function fetchFromMyMemory(string $text, string $from, string $to): ?string
    {
        if (mb_strlen($text) > 450) {
            return self::fetchChunked($text, $from, $to);
        }

        return self::requestMyMemory($text, $from, $to);
    }

    private static function fetchChunked(string $text, string $from, string $to): ?string
    {
        $parts = preg_split('/(\R+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return self::requestMyMemory($text, $from, $to);
        }

        $out = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (preg_match('/^\R+$/u', $part)) {
                $out .= $part;

                continue;
            }
            $chunk = self::requestMyMemory($part, $from, $to);
            if ($chunk === null) {
                return null;
            }
            $out .= $chunk;
        }

        return $out !== '' ? $out : null;
    }

    private static function requestMyMemory(string $text, string $from, string $to): ?string
    {
        try {
            $response = Http::timeout(12)
                ->acceptJson()
                ->get('https://api.mymemory.translated.net/get', [
                    'q' => $text,
                    'langpair' => $from.'|'.$to,
                ]);

            if (! $response->successful()) {
                return null;
            }

            $translated = $response->json('responseData.translatedText');
            if (! is_string($translated) || trim($translated) === '') {
                return null;
            }

            $translated = trim($translated);

            if (stripos($translated, 'MYMEMORY WARNING') !== false) {
                Log::warning('GdaAutoTranslate: quota MyMemory atteint');

                return null;
            }

            return $translated;
        } catch (\Throwable $e) {
            Log::warning('GdaAutoTranslate: échec MyMemory', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
