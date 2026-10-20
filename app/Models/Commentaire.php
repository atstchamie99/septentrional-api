<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Commentaire extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nom',
        'email',
        'parent_id',
        'modere_par',
        'commentable_type',
        'commentable_id',
        'contenu',
        'statut',
        'adresse_ip',
    ];

    public function commentable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function moderateur()
    {
        return $this->belongsTo(User::class, 'modere_par');
    }

    public function parent()
    {
        return $this->belongsTo(Commentaire::class, 'parent_id');
    }

    public function reponses()
    {
        return $this->hasMany(Commentaire::class, 'parent_id');
    }

    /** Filtre : commentaires approuvés uniquement (usage admin). */
    public function scopeApprouves($query)
    {
        return $query->where('statut', 'approuve');
    }

    /** Filtre public : tout sauf les commentaires rejetés par l'admin. */
    public function scopeNonRejetes($query)
    {
        return $query->where('statut', '!=', 'rejete');
    }

    public function scopeEnAttente($query)
    {
        return $query->where('statut', 'en_attente');
    }
}