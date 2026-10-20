<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\OptimizedImageStorage;
use App\Models\MediaLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ImageController extends Controller
{
    private const MAX_MEDIA_KB = 51200; // 50 Mo

    /**
     * Upload an image and return its public URL
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:' . self::MAX_MEDIA_KB,
        ], [
            'image.required' => 'Une image est requise',
            'image.image' => 'Le fichier doit être une image',
            'image.mimes' => 'Seuls les formats JPEG, PNG, GIF, WEBP sont acceptés',
            'image.max' => 'L\'image ne doit pas dépasser 50 Mo',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $path = app(OptimizedImageStorage::class)
                ->store($request->file('image'), 'images');

            // Générer l'URL publique absolue
            $url = Storage::disk('public')->url($path);
            MediaLibrary::firstOrCreate(
                ['source' => 'upload', 'path' => $path],
                ['uploaded_by' => $request->user()?->id, 'type' => 'image', 'original_name' => $request->file('image')->getClientOriginalName(), 'mime_type' => $request->file('image')->getMimeType(), 'size' => $request->file('image')->getSize(), 'title' => pathinfo($request->file('image')->getClientOriginalName(), PATHINFO_FILENAME)]
            );

            return response()->json([
                'url' => $url,
                'message' => 'Image uploadée avec succès',
                'path' => $path,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de l\'upload',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function uploadVideo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'video' => 'required|file|mimes:mp4,webm,ogg|max:' . self::MAX_MEDIA_KB,
        ], [
            'video.required' => 'Une vidéo est requise',
            'video.mimes'    => 'Seuls les formats MP4, WEBM, OGG sont acceptés',
            'video.max'      => 'La vidéo ne doit pas dépasser 50 Mo',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Erreur de validation', 'errors' => $validator->errors()], 422);
        }

        try {
            $path = $request->file('video')->store('videos', 'public');
            $url  = Storage::disk('public')->url($path);
            MediaLibrary::firstOrCreate(
                ['source' => 'upload', 'path' => $path],
                ['uploaded_by' => $request->user()?->id, 'type' => 'video', 'original_name' => $request->file('video')->getClientOriginalName(), 'mime_type' => $request->file('video')->getMimeType(), 'size' => $request->file('video')->getSize(), 'title' => pathinfo($request->file('video')->getClientOriginalName(), PATHINFO_FILENAME)]
            );
            return response()->json(['url' => $url, 'message' => 'Vidéo uploadée avec succès', 'path' => $path], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur lors de l\'upload', 'error' => $e->getMessage()], 500);
        }
    }
}
