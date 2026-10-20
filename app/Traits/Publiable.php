<?php
// app/Traits/Publiable.php

namespace App\Traits;

trait Publiable
{
    public function scopePublies($query)
    {
        return $query->where('statut', 'publie')
                     ->where(function ($q) {
                         $q->whereNull('date_publication')
                           ->orWhere('date_publication', '<=', now());
                     });
    }

    public function scopeBrouillons($query)
    {
        return $query->where('statut', 'brouillon');
    }

    public function scopeArchives($query)
    {
        return $query->where('statut', 'archive');
    }

    public function estPublie(): bool
    {
        return $this->statut === 'publie' &&
               ($this->date_publication === null || $this->date_publication <= now());
    }
}