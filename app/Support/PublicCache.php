<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class PublicCache
{
    public static function forgetArticles(): void
    {
        self::forgetFlashInfos();
        Cache::forget('v1:articles:alertes');
        Cache::forget('v1:seo:sitemap');
        // Versioned keys invalidate every popular-list variation without flushing unrelated cache entries.
        Cache::increment('v1:articles:cache-version');
    }

    public static function forgetFlashInfos(): void
    {
        Cache::forget('v1:flash-infos');
        Cache::forget('v2:flash-infos');
    }


    public static function forgetPartenaires(?string $position = null): void
    {
        Cache::forget('v1:partenaires');

        if ($position) {
            Cache::forget("v1:partenaires:{$position}");
        }
    }

    public static function forgetParametres(): void
    {
        Cache::forget('v1:parametres:publics');
    }

    public static function forgetOccasionActive(): void
    {
        Cache::forget('v1:occasions:actives');
    }
}
