<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AbonneResource;
use App\Models\Abonne;
use Illuminate\Http\JsonResponse;

class AbonneController extends Controller
{
    public function index()
    {
        return AbonneResource::collection(Abonne::latest()->paginate(50));
    }

    public function show(Abonne $abonne)
    {
        return new AbonneResource($abonne);
    }

    public function destroy(Abonne $abonne): JsonResponse
    {
        $abonne->delete();

        return response()->json(['message' => 'Abonné supprimé.']);
    }
}