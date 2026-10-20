<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Parametre;
use Illuminate\Support\Facades\Cache;

class ParametreController extends Controller
{
    /**
     * Groupes de paramètres exposés publiquement.
     * Le groupe "systeme" (et tout groupe non listé ici) reste admin-only.
     */
    private const PUBLIC_GROUPS = ['social', 'general', 'seo'];

    public function publics()
    {
        $data = Cache::remember('v1:parametres:publics', 600, function () {
            return Parametre::whereIn('groupe', self::PUBLIC_GROUPS)
                ->get()
                ->groupBy('groupe')
                ->map(fn ($parametres) => $parametres->mapWithKeys(
                    fn ($parametre) => [$parametre->cle => $parametre->valeur_castee]
                ))
                ->toArray();
        });

        return response()->json(['data' => $data]);
    }
}
