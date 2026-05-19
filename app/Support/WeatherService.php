<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class WeatherService
{
    /**
     * @return array{ok: bool, temp?: int|float, description?: string, icon?: string, city?: string, icon_url?: string}
     */
    public function currentForNav(?float $lat = null, ?float $lon = null, string $lang = 'fr'): array
    {
        $key = (string) config('services.openweather.key', '');
        if ($key === '') {
            return ['ok' => false, 'message' => 'Clé météo non configurée'];
        }

        $lat = $lat ?? (float) config('gda.weather_default_lat', 12.6392);
        $lon = $lon ?? (float) config('gda.weather_default_lon', -8.0029);
        $lat = max(-90.0, min(90.0, $lat));
        $lon = max(-180.0, min(180.0, $lon));

        $cacheKey = sprintf('gda_weather_nav_%s_%s_%s', round($lat, 2), round($lon, 2), $lang);

        return Cache::remember($cacheKey, now()->addMinutes(12), function () use ($lat, $lon, $key, $lang) {
            $response = Http::timeout(10)->get('https://api.openweathermap.org/data/2.5/weather', [
                'lat' => $lat,
                'lon' => $lon,
                'appid' => $key,
                'units' => 'metric',
                'lang' => $lang === 'en' ? 'en' : 'fr',
            ]);

            if (! $response->successful()) {
                return ['ok' => false, 'message' => 'Météo indisponible'];
            }

            $data = $response->json();
            $icon = (string) ($data['weather'][0]['icon'] ?? '01d');

            return [
                'ok' => true,
                'temp' => (int) round((float) ($data['main']['temp'] ?? 0)),
                'description' => ucfirst((string) ($data['weather'][0]['description'] ?? '')),
                'icon' => $icon,
                'icon_url' => 'https://openweathermap.org/img/wn/'.$icon.'@2x.png',
                'city' => (string) ($data['name'] ?? ''),
            ];
        });
    }
}
