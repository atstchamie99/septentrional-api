<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProgrammeSeeder extends Seeder
{
    /**
     * Grille hebdomadaire de Septentrional.
     * Données inspirées du mockup fourni + structure réelle.
     */
    public function run(): void
    {
        DB::table('programmes')->truncate();

        $grille = [
            // ── Jours de semaine (lundi → vendredi) ──────────────────────
            'lundi' => $this->grilleJourSemaine(),
            'mardi' => $this->grilleJourSemaine(),
            'mercredi' => $this->grilleJourSemaine(),
            'jeudi' => $this->grilleJourSemaine(),
            'vendredi' => $this->grilleJourSemaine(),

            // ── Samedi ────────────────────────────────────────────────────
            'samedi' => [
                ['06:00', '06:15', 'Journal du matin + spots pub', 'francais'],
                ['06:15', '06:40', 'Communiqués officiels', 'francais'],
                ['06:40', '07:00', 'Avis de décès en français', 'francais'],
                ['07:00', '07:05', 'Flash info Kabyè', 'kabye'],
                ['07:05', '08:00', 'Informations et communiqués en Kabyè', 'kabye'],
                ['08:00', '09:00', 'Revue de presse nationale', 'francais'],
                ['09:00', '10:00', 'Émission culturelle du week-end', 'francais'],
                ['10:00', '12:00', 'Musique et variétés', null],
                ['12:00', '12:30', 'Journal de midi', 'francais'],
                ['12:30', '14:00', 'Détente musicale', null],
                ['14:00', '16:00', 'Magazine du week-end', 'francais'],
                ['16:00', '18:00', 'Émission Kabyè du samedi', 'kabye'],
                ['18:00', '18:30', 'Journal du soir', 'francais'],
                ['18:30', '19:00', 'Infos en Kabyè', 'kabye'],
                ['19:00', '20:00', 'Émission religieuse', 'francais'],
                ['20:00', '22:00', 'Soirée musicale', null],
                ['22:00', '23:59', 'Nuit musicale', null],
            ],

            // ── Dimanche ─────────────────────────────────────────────────
            'dimanche' => [
                ['07:00', '08:00', 'Émission religieuse du dimanche', 'francais'],
                ['08:00', '09:00', 'Revue de presse dominicale', 'francais'],
                ['09:00', '10:00', 'Émission Ewé', 'ewe'],
                ['10:00', '12:00', 'Variétés et détente', null],
                ['12:00', '12:30', 'Journal de midi', 'francais'],
                ['12:30', '14:00', 'Musique africaine', null],
                ['14:00', '16:00', 'Émission spéciale dimanche', 'francais'],
                ['16:00', '18:00', 'Musique Kabyè', 'kabye'],
                ['18:00', '18:30', 'Journal du soir', 'francais'],
                ['18:30', '20:00', 'Émission culturelle', 'francais'],
                ['20:00', '22:00', 'Soirée musicale du dimanche', null],
                ['22:00', '23:59', 'Nuit musicale', null],
            ],
        ];

        $rows = [];
        foreach ($grille as $jour => $tranches) {
            foreach ($tranches as $i => [$debut, $fin, $titre, $langue]) {
                $rows[] = [
                    'jour'        => $jour,
                    'heure_debut' => $debut . ':00',
                    'heure_fin'   => $fin . ':00',
                    'titre'       => $titre,
                    'langue'      => $langue,
                    'ordre'       => $i,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ];
            }
        }

        DB::table('programmes')->insert($rows);

        $this->command->info('✅  ' . count($rows) . ' créneaux insérés dans la grille des programmes.');
    }

    /**
     * Grille type d'un jour de semaine.
     */
    private function grilleJourSemaine(): array
    {
        return [
            ['06:00', '06:15', 'Journal + spots pub', 'francais'],
            ['06:15', '06:40', 'Communiqués', 'francais'],
            ['06:40', '07:00', 'Avis de décès en français', 'francais'],
            ['07:00', '07:05', 'Flash information + pub Kabyè', 'kabye'],
            ['07:05', '07:55', 'Informations et communiqués en Kabyè, proverbe, prévention routière', 'kabye'],
            ['07:55', '08:00', 'Météo', 'francais'],
            ['08:00', '08:30', 'Revue de presse', 'francais'],
            ['08:30', '09:00', 'Communiqués officiels + spots', 'francais'],
            ['09:00', '10:00', 'Émission magazine du matin', 'francais'],
            ['10:00', '11:00', 'Émission Kabyè du matin', 'kabye'],
            ['11:00', '12:00', 'Musique et variétés', null],
            ['12:00', '12:30', 'Journal de midi', 'francais'],
            ['12:30', '13:00', 'Résumé info en Kabyè', 'kabye'],
            ['13:00', '14:00', 'Débat / magazine de l\'après-midi', 'francais'],
            ['14:00', '15:00', 'Émission Ewé', 'ewe'],
            ['15:00', '16:00', 'Musique africaine', null],
            ['16:00', '17:00', 'Émission jeunesse / éducation', 'francais'],
            ['17:00', '17:30', 'Flash info + agenda culturel', 'francais'],
            ['17:30', '18:00', 'Communiqués du soir', 'francais'],
            ['18:00', '18:30', 'Journal du soir', 'francais'],
            ['18:30', '19:00', 'Journal Kabyè du soir', 'kabye'],
            ['19:00', '20:00', 'Émission spéciale / magazine', 'francais'],
            ['20:00', '21:00', 'Détente musicale', null],
            ['21:00', '22:00', 'Émission nocturne', 'francais'],
            ['22:00', '23:59', 'Nuit musicale', null],
        ];
    }
}
