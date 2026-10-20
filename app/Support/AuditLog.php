<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class AuditLog
{
    /**
     * Journalise une action admin sensible (suppression, publication, etc.)
     * dans le channel "security", avec l'utilisateur et l'IP à l'origine de l'action.
     */
    public static function record(string $action, array $context = []): void
    {
        Log::channel('security')->info($action, array_merge([
            'user_id' => Request::user()?->id,
            'ip'      => Request::ip(),
        ], $context));
    }
}
