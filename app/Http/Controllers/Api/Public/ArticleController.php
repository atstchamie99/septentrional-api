<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Concerns\AppliesListFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListRequest;
use App\Http\Resources\Public\PublicArticleResource;
use App\Models\Article;
use App\Services\ViewCounter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ArticleController extends Controller
{
    use AppliesListFilters;

    public function __construct(private ViewCounter $viewCounter)
    {
    }

    public function index(ListRequest $request)
    {
        $validated = $request->validated();
        
        $query = Article::publies()
            ->with(['categorie', 'categories', 'user', 'etiquettes']);

        if ($request->filled('categorie')) {
            $query->dansCategorie($request->categorie);
        }

        if ($request->has('etiquette')) {
            $query->whereHas('etiquettes', fn ($q) => $q->where('slug', $request->etiquette));
        }

        $searchTerm = $validated['q'] ?? null;
        $this->applySearch($query, $searchTerm, ['titre', 'extrait']);
        
        $sortColumn = $request->getSortColumn(['date_publication', 'nombre_vues', 'created_at'], 'date_publication');
        $sortDirection = $request->getSortDirection('desc');
        $query->orderBy($sortColumn, $sortDirection);

        if ($request->boolean('alertes')) {
            $query->where('est_alerte', true);
        }

        if ($request->filled('annee')) {
            $query->whereYear('date_publication', $request->integer('annee'));
        }

        if ($request->filled('mois')) {
            $query->whereMonth('date_publication', $request->integer('mois'));
        }

        if ($request->filled('periode')) {
            $parts = explode('-', (string) $request->input('periode'));
            if (count($parts) === 2) {
                $query->whereYear('date_publication', (int) $parts[0])
                      ->whereMonth('date_publication', (int) $parts[1]);
            }
        }

        $perPage = $request->getPerPage(12);
        $articles = $query->paginate($perPage);

        return PublicArticleResource::collection($articles);
    }

    public function archivesMois()
    {
        // Cache only data: persistent stores may refuse to unserialize HTTP responses.
        $archives = Cache::remember('v2:articles:archives-mois', 300, function () {
            $moisFrancais = [
                1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
                5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
                9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
            ];

            $anchor = Article::publies()->max('date_publication');
            $datePivot = $anchor ? \Carbon\Carbon::parse($anchor)->startOfMonth() : now()->startOfMonth();

            $resultats = [];
            for ($i = 0; $i < 6; $i++) {
                $curDate = (clone $datePivot)->subMonths($i);
                $annee = (int) $curDate->format('Y');
                $mois = (int) $curDate->format('n');
                $moisNom = $moisFrancais[$mois] ?? $curDate->format('F');
                $slugMois = sprintf('%04d-%02d', $annee, $mois);

                $query = Article::publies()
                    ->whereYear('date_publication', $annee)
                    ->whereMonth('date_publication', $mois);

                $total = (clone $query)->count();

                $topArticle = (clone $query)
                    ->whereNotNull('image_principale')
                    ->where('image_principale', '!=', '')
                    ->orderByDesc('nombre_vues')
                    ->first() ?? (clone $query)->latest('date_publication')->first();

                $resultats[] = [
                    'annee'          => $annee,
                    'mois'           => $mois,
                    'mois_nom'       => $moisNom,
                    'label'          => "{$moisNom} {$annee}",
                    'periode'        => $slugMois,
                    'total_articles' => $total,
                    'image'          => $topArticle?->image_url,
                    'top_titre'      => $topArticle?->titre,
                ];
            }

            // Ordre chronologique : du plus ancien au plus récent
            $resultats = array_reverse($resultats);

            return $resultats;
        });

        return response()->json([
            'success' => true,
            'data' => $archives,
        ]);
    }

    public function show(string $slug)
    {
        $article = Article::publies()
            ->where('slug', $slug)
            ->with(['categorie', 'categories', 'user', 'etiquettes', 'commentaires' => fn ($q) => $q->where('statut', 'approuve')->latest()])
            ->firstOrFail();

        $this->viewCounter->increment($article, request(), 'article');

        return new PublicArticleResource($article);
    }

    public function alertes()
    {
        return Cache::remember('v1:articles:alertes', 300, function () {
            $articles = Article::publies()
                ->where('est_alerte', true)
                ->latest('date_publication')
                ->limit(5)
                ->with(['categorie', 'categories', 'user', 'etiquettes'])
                ->get();

            return json_decode(PublicArticleResource::collection($articles)->toJson(), true);
        });
    }

    public function populaires(Request $request)
    {
        $limit = min($request->integer('limit', 5) ?: 5, 10);
        $excludeId = $request->integer('exclude') ?: null;

        $cacheVersion = Cache::get('v1:articles:cache-version', 1);
        $cacheKey = "v1:articles:populaires:{$cacheVersion}:{$limit}:" . ($excludeId ?? 'none');

        return Cache::remember($cacheKey, 300, function () use ($limit, $excludeId) {
            $query = Article::publies()
                ->orderByDesc('nombre_vues')
                ->limit($limit)
                ->with(['categorie', 'categories', 'user', 'etiquettes']);

            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            $articles = $query->get();

            return json_decode(PublicArticleResource::collection($articles)->toJson(), true);
        });
    }
}
