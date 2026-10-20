<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OccasionSpecialeResource;
use App\Models\MediaLibrary;
use App\Models\OccasionSpeciale;
use App\Services\OptimizedImageStorage;
use App\Support\AuditLog;
use App\Support\MediaReplacement;
use App\Support\PublicCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OccasionSpecialeController extends Controller
{
    public function index()
    {
        return OccasionSpecialeResource::collection(OccasionSpeciale::latest()->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'titre' => 'required|string|max:255',
            'texte' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:51200',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
            'est_actif' => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = app(OptimizedImageStorage::class)
                ->store($request->file('image'), 'occasions');
        }

        $occasion = OccasionSpeciale::create($data);

        PublicCache::forgetOccasionActive();

        return response()->json([
            'message' => 'Occasion spéciale créée avec succès.',
            'occasion' => new OccasionSpecialeResource($occasion),
        ], 201);
    }

    public function show(OccasionSpeciale $occasion)
    {
        return new OccasionSpecialeResource($occasion);
    }

    public function update(Request $request, OccasionSpeciale $occasion): JsonResponse
    {
        $data = $request->validate([
            'titre' => 'sometimes|required|string|max:255',
            'texte' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:51200',
            'date_debut' => 'sometimes|required|date',
            'date_fin' => 'sometimes|required|date|after_or_equal:date_debut',
            'est_actif' => 'boolean',
        ]);

        MediaReplacement::run(function (MediaReplacement $media) use ($request, $occasion, &$data): void {
            if ($request->hasFile('image')) {
                $newImage = app(OptimizedImageStorage::class)
                    ->store($request->file('image'), 'occasions');
                $data['image'] = $media->replaceOnDisk(
                    $newImage,
                    MediaLibrary::safeOldPathForReplacement($occasion->image, $newImage, OccasionSpeciale::class, $occasion->id),
                );
            }

            DB::transaction(function () use ($occasion, $data): void {
                $occasion->fill($data)->saveOrFail();
            });
        });

        PublicCache::forgetOccasionActive();

        return response()->json([
            'message' => 'Occasion spéciale mise à jour.',
            'occasion' => new OccasionSpecialeResource($occasion->fresh()),
        ]);
    }

    public function destroy(OccasionSpeciale $occasion): JsonResponse
    {
        // Ne supprimer le fichier que s'il n'est pas réutilisé ailleurs (image
        // piochée dans la médiathèque et partagée avec un autre contenu).
        $safeToDeleteImage = MediaLibrary::safeOldPathForReplacement(
            $occasion->image,
            '',
            OccasionSpeciale::class,
            $occasion->id,
        );

        if ($safeToDeleteImage) {
            Storage::disk('public')->delete($safeToDeleteImage);
        }

        $occasion->delete();

        PublicCache::forgetOccasionActive();

        AuditLog::record('Occasion spéciale supprimée', ['occasion_id' => $occasion->id, 'titre' => $occasion->titre]);

        return response()->json(['message' => 'Occasion spéciale supprimée.']);
    }
}
