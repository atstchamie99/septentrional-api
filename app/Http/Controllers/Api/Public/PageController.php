<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Public\PublicPageResource;
use App\Models\Page;

class PageController extends Controller
{
    public function show(string $slug)
    {
        $page = Page::publies()->where('slug', $slug)->firstOrFail();

        return new PublicPageResource($page);
    }
}
