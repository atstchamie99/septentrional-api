<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Video;

class VideoPolicy extends OwnableContentPolicy
{
    public function publier(User $user, Video $video): bool
    {
        return $this->update($user, $video);
    }
}
