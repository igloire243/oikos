<?php

namespace App\Http\Controllers\Vitrine;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Reglage;
use App\Support\ModesPaiement;
use App\Support\Modules;

/**
 * LE SITE PUBLIC. Aucune authentification, et — c'est la contrepartie — aucune écriture.
 *
 * CE QUE CE CONTRÔLEUR A LE DROIT DE LIRE, ET RIEN D'AUTRE
 * --------------------------------------------------------
 *   · `plans` où is_public = true   — votre grille affichée. Les offres retirées de la vente et
 *     le tarif sur mesure, marqués false, n'en sortent jamais.
 *   · `clients` via scopeCitables() — seulement ceux qui ont accepté d'être nommés ET qui sont
 *     encore actifs.
 *
 * Il ne touche JAMAIS `installations`, `cle_hash`, `abonnements`, `factures` ni `paiements`.
 * Cette application détient les clés qui commandent les systèmes de vos clients ; la partie d'elle
 * qui est ouverte à tout Internet doit pouvoir se relire en une minute et ne rien contenir de plus
 * qu'une brochure.
 */
class SiteController extends Controller
{
    public function accueil()
    {
        $publics = $this->plansPublics();

        return view('vitrine.accueil', [
            // DEUX CHIFFRES SEULEMENT sur la page d'accueil : « une église seule, à partir de X »
            // et « un réseau, à partir de Y ». Le visiteur se reconnaît dans l'un des deux cas en
            // une seconde. Le détail attend la page Tarifs — annoncer trois familles ici
            // reproduirait la grille qu'on vient de simplifier.
            'departSeule' => $publics->get(Plan::COMBINEE)?->first(),
            'departLicence' => $publics->get(Plan::LICENCE)?->first(),
            // L'ACCES ANNONCE EST CELUI DU RESEAU. Les deux familles d'acces cohabitent dans la
            // meme nature ACCES ; sans ce tri, « + X par entite » aurait pu afficher le tarif de
            // l'eglise seule, trois fois plus cher, le jour ou un changement d'`ordre` remonte
            // ACCES_SEULE_* en tete.
            'departAcces' => $publics->get(Plan::ACCES)
                ?->first(fn ($p) => ! str_starts_with($p->code, 'ACCES_SEULE')),
            'departAccesSeule' => $publics->get(Plan::ACCES)
                ?->first(fn ($p) => str_starts_with($p->code, 'ACCES_SEULE')),
            // Les modules de l'espace d'une église : c'est ce qu'un visiteur reconnaît. Ceux de
            // la vision parlent d'un siège de réseau, et n'ont de sens qu'une fois qu'on sait de
            // quel produit on parle.
            'modulesEntite' => Modules::espace('secteur'),
            // On annonce les modules VENDABLES : compter les paramètres et la gestion des comptes
            // gonflerait le chiffre avec des pages que personne n'achète.
            'nombreModules' => count(Modules::vendables()),
            'nombreReferences' => Client::citables()->count(),
        ]);
    }

    public function tarifs()
    {
        return view('vitrine.tarifs', [
            'parNature' => $this->plansPublics(),
            'modes' => ModesPaiement::proposables(),
        ]);
    }

    public function fonctionnalites()
    {
        return view('vitrine.fonctionnalites', [
            // DEUX FAMILLES, ANNONCÉES COMME TELLES. Les présenter en une seule liste laissait
            // croire que le produit avait treize modules ; il en a vingt et un, répartis sur deux
            // espaces qui ne s'adressent pas aux mêmes personnes.
            // L'ordre compte : l'espace d'une église d'abord — c'est celui que le visiteur
            // reconnaît —, puis la vision, puis les espaces intermédiaires.
            'familles' => collect(['secteur', 'superadmin', 'antenne', 'department'])
                ->mapWithKeys(fn ($espace) => [$espace => Modules::espace($espace)])
                ->all(),
            'nomsFamilles' => collect(Modules::espaces())->map(fn ($e) => $e['libelle'])->all(),
            'nombreModules' => count(Modules::vendables()),
        ]);
    }

    public function paiement()
    {
        return view('vitrine.paiement', [
            'modes' => ModesPaiement::proposables(),
            // Lus en base : ce sont des promesses commerciales, modifiables depuis la console.
            // Le repli sur config() couvre l'installation neuve, dont la table est encore vide.
            'delai' => Reglage::valeur('paiement_delai', '24 heures ouvrables', 'paiement.delai_reouverture'),
            'titulaire' => Reglage::valeur('paiement_titulaire', '', 'paiement.titulaire'),
        ]);
    }

    public function references()
    {
        return view('vitrine.references', [
            // On ne sélectionne QUE les colonnes montrables. Un `get()` complet ferait voyager
            // jusqu'à la vue les notes internes et les coordonnées du pasteur : il suffirait
            // ensuite d'une erreur d'affichage — ou d'un @dump oublié — pour les publier.
            'clients' => Client::citables()
                ->orderBy('nom')
                ->get(['nom', 'ville', 'pays', 'temoignage', 'temoignage_auteur', 'site_url']),
        ]);
    }

    /**
     * Les offres publiques, groupées par NATURE et rangées dans l'ordre où on les vend.
     *
     * L'ordre des groupes est imposé à la main plutôt que laissé au hasard des clés : la vitrine
     * doit présenter d'abord l'église seule — le cas le plus fréquent et le plus simple — puis la
     * licence, puis les accès. Un `groupBy` restitue les groupes dans l'ordre où les lignes
     * arrivent, ce qui ferait dépendre la présentation commerciale d'un tri SQL.
     */
    private function plansPublics()
    {
        $ordreDesNatures = [Plan::COMBINEE => 1, Plan::LICENCE => 2, Plan::ACCES => 3];

        return Plan::where('is_public', true)
            ->orderBy('ordre')
            ->orderBy('prix_usd_cents')
            ->get()
            ->groupBy('nature')
            ->sortBy(fn ($plans, $nature) => $ordreDesNatures[$nature] ?? 9);
    }
}
