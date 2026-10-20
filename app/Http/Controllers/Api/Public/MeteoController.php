<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Services\WeatherService;
use Illuminate\Support\Facades\Cache;

class MeteoController extends Controller
{
    public function jour(WeatherService $weather)
    {
        // On ne met en cache que les réponses réussies : si la clé API est absente/invalide
        // ou l'appel échoue, on ne veut pas figer un résultat vide pendant 30 minutes une
        // fois le problème corrigé (Cache::remember mettrait aussi `null` en cache).
        $data = Cache::get('v1:meteo:jour');

        if ($data === null) {
            $data = $weather->jour();

            if ($data !== null) {
                Cache::put('v1:meteo:jour', $data, 1800);
            }
        }

        return response()->json(['data' => $data]);
    }
}
