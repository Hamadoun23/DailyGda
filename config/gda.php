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

];
