<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Concerns\AppliesListFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListRequest;
use App\Http\Resources\Public\PublicVideoResource;
use App\Models\Video;
use App\Services\ViewCounter;

class VideoController extends Controller
{
    use AppliesListFilters;

    public function __construct(private ViewCounter $viewCounter)
    {
    }

    public function index(ListRequest $request)
    {
        $validated = $request->validated();
        
        $query = Video::publies()->with(['categorie', 'user']);

        if ($request->has('categorie')) {
            $query->whereHas('categorie', fn ($q) => $q->where('slug', $request->categorie));
        }

        $searchTerm = $validated['q'] ?? null;
        $this->applySearch($query, $searchTerm, ['titre', 'description']);
        
        $sortColumn = $request->getSortColumn(['date_publication', 'nombre_vues', 'created_at'], 'date_publication');
        $sortDirection = $request->getSortDirection('desc');
        $query->orderBy($sortColumn, $sortDirection);

        $perPage = $request->getPerPage(12);
        $videos = $query->paginate($perPage);

        return PublicVideoResource::collection($videos);
    }

    public function show(string $slug)
    {
        $video = Video::publies()
            ->where('slug', $slug)
            ->with(['categorie', 'user', 'commentaires' => fn ($q) => $q->where('statut', 'approuve')])
            ->firstOrFail();

        $this->viewCounter->increment($video, request(), 'video');

        return new PublicVideoResource($video);
    }
}
