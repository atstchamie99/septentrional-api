<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PartenaireResource;
use App\Models\MediaLibrary;
use App\Models\Partenaire;
use App\Services\OptimizedImageStorage;
use App\Support\AuditLog;
use App\Support\MediaReplacement;
use App\Support\PublicCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PartenaireController extends Controller
{
    public function index()
    {
        return PartenaireResource::collection(Partenaire::latest()->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom' => 'required|string|max:255',
            'logo' => 'required|image|mimes:jpeg,png,jpg,webp|max:51200',
            'lien' => 'nullable|url|max:500',
            'position' => 'required|in:en-tete,barre-laterale,pied-de-page',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date',
            'est_actif' => 'boolean',
        ]);

        if ($request->hasFile('logo')) {
            $data['logo'] = app(OptimizedImageStorage::class)
                ->store($request->file('logo'), 'partenaires');
        }

        $part = Partenaire::create($data);

        PublicCache::forgetPartenaires($data['position']);

        return response()->json([
            'message' => 'Partenaire créé avec succès.',
            'partenaire' => new PartenaireResource($part),
        ], 201);
    }

    public function show(Partenaire $partenaire)
    {
        return new PartenaireResource($partenaire);
    }

    public function update(Request $request, Partenaire $partenaire): JsonResponse
    {
        $data = $request->validate([
            'nom' => 'sometimes|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:51200',
            'lien' => 'nullable|url|max:500',
            'position' => 'sometimes|in:en-tete,barre-laterale,pied-de-page',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date',
            'est_actif' => 'boolean',
        ]);

        $oldPosition = $partenaire->position;

        MediaReplacement::run(function (MediaReplacement $media) use ($request, $partenaire, &$data): void {
            if ($request->hasFile('logo')) {
                $newLogo = app(OptimizedImageStorage::class)
                    ->store($request->file('logo'), 'partenaires');
                $data['logo'] = $media->replaceOnDisk(
                    $newLogo,
                    MediaLibrary::safeOldPathForReplacement($partenaire->logo, $newLogo, Partenaire::class, $partenaire->id),
                );
            }

            DB::transaction(function () use ($partenaire, $data): void {
                $partenaire->fill($data)->saveOrFail();
            });
        });

        PublicCache::forgetPartenaires($oldPosition);
        PublicCache::forgetPartenaires($partenaire->position);

        return response()->json([
            'message' => 'Partenaire mis à jour.',
            'partenaire' => new PartenaireResource($partenaire->fresh()),
        ]);
    }

    public function destroy(Partenaire $partenaire): JsonResponse
    {
        // Ne supprimer le fichier que s'il n'est pas réutilisé ailleurs (logo
        // piochée dans la médiathèque et partagée avec un autre contenu).
        $safeToDeleteLogo = MediaLibrary::safeOldPathForReplacement(
            $partenaire->logo,
            '',
            Partenaire::class,
            $partenaire->id,
        );

        if ($safeToDeleteLogo) {
            Storage::disk('public')->delete($safeToDeleteLogo);
        }

        $position = $partenaire->position;
        $partenaireId = $partenaire->id;
        $partenaire->delete();

        PublicCache::forgetPartenaires($position);

        AuditLog::record('Partenaire supprimé', ['partenaire_id' => $partenaireId]);

        return response()->json(['message' => 'Partenaire supprimé.']);
    }
}
