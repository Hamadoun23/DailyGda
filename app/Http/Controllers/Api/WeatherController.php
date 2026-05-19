<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\GdaLocale;
use App\Support\WeatherService;
use Illuminate\Http\Request;

class WeatherController extends Controller
{
    public function nav(Request $request, WeatherService $weather)
    {
        $lat = $request->query('lat');
        $lon = $request->query('lon');

        $latF = is_numeric($lat) ? (float) $lat : null;
        $lonF = is_numeric($lon) ? (float) $lon : null;

        $lang = GdaLocale::fromRequest($request);

        return response()->json($weather->currentForNav($latF, $lonF, $lang));
    }
}
