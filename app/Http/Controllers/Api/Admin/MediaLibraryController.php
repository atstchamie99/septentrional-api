<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaLibrary;
use App\Jobs\OptimizeVideo;
use App\Services\OptimizedImageStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaLibraryController extends Controller
{
    private const MAX_MEDIA_KB = 51200; // 50 Mo

    public function index(Request $request)
    {
        $request->validate([
            'type' => 'nullable|in:image,video',
            'usage' => 'nullable|in:used,unused',
            'q' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = MediaLibrary::query()->latest();
        $type = $request->string('type')->toString();
        $usage = $request->string('usage')->toString();

        if ($type !== '') {
            $query->where('type', $type);
        }
        if ($request->filled('q')) {
            $query->where(function ($media) use ($request): void {
                $term = '%'.$request->string('q').'%';
                $media->where('title', 'like', $term)->orWhere('original_name', 'like', $term);
            });
        }
        if ($usage !== '') {
            $usedIds = MediaLibrary::usedMediaIds();

            if ($usage === 'used') {
                $usedIds === [] ? $query->whereRaw('0 = 1') : $query->whereIn('id', $usedIds);
            } else {
                $query->whereNotIn('id', $usedIds);
            }
        }
        $media = $query->paginate(min($request->integer('per_page', 24), 100));
        $references = MediaLibrary::referencesForPaths($media->pluck('path')->filter()->all());

        $media->setCollection($media->getCollection()
            ->map(function (MediaLibrary $item) use ($references): MediaLibrary {
                $item->setAttribute('folder', $item->path ? dirname($item->path) : 'Lien externe');
                $itemReferences = $item->source === 'external' && $item->external_url
                    ? MediaLibrary::referencesForExternalUrl($item->external_url)
                    : ($references[$item->path] ?? []);
                $item->setAttribute('references', $itemReferences);
                $item->setAttribute('is_used', $itemReferences !== []);
                return $item;
            }));

        return response()->json($media);
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:image,video', 'file' => 'nullable|file|max:' . self::MAX_MEDIA_KB,
            'external_url' => 'nullable|url', 'title' => 'nullable|string|max:255', 'alt_text' => 'nullable|string|max:255',
        ]);
        abort_if(!$request->hasFile('file') && !$request->filled('external_url'), 422, 'Un fichier ou un lien est requis.');
        $type = $request->string('type')->toString();
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $request->validate(['file' => $type === 'image'
                ? 'image|mimes:jpeg,png,jpg,gif,webp|max:' . self::MAX_MEDIA_KB
                : 'mimes:mp4,webm,ogg|max:' . self::MAX_MEDIA_KB]);
            if ($type === 'image') {
                $path = app(OptimizedImageStorage::class)->store($file, 'library/images');
                $media = MediaLibrary::create(['uploaded_by'=>$request->user()->id,'type'=>$type,'source'=>'upload','path'=>$path,'original_name'=>$file->getClientOriginalName(),'mime_type'=>$file->getMimeType(),'size'=>$file->getSize(),'title'=>$request->title ?: $file->getClientOriginalName(),'alt_text'=>$request->alt_text]);
            } else {
                $temporaryPath = $file->store('media-processing', 'local');
                $media = MediaLibrary::create(['uploaded_by'=>$request->user()->id,'type'=>'video','source'=>'upload','original_name'=>$file->getClientOriginalName(),'mime_type'=>$file->getMimeType(),'size'=>$file->getSize(),'title'=>$request->title ?: $file->getClientOriginalName(),'alt_text'=>$request->alt_text,'processing'=>true,'processing_path'=>$temporaryPath]);
                OptimizeVideo::dispatch($media->id, $temporaryPath);
            }
        } else {
            $media = MediaLibrary::create(['uploaded_by'=>$request->user()->id,'type'=>$type,'source'=>'external','external_url'=>$request->external_url,'title'=>$request->title,'alt_text'=>$request->alt_text]);
        }
        MediaLibrary::forgetUsageCache();
        return response()->json($media, 201);
    }

    public function show($id)
    {
        $media = MediaLibrary::findOrFail($id);
        return response()->json($media);
    }

    public function destroy($id)
    {
        $media = MediaLibrary::findOrFail($id);
        $references = $this->referencesFor($media);

        if ($references !== []) {
            return response()->json([
                'message' => 'Ce média est encore utilisé et ne peut pas être supprimé.',
                'references' => $references,
            ], 409);
        }

        $this->deleteMediaFiles($media);
        $media->delete();
        MediaLibrary::forgetUsageCache();
        return response()->noContent();
    }

    /**
     * Suppression groupée : chaque média est traité indépendamment (les
     * encore-utilisés sont ignorés plutôt que de faire échouer tout le lot),
     * pour permettre de sélectionner large sans devoir déselectionner les
     * quelques images encore référencées à la main.
     */
    public function destroyMany(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1|max:200',
            'ids.*' => 'integer',
        ]);

        $items = MediaLibrary::query()->whereIn('id', $data['ids'])->get();

        $deleted = [];
        $skipped = [];

        foreach ($items as $media) {
            $references = $this->referencesFor($media);

            if ($references !== []) {
                $skipped[] = [
                    'id' => $media->id,
                    'title' => $media->title ?: $media->original_name,
                    'references' => $references,
                ];
                continue;
            }

            $this->deleteMediaFiles($media);
            $media->delete();
            $deleted[] = $media->id;
        }

        MediaLibrary::forgetUsageCache();

        return response()->json([
            'message' => count($deleted) . ' média(s) supprimé(s)' . ($skipped !== [] ? ', ' . count($skipped) . ' encore utilisé(s) ignoré(s).' : '.'),
            'deleted' => $deleted,
            'skipped' => $skipped,
        ]);
    }

    private function referencesFor(MediaLibrary $media): array
    {
        return $media->source === 'external' && $media->external_url
            ? MediaLibrary::referencesForExternalUrl($media->external_url)
            : ($media->path ? MediaLibrary::referencesForPath($media->path) : []);
    }

    private function deleteMediaFiles(MediaLibrary $media): void
    {
        if ($media->source === 'upload' && $media->path) Storage::disk('public')->delete($media->path);
        if ($media->processing_path) Storage::disk('local')->delete($media->processing_path);
    }
}
