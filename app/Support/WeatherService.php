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
        $key = trim((string) config('services.openweather.key', ''));
        if ($key === '') {
            return ['ok' => false, 'message' => 'Clé météo non configurée', 'code' => 'missing_api_key'];
        }

        $lat = $lat ?? (float) config('gda.weather_default_lat', 12.6392);
        $lon = $lon ?? (float) config('gda.weather_default_lon', -8.0029);
        $lat = max(-90.0, min(90.0, $lat));
        $lon = max(-180.0, min(180.0, $lon));

        $cacheKey = sprintf('gda_weather_nav_%s_%s_%s', round($lat, 2), round($lon, 2), $lang);

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && ($cached['ok'] ?? false)) {
            return $cached;
        }

        $result = $this->fetchFromOpenWeather($lat, $lon, $key, $lang);

        if ($result['ok'] ?? false) {
            Cache::put($cacheKey, $result, now()->addMinutes(12));
        }

        return $result;
    }

    /**
     * @return array{ok: bool, temp?: int|float, description?: string, icon?: string, city?: string, icon_url?: string, message?: string}
     */
    private function fetchFromOpenWeather(float $lat, float $lon, string $key, string $lang): array
    {
        try {
            $response = Http::timeout(10)->get('https://api.openweathermap.org/data/2.5/weather', [
                'lat' => $lat,
                'lon' => $lon,
                'appid' => $key,
                'units' => 'metric',
                'lang' => $lang === 'en' ? 'en' : 'fr',
            ]);
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'Météo indisponible'];
        }

        if (! $response->successful()) {
            $status = $response->status();

            return [
                'ok' => false,
                'message' => 'Météo indisponible',
                'code' => $status === 401 ? 'invalid_api_key' : 'api_error',
            ];
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
    }

    /**
     * Prévisions 5 j / pas 3 h — synthèse pour décisions chantier (pluie, vent).
     *
     * @return array<string, mixed>
     */
    public function forecastForDecisions(?float $lat = null, ?float $lon = null, string $lang = 'fr'): array
    {
        $key = trim((string) config('services.openweather.key', ''));
        if ($key === '') {
            return ['ok' => false, 'message' => 'Clé météo non configurée', 'code' => 'missing_api_key'];
        }

        $lat = $lat ?? (float) config('gda.weather_default_lat', 12.6392);
        $lon = $lon ?? (float) config('gda.weather_default_lon', -8.0029);
        $lat = max(-90.0, min(90.0, $lat));
        $lon = max(-180.0, min(180.0, $lon));

        $cacheKey = sprintf('gda_weather_forecast_%s_%s_%s', round($lat, 2), round($lon, 2), $lang);

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && ($cached['ok'] ?? false)) {
            return $cached;
        }

        $result = $this->fetchForecastFromOpenWeather($lat, $lon, $key, $lang);

        if ($result['ok'] ?? false) {
            Cache::put($cacheKey, $result, now()->addMinutes(30));
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchForecastFromOpenWeather(float $lat, float $lon, string $key, string $lang): array
    {
        try {
            $response = Http::timeout(12)->get('https://api.openweathermap.org/data/2.5/forecast', [
                'lat' => $lat,
                'lon' => $lon,
                'appid' => $key,
                'units' => 'metric',
                'lang' => $lang === 'en' ? 'en' : 'fr',
            ]);
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'Prévisions indisponibles'];
        }

        if (! $response->successful()) {
            return [
                'ok' => false,
                'message' => 'Prévisions indisponibles',
                'code' => $response->status() === 401 ? 'invalid_api_key' : 'api_error',
            ];
        }

        $data = $response->json();
        $list = $data['list'] ?? [];
        if (! is_array($list) || $list === []) {
            return ['ok' => false, 'message' => 'Prévisions indisponibles'];
        }

        $windThreshold = (float) config('gda.weather_wind_alert_ms', 10);
        $popThreshold = (float) config('gda.weather_rain_pop_alert', 0.55);
        $rainMmThreshold = (float) config('gda.weather_rain_mm_alert', 1.0);

        $daysMap = [];
        $alerts = [];

        foreach ($list as $item) {
            if (! is_array($item)) {
                continue;
            }

            $dtTxt = (string) ($item['dt_txt'] ?? '');
            $date = substr($dtTxt, 0, 10);
            if ($date === '') {
                continue;
            }

            $weather = is_array($item['weather'][0] ?? null) ? $item['weather'][0] : [];
            $main = is_array($item['main'] ?? null) ? $item['main'] : [];
            $wind = is_array($item['wind'] ?? null) ? $item['wind'] : [];
            $rain = is_array($item['rain'] ?? null) ? $item['rain'] : [];

            $temp = (float) ($main['temp'] ?? 0);
            $feels = (float) ($main['feels_like'] ?? $temp);
            $humidity = (int) ($main['humidity'] ?? 0);
            $windMs = (float) ($wind['speed'] ?? 0);
            $windGust = (float) ($wind['gust'] ?? 0);
            $pop = (float) ($item['pop'] ?? 0);
            $rainMm = (float) ($rain['3h'] ?? 0);
            $weatherMain = (string) ($weather['main'] ?? '');
            $description = ucfirst((string) ($weather['description'] ?? ''));
            $icon = (string) ($weather['icon'] ?? '01d');
            $time = substr($dtTxt, 11, 5);

            $slot = [
                'dt' => (int) ($item['dt'] ?? 0),
                'dt_txt' => $dtTxt,
                'time' => $time,
                'temp' => (int) round($temp),
                'feels_like' => (int) round($feels),
                'humidity' => $humidity,
                'description' => $description,
                'weather_main' => $weatherMain,
                'icon' => $icon,
                'wind_ms' => round($windMs, 1),
                'wind_kmh' => (int) round($windMs * 3.6),
                'wind_gust_kmh' => $windGust > 0 ? (int) round($windGust * 3.6) : null,
                'pop_percent' => (int) round($pop * 100),
                'rain_mm' => round($rainMm, 1),
            ];

            if (! isset($daysMap[$date])) {
                $daysMap[$date] = [
                    'date' => $date,
                    'slots' => [],
                    'temp_min' => $temp,
                    'temp_max' => $temp,
                    'wind_max_kmh' => (int) round($windMs * 3.6),
                    'pop_max' => (int) round($pop * 100),
                    'rain_slots' => 0,
                ];
            }

            $daysMap[$date]['slots'][] = $slot;
            $daysMap[$date]['temp_min'] = min($daysMap[$date]['temp_min'], $temp);
            $daysMap[$date]['temp_max'] = max($daysMap[$date]['temp_max'], $temp);
            $daysMap[$date]['wind_max_kmh'] = max($daysMap[$date]['wind_max_kmh'], (int) round($windMs * 3.6));
            $daysMap[$date]['pop_max'] = max($daysMap[$date]['pop_max'], (int) round($pop * 100));
            if ($pop >= $popThreshold || $rainMm >= $rainMmThreshold || strtolower($weatherMain) === 'rain') {
                $daysMap[$date]['rain_slots']++;
            }

            $alert = $this->buildForecastAlert(
                $slot,
                $windThreshold,
                $popThreshold,
                $rainMmThreshold,
                $lang,
            );
            if ($alert !== null) {
                $alerts[] = $alert;
            }
        }

        $days = [];
        foreach ($daysMap as $day) {
            $days[] = [
                'date' => $day['date'],
                'summary' => [
                    'temp_min' => (int) round($day['temp_min']),
                    'temp_max' => (int) round($day['temp_max']),
                    'wind_max_kmh' => $day['wind_max_kmh'],
                    'pop_max' => $day['pop_max'],
                    'rain_slots' => $day['rain_slots'],
                ],
                'slots' => $day['slots'],
            ];
        }

        usort($days, fn ($a, $b) => strcmp($a['date'], $b['date']));

        $city = (string) ($data['city']['name'] ?? '');
        $country = (string) ($data['city']['country'] ?? '');

        $allSlots = [];
        foreach ($days as $day) {
            foreach ($day['slots'] as $slot) {
                $allSlots[] = $slot;
            }
        }

        $current = $this->fetchFromOpenWeather($lat, $lon, $key, $lang);
        $stats = $this->buildForecastStats($allSlots, count($alerts));

        return [
            'ok' => true,
            'city' => $city,
            'country' => $country,
            'lat' => $lat,
            'lon' => $lon,
            'fetched_at' => now()->toIso8601String(),
            'current' => ($current['ok'] ?? false) ? $current : null,
            'stats' => $stats,
            'thresholds' => [
                'wind_ms' => $windThreshold,
                'wind_kmh' => (int) round($windThreshold * 3.6),
                'rain_pop_percent' => (int) round($popThreshold * 100),
                'rain_mm' => $rainMmThreshold,
            ],
            'alerts' => array_slice($alerts, 0, 24),
            'days' => $days,
        ];
    }

    /**
     * Recherche de localités (géocodage direct OpenWeather).
     *
     * @return array{ok: bool, results?: list<array<string, mixed>>, message?: string}
     */
    public function searchLocations(string $query, int $limit = 8): array
    {
        $key = trim((string) config('services.openweather.key', ''));
        if ($key === '') {
            return ['ok' => false, 'message' => 'Clé météo non configurée', 'code' => 'missing_api_key'];
        }

        $q = trim($query);
        if (mb_strlen($q) < 2) {
            return ['ok' => true, 'results' => []];
        }

        $limit = max(1, min(10, $limit));
        $cacheKey = 'gda_geo_'.md5(mb_strtolower($q).'_'.$limit);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::timeout(8)->get('https://api.openweathermap.org/geo/1.0/direct', [
                'q' => $q,
                'limit' => $limit,
                'appid' => $key,
            ]);
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'Recherche indisponible'];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'message' => 'Recherche indisponible'];
        }

        $rows = $response->json();
        if (! is_array($rows)) {
            return ['ok' => true, 'results' => []];
        }

        $results = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = (string) ($row['name'] ?? '');
            $country = (string) ($row['country'] ?? '');
            $state = (string) ($row['state'] ?? '');
            $lat = (float) ($row['lat'] ?? 0);
            $lon = (float) ($row['lon'] ?? 0);
            if ($name === '') {
                continue;
            }
            $parts = array_filter([$name, $state, $country]);
            $results[] = [
                'name' => $name,
                'state' => $state,
                'country' => $country,
                'lat' => $lat,
                'lon' => $lon,
                'label' => implode(', ', $parts),
            ];
        }

        $payload = ['ok' => true, 'results' => $results];
        Cache::put($cacheKey, $payload, now()->addHours(24));

        return $payload;
    }

    /**
     * @param  list<array<string, mixed>>  $slots
     * @return array<string, int|float>
     */
    private function buildForecastStats(array $slots, int $alertCount): array
    {
        if ($slots === []) {
            return [
                'temp_min' => 0,
                'temp_max' => 0,
                'wind_max_kmh' => 0,
                'pop_max' => 0,
                'humidity_avg' => 0,
                'rain_mm_total' => 0,
                'alert_count' => $alertCount,
                'slots_count' => 0,
            ];
        }

        $tempMin = PHP_INT_MAX;
        $tempMax = PHP_INT_MIN;
        $windMax = 0;
        $popMax = 0;
        $humiditySum = 0;
        $rainTotal = 0.0;

        foreach ($slots as $slot) {
            $tempMin = min($tempMin, (int) ($slot['temp'] ?? 0));
            $tempMax = max($tempMax, (int) ($slot['temp'] ?? 0));
            $windMax = max($windMax, (int) ($slot['wind_kmh'] ?? 0));
            $popMax = max($popMax, (int) ($slot['pop_percent'] ?? 0));
            $humiditySum += (int) ($slot['humidity'] ?? 0);
            $rainTotal += (float) ($slot['rain_mm'] ?? 0);
        }

        return [
            'temp_min' => $tempMin === PHP_INT_MAX ? 0 : $tempMin,
            'temp_max' => $tempMax === PHP_INT_MIN ? 0 : $tempMax,
            'wind_max_kmh' => $windMax,
            'pop_max' => $popMax,
            'humidity_avg' => (int) round($humiditySum / count($slots)),
            'rain_mm_total' => round($rainTotal, 1),
            'alert_count' => $alertCount,
            'slots_count' => count($slots),
        ];
    }

    /**
     * @param  array<string, mixed>  $slot
     * @return array<string, mixed>|null
     */
    private function buildForecastAlert(
        array $slot,
        float $windThreshold,
        float $popThreshold,
        float $rainMmThreshold,
        string $lang,
    ): ?array {
        $types = [];
        $severity = 'info';

        $weatherMain = strtolower((string) ($slot['weather_main'] ?? ''));
        $pop = ((int) ($slot['pop_percent'] ?? 0)) / 100;
        $rainMm = (float) ($slot['rain_mm'] ?? 0);
        $windMs = (float) ($slot['wind_ms'] ?? 0);

        if ($weatherMain === 'thunderstorm') {
            $types[] = 'storm';
            $severity = 'high';
        } elseif ($pop >= $popThreshold || $rainMm >= $rainMmThreshold || $weatherMain === 'rain' || $weatherMain === 'drizzle') {
            $types[] = 'rain';
            $severity = $rainMm >= 3 || $pop >= 0.75 ? 'high' : 'medium';
        }

        if ($windMs >= $windThreshold) {
            $types[] = 'wind';
            $severity = $windMs >= $windThreshold * 1.35 ? 'high' : ($severity === 'high' ? 'high' : 'medium');
        }

        if ($types === []) {
            return null;
        }

        $en = $lang === 'en';
        $time = (string) ($slot['time'] ?? '');
        $desc = (string) ($slot['description'] ?? '');

        $parts = [];
        if (in_array('storm', $types, true)) {
            $parts[] = $en ? 'Thunderstorm risk' : 'Risque d’orage';
        }
        if (in_array('rain', $types, true)) {
            $parts[] = $en
                ? sprintf('Rain (%d%% prob., %.1f mm/3h)', (int) $slot['pop_percent'], $rainMm)
                : sprintf('Pluie (%d %% prob., %.1f mm/3h)', (int) $slot['pop_percent'], $rainMm);
        }
        if (in_array('wind', $types, true)) {
            $parts[] = $en
                ? sprintf('Strong wind (%d km/h)', (int) $slot['wind_kmh'])
                : sprintf('Vent fort (%d km/h)', (int) $slot['wind_kmh']);
        }

        return [
            'types' => $types,
            'severity' => $severity,
            'dt_txt' => (string) ($slot['dt_txt'] ?? ''),
            'time' => $time,
            'date' => substr((string) ($slot['dt_txt'] ?? ''), 0, 10),
            'message' => implode(' — ', $parts).' — '.$desc,
        ];
    }
}
