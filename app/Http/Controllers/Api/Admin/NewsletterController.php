<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\NewsletterMail;
use App\Models\Abonne;
use App\Support\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Mews\Purifier\Facades\Purifier;

class NewsletterController extends Controller
{
    /**
     * Compose et envoie immédiatement une newsletter à tous les abonnés actifs.
     */
    public function envoyer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'titre'   => 'required|string|max:255',
            'contenu' => 'required|string',
        ]);

        $contenu = Purifier::clean($data['contenu']);
        $abonnes = Abonne::actifs()->get();

        foreach ($abonnes as $abonne) {
            Mail::to($abonne->email)->queue(new NewsletterMail(
                $data['titre'],
                $contenu,
                $abonne->nom,
                rtrim(config('app.frontend_url'), '/') . '/desabonnement/' . $abonne->jeton,
            ));
        }

        AuditLog::record('Newsletter envoyée', [
            'titre'         => $data['titre'],
            'destinataires' => $abonnes->count(),
        ]);

        return response()->json([
            'message' => "Newsletter mise en file d'envoi pour {$abonnes->count()} abonné(s).",
        ]);
    }
}
