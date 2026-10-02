<?php

namespace Database\Seeders;

use App\Models\Entite;
use App\Models\Offre;
use Illuminate\Database\Seeder;

/**
 * LE CATALOGUE DE DÉPART — les prix de l'ancienne console, redécoupés sur les modules du produit v2.
 *
 * Premium porte `modules = null` à chaque étage : tout l'espace, ceux d'aujourd'hui ET ceux qu'une
 * mise à jour ajoutera. Starter et Standard énumèrent, et un module ajouté plus tard n'y entrera
 * pas tout seul — c'est ce qui donne une raison de monter en gamme.
 *
 * Rejouable : il met à jour par code, et ne touche pas une offre que la console aurait créée.
 */
class OffreSeeder extends Seeder
{
    private const VISION_STARTER = ['vision.comptes', 'vision.entites', 'vision.programmes', 'vision.rapports', 'vision.communications', 'vision.messagerie'];

    private const VISION_STANDARD = [...self::VISION_STARTER, 'vision.membres', 'vision.medias', 'vision.finances', 'vision.export'];

    private const ANTENNE_STARTER = ['antenne.extensions', 'antenne.bergers', 'antenne.membres', 'antenne.rapports', 'antenne.services', 'antenne.communications', 'antenne.messagerie'];

    private const ANTENNE_STANDARD = [...self::ANTENNE_STARTER, 'antenne.prieres', 'antenne.visites', 'antenne.reunions', 'antenne.departements', 'antenne.finances', 'antenne.medias', 'antenne.formations', 'antenne.export', 'antenne.delegations'];

    private const EGLISE_STARTER = [
        'extension.membres', 'extension.cultes', 'extension.tresorerie', 'extension.rapports', 'extension.equipes',
        'extension.messagerie', 'extension.espace_membre',
        'departement.equipe', 'departement.plannings', 'departement.rapports', 'departement.messagerie',
    ];

    private const EGLISE_STANDARD = [
        ...self::EGLISE_STARTER,
        'extension.programmes', 'extension.prieres', 'extension.visites', 'extension.activites', 'extension.discipulariat',
        'extension.formations', 'extension.medias', 'extension.discipline',
        'departement.programmes', 'departement.ressources',
    ];

    public function run(): void
    {
        foreach ($this->offres() as $offre) {
            Offre::query()->updateOrCreate(['code' => $offre['code']], $offre);
        }
    }

    /** @return list<array<string, mixed>> */
    private function offres(): array
    {
        $licence = fn (string $palier, string $nom, string $argumentaire, array $grille, ?array $modules, int $ordre) => [
            'code' => 'LICENCE_VISION_'.$palier,
            'nature' => Offre::LICENCE,
            'niveau' => Entite::VISION,
            'palier' => $palier,
            'nom' => $nom,
            'argumentaire' => $argumentaire,
            'periode_mois' => 12,
            'prix_usd_centimes' => $grille[0][0],
            'prix_cdf_centimes' => $grille[0][1],
            'paliers_taille' => array_map(fn ($max, $t) => ['max' => $max, 'prix_usd_centimes' => $t[0], 'prix_cdf_centimes' => $t[1]], [10, 50, 90, null], $grille),
            'plafond_acces' => $palier,
            'modules' => $modules,
            'publique' => true,
            'ordre' => $ordre,
        ];

        $acces = fn (string $niveau, string $palier, string $nom, string $argumentaire, int $usd, int $cdf, ?array $modules, int $ordre) => [
            'code' => 'ACCES_'.($niveau === Entite::ANTENNE ? 'ANTENNE' : 'EGLISE').'_'.$palier,
            'nature' => Offre::ACCES,
            'niveau' => $niveau,
            'palier' => $palier,
            'nom' => $nom,
            'argumentaire' => $argumentaire,
            'periode_mois' => 1,
            'prix_usd_centimes' => $usd,
            'prix_cdf_centimes' => $cdf,
            'paliers_taille' => null,
            'plafond_acces' => null,
            'modules' => $modules,
            'publique' => true,
            'ordre' => $ordre,
        ];

        return [
            $licence(Offre::STARTER, 'Licence Vision — Starter',
                'Met le système en service et ouvre l\'espace de la Vision : comptes, implantations, programmes, rapports, communications.',
                [[15000, 34500000], [26000, 59800000], [40000, 92000000], [60000, 138000000]], self::VISION_STARTER, 10),
            $licence(Offre::STANDARD, 'Licence Vision — Standard',
                'Ajoute le registre de la Vision, les médias, les finances consolidées et le centre d\'export. Autorise des accès Standard.',
                [[26000, 59800000], [44000, 101200000], [66000, 151800000], [100000, 230000000]], self::VISION_STANDARD, 11),
            $licence(Offre::PREMIUM, 'Licence Vision — Premium',
                'Tout l\'espace de la Vision, modules à venir compris. Autorise des accès Premium.',
                [[40000, 92000000], [69000, 158700000], [105000, 241500000], [156000, 358800000]], null, 12),

            $acces(Entite::EXTENSION, Offre::STARTER, 'Accès Église — Starter',
                'Le socle d\'une assemblée : membres, cultes et présences, trésorerie, rapports, équipes, l\'espace des fidèles et le pointage des départements.',
                500, 1150000, self::EGLISE_STARTER, 20),
            $acces(Entite::EXTENSION, Offre::STANDARD, 'Accès Église — Standard',
                'Ajoute programmes et calendrier, suivi pastoral, baptêmes, formations, médias, discipline, et les programmes internes des départements.',
                1000, 2300000, self::EGLISE_STANDARD, 21),
            $acces(Entite::EXTENSION, Offre::PREMIUM, 'Accès Église — Premium',
                'Tout l\'espace de l\'église et de ses départements, modules à venir compris.',
                1500, 3450000, null, 22),

            $acces(Entite::ANTENNE, Offre::STARTER, 'Accès Antenne — Starter',
                'Superviser ses églises : églises rattachées, bergers, annuaire régional, rapports, services, communications.',
                1000, 2300000, self::ANTENNE_STARTER, 30),
            $acces(Entite::ANTENNE, Offre::STANDARD, 'Accès Antenne — Standard',
                'Ajoute le suivi pastoral, les visites d\'églises, les réunions mensuelles, les départements, les finances, les formations et l\'export.',
                2000, 4600000, self::ANTENNE_STANDARD, 31),
            $acces(Entite::ANTENNE, Offre::PREMIUM, 'Accès Antenne — Premium',
                'Tout l\'espace de l\'antenne, supervision et dossiers disciplinaires compris, modules à venir compris.',
                3000, 6900000, null, 32),
        ];
    }
}
