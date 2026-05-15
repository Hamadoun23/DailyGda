<?php

namespace App\Support;

use Illuminate\Http\Request;

final class GdaLocale
{
    public static function fromRequest(Request $request): string
    {
        $h = strtolower(trim((string) $request->header('X-GDA-Ui-Lang', '')));
        if ($h === 'en') {
            return 'en';
        }
        if ($h === 'fr') {
            return 'fr';
        }

        $q = strtolower(trim((string) $request->query('locale', '')));
        if ($q === 'en') {
            return 'en';
        }

        $accept = strtolower(trim((string) $request->header('Accept-Language', '')));
        if ($accept !== '' && str_starts_with($accept, 'en')) {
            return 'en';
        }

        return 'fr';
    }
}
