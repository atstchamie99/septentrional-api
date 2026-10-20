<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class OwnableContentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->estAdmin() || $user->estRedacteur();
    }

    public function view(User $user, Model $model): bool
    {
        // Un rédacteur a accès à tout le contenu (pas seulement le sien) pour faciliter le travail d'équipe.
        return $user->estAdmin() || $user->estRedacteur();
    }

    public function create(User $user): bool
    {
        return $user->estAdmin() || $user->estRedacteur();
    }

    public function update(User $user, Model $model): bool
    {
        if ($user->estAdmin()) return true;
        if (!$user->estRedacteur()) return false;
        return (int) $model->getAttribute('user_id') === $user->id;
    }

    public function delete(User $user, Model $model): bool
    {
        // La suppression reste réservée à l'admin (déjà imposé par les routes, ceinture + bretelles ici).
        return $user->estAdmin();
    }
}
