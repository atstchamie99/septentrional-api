<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\VideoResource;
use App\Models\MediaLibrary;
use App\Models\Video;
use App\Services\OptimizedImageStorage;
use App\Support\AuditLog;
use App\Support\MediaReplacement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Mews\Purifier\Facades\Purifier;

class VideoController extends Controller
{
    private const MAX_MEDIA_KB = 51200; // 50 Mo

    public function index(Request $request)
    {
        // Un rédacteur voit tout le contenu, pas seulement le sien, pour faciliter le travail d'équipe.
        $query = Video::with(['categorie', 'user'])->latest();

        return VideoResource::collection($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Video::class);

        $rules = [
            'categorie_id' => 'required|exists:categories,id',
            'titre' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:videos,slug',
            'description' => 'nullable|string',
            'miniature' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:' . self::MAX_MEDIA_KB,
            'duree' => 'integer|min:0',
            'statut' => 'required|in:brouillon,publie,archive',
            'date_publication' => 'nullable|date',
        ];

        $typeVideo = $request->input('type_video', 'youtube');

        // Validation conditionnelle selon le type de vidéo
        if ($typeVideo === 'upload') {
            $rules['type_video'] = 'required|in:upload';
            $rules['file'] = 'required|file|mimes:mp4,mov,avi,wmv,flv|max:' . self::MAX_MEDIA_KB;
            $rules['url_video'] = 'nullable|string|max:500';
        } else {
            $rules['type_video'] = 'required|in:youtube,vimeo';
            $rules['url_video'] = 'required|url|max:500';
            $rules['file'] = 'nullable';
        }

        $data = $request->validate($rules);

        $data['user_id'] = $request->user()->id;

        // Gestion de l'upload de fichier vidéo
        if ($typeVideo === 'upload') {
            if (! $request->hasFile('file')) {
                return response()->json([
                    'message' => 'Le fichier vidéo est requis pour le type "upload".',
                    'errors' => ['file' => ['The file field is required.']],
                ], 422);
            }

            $file = $request->file('file');

            // Vérification du MIME réel (pas seulement l'extension)
            $allowedMimes = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-ms-wmv', 'video/x-flv'];
            $mime = $file->getMimeType();
            if (! in_array($mime, $allowedMimes)) {
                return response()->json([
                    'message' => 'Type de fichier vidéo non autorisé.',
                    'allowed_types' => implode(', ', $allowedMimes),
                ], 422);
            }

            $filename = Str::random(40).'.'.$file->extension();
            $file->move(public_path('videos'), $filename);
            $data['url_video'] = 'videos/'.$filename;
        }

        if ($request->hasFile('miniature')) {
            $data['miniature'] = app(OptimizedImageStorage::class)
                ->store($request->file('miniature'), 'videos/miniatures');
        }

        if (isset($data['description'])) {
            $data['description'] = Purifier::clean($data['description']);
        }

        $video = Video::create($data);

        return response()->json([
            'message' => 'Vidéo créée avec succès.',
            'video' => new VideoResource($video->load('categorie')),
        ], 201);
    }

    public function show(Video $video)
    {
        $this->authorize('view', $video);

        return new VideoResource($video->load('categorie', 'user'));
    }

    public function update(Request $request, Video $video): JsonResponse
    {
        $this->authorize('update', $video);

        $typeVideo = $request->input('type_video', $video->type_video);

        $rules = [
            'categorie_id' => 'sometimes|exists:categories,id',
            'titre' => 'sometimes|string|max:255',
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('videos')->ignore($video->id)],
            'description' => 'nullable|string',
            'miniature' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:' . self::MAX_MEDIA_KB,
            'type_video' => 'sometimes|in:upload,youtube,vimeo',
            'duree' => 'integer|min:0',
            'statut' => 'sometimes|in:brouillon,publie,archive',
            'date_publication' => 'nullable|date',
        ];

        // Validation conditionnelle selon le type de vidéo
        if ($typeVideo === 'upload') {
            $rules['file'] = ($video->type_video === 'upload' ? 'sometimes' : 'required')
                .'|file|mimes:mp4,mov,avi,wmv,flv|max:' . self::MAX_MEDIA_KB;
            $rules['url_video'] = 'nullable|string|max:500';
        } else {
            $rules['url_video'] = ($video->type_video === 'upload' ? 'required' : 'sometimes')
                .'|url|max:500';
            $rules['file'] = 'nullable';
        }

