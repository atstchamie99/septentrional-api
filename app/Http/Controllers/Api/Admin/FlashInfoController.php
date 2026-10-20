<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFlashInfoRequest;
use App\Http\Requests\Admin\UpdateFlashInfoRequest;
use App\Http\Resources\FlashInfoResource;
use App\Models\FlashInfo;
use App\Support\PublicCache;
use Illuminate\Http\JsonResponse;

class FlashInfoController extends Controller
{
    public function index()
    {
        return FlashInfoResource::collection(FlashInfo::with('article')->latest()->paginate(20));
    }

    public function store(StoreFlashInfoRequest $request): JsonResponse
    {
        $flash = FlashInfo::create($request->validated());

        PublicCache::forgetFlashInfos();

        return response()->json([
            'message' => 'Flash info créé avec succès.',
            'flash'   => new FlashInfoResource($flash),
        ], 201);
    }

    public function show(FlashInfo $flashInfo)
    {
        return new FlashInfoResource($flashInfo);
    }

    public function update(UpdateFlashInfoRequest $request, FlashInfo $flashInfo): JsonResponse
    {
        $flashInfo->update($request->validated());

        PublicCache::forgetFlashInfos();

        return response()->json([
            'message' => 'Flash info mis à jour.',
            'flash'   => new FlashInfoResource($flashInfo->fresh()),
        ]);
    }

    public function destroy(FlashInfo $flashInfo): JsonResponse
    {
        $flashInfo->delete();

        PublicCache::forgetFlashInfos();

        return response()->json(['message' => 'Flash info supprimé.']);
    }
}