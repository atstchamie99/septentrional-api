<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePageRequest;
use App\Http\Requests\Admin\UpdatePageRequest;
use App\Http\Resources\PageResource;
use App\Models\Page;
use App\Support\AuditLog;
use Illuminate\Http\JsonResponse;
use Mews\Purifier\Facades\Purifier;

class PageController extends Controller
{
    public function index()
    {
        return PageResource::collection(Page::latest()->paginate(20));
    }

    public function store(StorePageRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['contenu'] = Purifier::clean($data['contenu']);

        $page = Page::create($data);

        return response()->json([
            'message' => 'Page créée avec succès.',
            'page'    => new PageResource($page),
        ], 201);
    }

    public function show(Page $page)
    {
        return new PageResource($page);
    }

    public function update(UpdatePageRequest $request, Page $page): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['contenu'])) {
            $data['contenu'] = Purifier::clean($data['contenu']);
        }

        $page->update($data);

        return response()->json([
            'message' => 'Page mise à jour.',
            'page'    => new PageResource($page->fresh()),
        ]);
    }

    public function destroy(Page $page): JsonResponse
    {
        AuditLog::record('Page supprimée', ['page_id' => $page->id, 'slug' => $page->slug]);

        $page->delete();

        return response()->json(['message' => 'Page supprimée.']);
    }
}