        $data = $request->validate($rules);
        $uploadedVideo = $request->file('file');
        $thumbnail = $request->file('miniature');

        if ($typeVideo === 'upload' && $uploadedVideo) {
            // Vérification du MIME réel
            $allowedMimes = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-ms-wmv', 'video/x-flv'];
            $mime = $uploadedVideo->getMimeType();
            if (! in_array($mime, $allowedMimes, true)) {
                return response()->json([
                    'message' => 'Type de fichier vidéo non autorisé.',
                    'allowed_types' => implode(', ', $allowedMimes),
                ], 422);
            }
        }

        if (isset($data['description'])) {
            $data['description'] = Purifier::clean($data['description']);
        }

        MediaReplacement::run(function (MediaReplacement $media) use (
            $typeVideo,
            $uploadedVideo,
            $thumbnail,
            $video,
            &$data,
        ): void {
            $oldUploadedVideo = $video->type_video === 'upload'
                ? $this->uploadedVideoAbsolutePath($video->url_video)
                : null;

            if ($typeVideo === 'upload' && $uploadedVideo) {
                $filename = Str::random(40).'.'.$uploadedVideo->extension();
                File::ensureDirectoryExists(public_path('videos'));
                $newVideo = $uploadedVideo->move(public_path('videos'), $filename);

                $media->replaceLocalFile($newVideo->getPathname(), $oldUploadedVideo);
                $data['url_video'] = 'videos/'.$filename;
            } elseif ($typeVideo !== 'upload') {
                $media->deleteLocalFileAfterSuccess($oldUploadedVideo);
            }

            if ($thumbnail) {
                $newThumbnail = app(OptimizedImageStorage::class)
                    ->store($thumbnail, 'videos/miniatures');
                $data['miniature'] = $media->replaceOnDisk(
                    $newThumbnail,
                    MediaLibrary::safeOldPathForReplacement($video->miniature, $newThumbnail, Video::class, $video->id),
                );
            }

            DB::transaction(function () use ($video, $data): void {
                $video->fill($data)->saveOrFail();
            });
        });

        return response()->json([
            'message' => 'Vidéo mise à jour.',
            'video' => new VideoResource($video->fresh()->load('categorie')),
        ]);
    }

    public function destroy(Video $video): JsonResponse
    {
        $this->authorize('delete', $video);

        // Ne supprimer la miniature que si elle n'est pas réutilisée ailleurs
        // (image piochée dans la médiathèque et partagée avec un autre contenu).
        $safeToDeleteMiniature = MediaLibrary::safeOldPathForReplacement(
            $video->miniature,
            '',
            Video::class,
            $video->id,
        );

        if ($safeToDeleteMiniature) {
            Storage::disk('public')->delete($safeToDeleteMiniature);
        }

        // Le fichier vidéo lui-même (hors médiathèque, jamais partagé) doit
        // aussi être nettoyé, sinon il reste orphelin sur le disque.
        if ($video->type_video === 'upload') {
            $absolutePath = $this->uploadedVideoAbsolutePath($video->url_video);
            if ($absolutePath) {
                File::delete($absolutePath);
            }
        }

        $video->delete();

        AuditLog::record('Vidéo supprimée', ['video_id' => $video->id, 'titre' => $video->titre]);

        return response()->json(['message' => 'Vidéo supprimée.']);
    }

    public function publier(Video $video): JsonResponse
    {
        $this->authorize('publier', $video);

        $video->update([
            'statut' => 'publie',
            'date_publication' => now(),
        ]);

        return response()->json([
            'message' => 'Vidéo publiée.',
            'video' => new VideoResource($video),
        ]);
    }

    private function uploadedVideoAbsolutePath(?string $relativePath): ?string
    {
        if (
            $relativePath === null
            || ! preg_match('/\Avideos\/[A-Za-z0-9._-]+\z/', $relativePath)
        ) {
            return null;
        }

        return public_path($relativePath);
    }
}
