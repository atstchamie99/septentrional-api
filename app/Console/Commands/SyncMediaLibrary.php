<?php

namespace App\Console\Commands;

use App\Models\MediaLibrary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SyncMediaLibrary extends Command
{
    protected $signature = 'media:sync';
    protected $description = 'Synchronise les fichiers existants sur le disque avec la table media_library';

    public function handle()
    {
        $disk = Storage::disk('public');
        $files = $disk->allFiles();
        
        $count = 0;
        $this->info('Début de la synchronisation de ' . count($files) . ' fichiers...');
        
        foreach ($files as $path) {
            // Ignorer les dossiers non pertinents (ex: cache, livewire-tmp)
            if (Str::startsWith($path, ['livewire-tmp/', 'media-processing/', '.'])) {
                continue;
            }
            
            $mimeType = $disk->mimeType($path);
            if (!$mimeType) continue;
            
            $isImage = Str::startsWith($mimeType, 'image/');
            $isVideo = Str::startsWith($mimeType, 'video/');
            
            if (!$isImage && !$isVideo) {
                continue;
            }
            
            // Vérifier si le fichier existe déjà dans la base
            $exists = MediaLibrary::where('source', 'upload')->where('path', $path)->exists();
            
            if (!$exists) {
                $filename = basename($path);
                $title = pathinfo($filename, PATHINFO_FILENAME);
                
                MediaLibrary::create([
                    'uploaded_by' => null, // Anonyme ou Admin
                    'type' => $isImage ? 'image' : 'video',
                    'source' => 'upload',
                    'path' => $path,
                    'original_name' => $filename,
                    'mime_type' => $mimeType,
                    'size' => $disk->size($path),
                    'title' => str_replace(['-', '_'], ' ', $title),
                ]);
                $count++;
            }
        }
        
        $this->info("Terminé ! $count nouveaux fichiers ont été ajoutés à la médiathèque.");
    }
}
