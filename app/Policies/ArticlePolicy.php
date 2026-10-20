<?php

namespace App\Policies;

use App\Models\Article;
use App\Models\User;

class ArticlePolicy extends OwnableContentPolicy
{
    public function publier(User $user, Article $article): bool
    {
        return $this->update($user, $article);
    }

    public function archiver(User $user, Article $article): bool
    {
        return $this->update($user, $article);
    }
}
