<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    /**
     * Récupère la météo du jour pour Dapaong, Kara, Sokodé, Atakpamé, Lomé via OpenWeatherMap.
     * Retourne null si la clé API n'est pas configurée ou en cas d'erreur.
     */
    public function jour(): ?array
    {
        $apiKey = config('services.openweather.key');

        if (!$apiKey) {
            return null;
        }

        $cities = [
            ['name' => 'Dapaong', 'lat' => 10.86, 'lon' => 0.20],
            ['name' => 'Kara', 'lat' => 9.55, 'lon' => 1.18],
            ['name' => 'Sokodé', 'lat' => 8.98, 'lon' => 1.13],
            ['name' => 'Atakpamé', 'lat' => 7.53, 'lon' => 1.13],
            ['name' => 'Lomé', 'lat' => 6.13, 'lon' => 1.22],
        ];

        $weatherData = [];

        foreach ($cities as $city) {
            try {
                $response = Http::timeout(5)->get('https://api.openweathermap.org/data/2.5/weather', [
                    'lat'   => $city['lat'],
                    'lon'   => $city['lon'],
                    'appid' => $apiKey,
                    'units' => 'metric',
                    'lang'  => 'fr',
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $weatherData[] = [
                        'temperature' => round($data['main']['temp'] ?? null),
                        'description' => $data['weather'][0]['description'] ?? null,
                        'icone'       => $data['weather'][0]['icon'] ?? null,
                        'humidite'    => $data['main']['humidity'] ?? null,
                        'ville'       => $city['name'],
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('WeatherService: échec de récupération météo pour ' . $city['name'], ['message' => $e->getMessage()]);
            }
        }

        return !empty($weatherData) ? $weatherData : null;
    }
}
