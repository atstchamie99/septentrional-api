<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\FlashInfoResource;
use App\Models\Article;
use App\Models\FlashInfo;
use Illuminate\Support\Facades\Cache;

class FlashInfoController extends Controller
{
    public function index()
    {
        $data = Cache::remember('v2:flash-infos', 60, function () {
            $flashInfos = FlashInfo::actifs()
                ->where(function ($query) {
                    $query->whereNull('article_id')
                        ->orWhereHas('article', fn ($articles) => $articles->publies());
                })
                ->with('article')->latest()->limit(10)->get();

            // Le bandeau WordPress présente les titres récents même sans
            // entrée dédiée dans le CMS. Les flashs manuels restent prioritaires.
            if ($flashInfos->isEmpty()) {
                $flashInfos = Article::publies()->latest('date_publication')->latest('id')->limit(5)->get()
                    ->map(function (Article $article) {
                        $flash = (new FlashInfo)->forceFill([
                            'id' => $article->id,
                            'texte' => $article->titre,
                            'article_id' => $article->id,
                            'est_actif' => true,
                            'date_debut' => $article->date_publication,
                            'created_at' => $article->created_at,
                        ]);
                        $flash->setRelation('article', $article);

                        return $flash;
                    });
            }

            return FlashInfoResource::collection($flashInfos)->resolve();
        });

        return response()->json(['data' => $data]);
    }
}
