<?php

namespace App\Jobs;

use App\Models\MediaLibrary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class OptimizeVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries = 1;

    public function __construct(
        public readonly int $mediaId,
        public readonly string $temporaryPath,
    ) {}

    public function handle(): void
    {
        $media = MediaLibrary::find($this->mediaId);
        $temporaryDisk = Storage::disk('local');

        if (! $media) {
            $temporaryDisk->delete($this->temporaryPath);
            return;
        }

        $outputPath = 'library/videos/' . Str::uuid() . '.mp4';

        try {
            if (! $temporaryDisk->exists($this->temporaryPath)) {
                throw new \RuntimeException('Le fichier vidéo source est introuvable.');
            }

            $publicDisk = Storage::disk('public');
            $publicDisk->makeDirectory('library/videos');
            $process = new Process([
                'ffmpeg', '-y', '-i', $temporaryDisk->path($this->temporaryPath),
                '-map', '0:v:0', '-map', '0:a?',
                '-vf', "scale=w='min(1280,iw)':h='min(720,ih)':force_original_aspect_ratio=decrease",
                '-c:v', 'libx264', '-preset', 'medium', '-crf', '28',
                '-c:a', 'aac', '-b:a', '128k', '-movflags', '+faststart',
                $publicDisk->path($outputPath),
            ]);
            $process->setTimeout($this->timeout);
            $process->mustRun();

            $media->update([
                'path' => $outputPath,
                'mime_type' => 'video/mp4',
                'size' => $publicDisk->size($outputPath),
                'processing' => false,
                'processing_path' => null,
                'processing_error' => null,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($outputPath);
            $media->update([
                'processing' => false,
                'processing_error' => 'Optimisation impossible. Réessayez avec une vidéo MP4, WebM ou OGG valide.',
            ]);
        } finally {
            $temporaryDisk->delete($this->temporaryPath);
        }
    }
}
