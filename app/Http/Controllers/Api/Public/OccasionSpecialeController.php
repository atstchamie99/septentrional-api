<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\OccasionSpecialeResource;
use App\Models\OccasionSpeciale;
use Illuminate\Support\Facades\Cache;

class OccasionSpecialeController extends Controller
{
    /**
     * Toutes les occasions actives du moment (plusieurs peuvent se chevaucher,
     * ex: vœux de Nouvel An + une autre annonce) : le front les affiche toutes,
     * sans qu'une n'en masque une autre.
     */
    public function active()
    {
        $data = Cache::remember('v1:occasions:actives', 900, fn () => OccasionSpeciale::actives()
            ->latest('date_debut')
            ->get()
            ->toArray());

        return response()->json([
            'data' => OccasionSpecialeResource::collection(OccasionSpeciale::hydrate($data)),
        ]);
    }
}
