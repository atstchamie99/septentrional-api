<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\PartenaireResource;
use App\Models\Partenaire;
use Illuminate\Support\Facades\Cache;

class PartenaireController extends Controller
{
    public function index()
    {
        $data = Cache::remember('v1:partenaires', 600, fn () => Partenaire::actifs()->latest()->get()->toArray());

        return PartenaireResource::collection(Partenaire::hydrate($data));
    }

    public function parPosition(string $position)
    {
        $data = Cache::remember("v1:partenaires:{$position}", 600, fn () => Partenaire::actifs()
            ->where('position', $position)
            ->latest()
            ->get()
            ->toArray());

        return PartenaireResource::collection(Partenaire::hydrate($data));
    }
}
