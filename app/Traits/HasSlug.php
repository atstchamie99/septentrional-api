<?php
// app/Traits/HasSlug.php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::creating(function ($model) {
            if (empty($model->slug)) {
                $source = property_exists($model, 'slugSource') ? $model->slugSource : 'titre';
                $model->slug = $model->generateUniqueSlug($source);
            }
        });

        static::updating(function ($model) {
            $source = property_exists($model, 'slugSource') ? $model->slugSource : 'titre';
            if ($model->isDirty($source) && empty($model->slug)) {
                $model->slug = $model->generateUniqueSlug($source);
            }
        });
    }

    /**
     * Génère un slug unique en ajoutant un suffixe si nécessaire
     */
    protected function generateUniqueSlug(string $sourceField): string
    {
        $slug = Str::slug($this->{$sourceField});
        $originalSlug = $slug;
        $count = 1;

        // Vérifier si le slug existe déjà
        while (static::where('slug', $slug)->where('id', '!=', $this->id ?? 0)->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }

        return $slug;
    }
}