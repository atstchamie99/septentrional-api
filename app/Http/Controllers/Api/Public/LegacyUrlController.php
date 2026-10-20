<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Categorie;
use App\Models\Page;
use App\Models\WordpressImport;
use Illuminate\Http\Request;

class LegacyUrlController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate(['path' => ['required', 'string', 'max:512', 'regex:~^/(?!/)[^?#]*$~']]);
        $path = '/'.trim($data['path'], '/').'/';
        $mapping = WordpressImport::where('original_path', $path)->first();
        abort_unless($mapping, 404);

        $target = match ($mapping->target_type) {
            Article::class => Article::publies()->find($mapping->target_id),
            Page::class => Page::publies()->find($mapping->target_id),
            Categorie::class => Categorie::find($mapping->target_id),
            default => null,
        };
        abort_unless($target, 404);
        $destination = match ($mapping->target_type) {
            Article::class => '/article/'.$target->slug,
            Page::class => in_array($target->slug, ['a-propos', 'contact', 'mentions-legales'], true) ? '/'.$target->slug : '/pages/'.$target->slug,
            Categorie::class => '/categorie/'.$target->slug,
        };

        return response()->json(['data' => ['path' => $destination]]);
    }
}
