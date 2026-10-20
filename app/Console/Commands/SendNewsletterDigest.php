<?php

namespace App\Console\Commands;

use App\Mail\NewsletterMail;
use App\Models\Abonne;
use App\Models\Article;
use App\Support\AuditLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('app:send-newsletter-digest')]
#[Description('Envoie automatiquement un résumé hebdomadaire des derniers articles publiés à tous les abonnés actifs')]
class SendNewsletterDigest extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $articles = Article::publies()
            ->latest('date_publication')
            ->limit(5)
            ->get(['titre', 'slug', 'extrait']);

        if ($articles->isEmpty()) {
            $this->info('Aucun article récent, digest non envoyé.');

            return;
        }

        $abonnes = Abonne::actifs()->get();

        if ($abonnes->isEmpty()) {
            $this->info('Aucun abonné actif, digest non envoyé.');

            return;
        }

        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        $contenu = '<ul style="padding-left:18px;">';
        foreach ($articles as $article) {
            $contenu .= sprintf(
                '<li style="margin-bottom:12px;"><a href="%s/article/%s" style="color:#1b5e20; font-weight:bold; text-decoration:none;">%s</a><br><span style="color:#666;">%s</span></li>',
                $frontendUrl,
                $article->slug,
                e($article->titre),
                e($article->extrait ?? '')
            );
        }
        $contenu .= '</ul>';

        $titre = 'Cette semaine sur Septentrional';

        foreach ($abonnes as $abonne) {
            Mail::to($abonne->email)->queue(new NewsletterMail(
                $titre,
                $contenu,
                $abonne->nom,
                $frontendUrl . '/desabonnement/' . $abonne->jeton,
            ));
        }

        AuditLog::record('Newsletter hebdomadaire envoyée (automatique)', [
            'destinataires' => $abonnes->count(),
            'articles'      => $articles->count(),
        ]);

        $this->info("Digest envoyé à {$abonnes->count()} abonné(s).");
    }
}
