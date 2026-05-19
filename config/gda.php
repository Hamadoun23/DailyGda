<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Galerie photos — limites d'upload (Laravel)
    |--------------------------------------------------------------------------
    |
    | photo_max_upload_kb : règle de validation Laravel « max » (en kilo-octets).
    |   65536 Ko = 64 Mo — aligné sur upload_max_filesize PHP en production.
    |
    | photo_compress_above_bytes : en dessous, le fichier est stocké tel quel.
    |   Au-dessus, redimensionnement JPEG (très grandes images uniquement).
    |
    */

    'photo_max_upload_kb' => (int) env('GDA_PHOTO_MAX_KB', 65536),

    'photo_compress_above_bytes' => (int) env('GDA_PHOTO_COMPRESS_BYTES', 8 * 1024 * 1024),

    'photo_max_edge' => (int) env('GDA_PHOTO_MAX_EDGE', 4096),

    'photo_jpeg_target_bytes' => (int) env('GDA_PHOTO_JPEG_TARGET_BYTES', 5 * 1024 * 1024),

  /** Taille max chaîne base64 (caractères) pour l’upload JSON. */
    'photo_max_base64_chars' => (int) env('GDA_PHOTO_MAX_BASE64_CHARS', 28_000_000),

    /** Position par défaut si la géolocalisation est refusée (Bamako). */
    'weather_default_lat' => (float) env('GDA_WEATHER_LAT', 12.6392),
    'weather_default_lon' => (float) env('GDA_WEATHER_LON', -8.0029),

    /** Seuils d’alerte prévisions (aide à la décision chantier). */
    'weather_wind_alert_ms' => (float) env('GDA_WEATHER_WIND_ALERT_MS', 10),
    'weather_rain_pop_alert' => (float) env('GDA_WEATHER_RAIN_POP_ALERT', 0.55),
    'weather_rain_mm_alert' => (float) env('GDA_WEATHER_RAIN_MM_ALERT', 1.0),

];
