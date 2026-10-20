<?php

namespace App\Traits;

use App\Models\MediaLibrary;

/**
 * Le filtre "utilisé / non utilisé" de la médiathèque (MediaLibraryController::index,
 * usage=used|unused) repose sur MediaLibrary::usedMediaIds(), mis en cache 5 minutes.
 * Sans invalidation, une image piochée dans la médiathèque (image_media_id) ou
 * liée par un nouvel upload direct pour ce modèle continue d'apparaître comme
 * "non utilisée" jusqu'à expiration du cache — un admin peut alors tenter de la
 * supprimer et se voir refuser (409, "encore utilisé") sans comprendre pourquoi,
 * alors que la médiathèque elle-même affichait l'image comme supprimable.
 *
 * Ce trait invalide le cache à chaque sauvegarde/suppression du modèle qui
 * l'utilise, quel que soit le point d'entrée (contrôleur, tinker, job...).
 */
trait InvalidatesMediaUsageCache
{
    protected static function bootInvalidatesMediaUsageCache(): void
    {
        static::saved(fn () => MediaLibrary::forgetUsageCache());
        static::deleted(fn () => MediaLibrary::forgetUsageCache());
    }
}
