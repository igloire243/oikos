<?php

namespace App\Support;

use App\Models\Abonnement;
use App\Models\Facture;
use App\Models\Installation;
use App\Models\Plan;
use App\Models\Reglage;

/**
 * CE QUE LA CONSOLE RÉPOND À UNE INSTALLATION QUI DEMANDE « de quoi ai-je le droit ? ».
 *
 * C'est le SEUL endroit où cette réponse se construit. L'activation et la synchronisation
 * nocturne renvoient exactement le même objet : deux canaux, un seul format. Écrire la réponse à
 * deux endroits finirait par produire deux vérités, et le client verrait ses modules changer selon
 * qu'il vient d'activer ou qu'il a attendu la nuit.
 *
 * CE QUE L'INSTALLATION EN FAIT
 * ------------------------------
 * Elle l'écrit dans `storage/licence.json` et s'y fie jusqu'à la prochaine synchronisation. D'où
 * `grace_jours` : si la console devient injoignable, l'installation continue sur cet état pendant
 * ce délai. Sans lui, une panne de VOTRE serveur fermerait une église un dimanche matin.
 *
 * LA LIMITE ASSUMÉE DE CETTE VERSION
 * ------------------------------------
 * `modules` est une liste unique pour TOUTE l'installation — l'union de ce que ses abonnements
 * ouvrent. Pour une église seule, c'est exact. Pour un réseau, c'est PERMISSIF : une antenne dont
 * l'abonnement est plus riche ouvre des pages à une église qui n'a pas payé autant. Le détail par
 * entité voyage déjà dans `entites` ; il ne sera appliqué que lorsque la barrière saura de quelle
 * entité relève la requête en cours. Mieux vaut l'écrire ici que le découvrir plus tard.
 */
class EtatLicence
{
    public static function pour(Installation $installation): array
    {
        $abonnements = $installation->abonnements()->with('plan')->get();

        $ouvrants = $abonnements->filter(fn (Abonnement $a) => $a->ouvreLEcriture());

        $etat = [
            'emis_le' => now()->toIso8601String(),
            'console' => config('app.url'),
            'communaute' => $installation->client?->nom,
            'installation' => $installation->nom,

            // L'EMPREINTE DE LA MACHINE À LAQUELLE CETTE LICENCE EST DESTINÉE.
            //
            // Elle entre dans le message signé, et c'est là tout son intérêt : le produit refuse
            // une licence qui ne porte pas SA propre empreinte. Sans elle, le `licence.json` d'un
            // client bien abonné, copié sur un autre serveur, y serait accepté — signature valide,
            // mais valide pour quelqu'un d'autre. Copier un fichier ne demande aucune compétence :
            // c'est exactement l'attaque qu'il faut fermer en premier.
            'empreinte' => $installation->empreinte,

            'statut' => $ouvrants->isEmpty() ? 'ESSAI' : 'ACTIF',
            'offre' => self::offrePrincipale($ouvrants),
            'fin' => self::echeance($ouvrants, $installation)?->toIso8601String(),
            'grace_jours' => Reglage::entier('grace_jours', 14),

            'modules' => self::modules($ouvrants),
            'quotas' => self::quotas($ouvrants),

            'entites' => self::entites($installation, $abonnements),

            // LA GRILLE DE TAILLES, POUR QUE LE CLIENT VOIE VENIR L'AUGMENTATION.
            //
            // Le prix d'une licence suit le nombre d'entités du réseau, et il est RECALCULÉ au
            // renouvellement (Cascade::prix()). Jusqu'ici, le client ne l'apprenait qu'en recevant
            // une facture plus élevée : rien, côté produit, ne disait ni combien d'entités il
            // déclarait, ni à partir de quand le tarif changeait. Une augmentation qu'on découvre
            // sur une facture passe pour une erreur de facturation.
            //
            // On envoie la GRILLE et non un prix calculé : le produit connaît son propre nombre
            // d'entités, en direct, alors que ce champ vieillirait entre deux synchronisations.
            'taille' => self::taille($ouvrants),

            // LE CADEAU DOIT SE VOIR, SINON IL NE COMPTE PAS.
            //
            // La promo de décembre repousse l'échéance des accès d'un mois, sans facture. Côté
            // client, cela se traduisait par une date qui avait bougé — et rien d'autre. Le
            // journal n'existe que dans la console. Un mois offert dont personne ne s'aperçoit est
            // un mois perdu en bonne volonté : autant ne pas l'offrir.
            'promo_decembre' => self::promoDecembre($ouvrants),

            // DE QUOI RÉPONDRE, CHEZ LE CLIENT, À « et je paie comment ? ».
            //
            // Sans ce bloc, l'écran d'abonnement du produit ne pouvait qu'afficher une date et se
            // taire. Un client qui lit « expire dans 6 jours » sans savoir ni combien, ni à qui, ni
            // avec quelle référence ne paie pas plus vite : il téléphone. C'est le seul endroit du
            // protocole où la console envoie quelque chose destiné aux YEUX du client plutôt qu'au
            // code du produit.
            'facturation' => self::facturation($installation),

            // COMBIEN DE TEMPS CETTE INSTALLATION PEUT SE TAIRE.
            //
            // Une licence signée reste valable jusqu'à sa date de fin, même hors ligne. Sans ce
            // compteur, débrancher le réseau suffirait à figer un abonnement jusqu'à son échéance,
            // puis à rejouer le même fichier indéfiniment. Passé ce délai sans échange réussi, le
            // produit se déclare périmé quoi que dise la date.
            'silence_jours' => (int) config('oikos.silence_jours', 45),
        ];

        // LA SIGNATURE EN DERNIER, sur tout ce qui précède. Elle ne se calcule qu'une fois le
        // tableau complet : ajouter un champ après coup produirait une signature qui ne couvre pas
        // ce qu'on envoie, c'est-à-dire une signature qui rassure sans rien protéger.
        //
        // Elle vaut null tant qu'aucune clé n'est configurée — le produit, de son côté, ne vérifie
        // que s'il détient une clé publique. Les deux moitiés s'allument séparément : c'est ce qui
        // permet de basculer un parc entier sans fermer personne. Voir SignatureLicence.
        $etat['signature'] = SignatureLicence::signer($etat);

        return $etat;
    }

    /**
     * Ce qu'il doit, et par quels moyens il peut le régler.
     *
     * TROIS PRÉCAUTIONS
     * ------------------
     *   · Les montants partent en CENTIMES, comme partout ailleurs ; le produit formatera. Envoyer
     *     « 12.30 » ferait entrer un flottant dans la chaîne, et les flottants finissent toujours
     *     par produire des totaux faux de quelques centimes.
     *
     *   · On n'envoie que les factures ÉMISES — ce qui est encore dû. Une facture soldée n'a rien à
     *     faire sur cet écran ; l'historique comptable complet est votre affaire, pas la sienne.
     *
     *   · Un mode actif mais sans coordonnées est déjà écarté par ModesPaiement. On ne refait pas
     *     ce filtre ici : le rejouer à la main, c'est l'occasion de l'oublier à moitié.
     */
    private static function facturation(Installation $installation): array
    {
        $modes = [];

        foreach (ModesPaiement::proposables() as $code => $mode) {
            $modes[] = [
                'code' => $code,
                'libelle' => $mode['libelle'],
                'numero' => $mode['numero'],
                'banque' => $mode['banque'],
                'instructions' => $mode['instructions'],
            ];
        }

        $factures = [];

        // Les factures de CE client, restreintes à CETTE installation dès qu'elles sont rattachées
        // à un abonnement. Un client qui exploite deux serveurs ne doit pas voir sur l'un les
        // impayés de l'autre : ce sont deux conversations différentes.
        if ($installation->client_id) {
            $requete = Facture::with(['paiements', 'abonnement.plan'])
                ->where('client_id', $installation->client_id)
                ->where('statut', Facture::EMISE)
                ->where(function ($q) use ($installation) {
                    $q->whereNull('abonnement_id')
                        ->orWhereHas('abonnement', fn ($a) => $a->where('installation_id', $installation->installation_id));
                })
                ->orderBy('du_le')
                ->limit(20);

            foreach ($requete->get() as $facture) {
                $factures[] = [
                    'numero' => $facture->numero,
                    'montant' => (int) $facture->montant,
                    'reste' => $facture->resteADevoir(),
                    'devise' => $facture->devise,
                    'du_le' => $facture->du_le?->toDateString(),
                    'objet' => $facture->abonnement?->plan?->nom,
                    // À quelle ENTITÉ cette facture se rapporte — « TYPE:ref » (VISION:1, ANTENNE:12,
                    // EXTENSION:44), ou null hors abonnement. Le produit s'en sert pour n'afficher,
                    // sur la page d'un espace, que les factures de cette entité-là.
                    'beneficiaire' => $facture->abonnement
                        ? $facture->abonnement->beneficiaire_type.':'.$facture->abonnement->beneficiaire_ref
                        : null,
                ];
            }
        }

        // L'encaissement en ligne (FlexPay) est-il branché ? Le produit s'en sert pour montrer, ou
        // non, un bouton « Payer maintenant » sur « Mon abonnement ». S'il est absent (vieille
        // console), le produit reste sur l'affichage « quoi payer, à qui » — comportement inchangé.
        $passerelle = app(\App\Support\Paiement\PasserellePaiement::class);
        $enLigne = $passerelle->estActive();

        return [
            'devise' => config('paiement.devise_affichee', 'USD'),
            'taux_cdf' => ModesPaiement::tauxCdf(),
            'titulaire' => trim((string) Reglage::valeur('titulaire', config('paiement.titulaire', ''))) ?: null,
            'delai' => trim((string) Reglage::valeur('delai_reouverture', config('paiement.delai_reouverture', ''))) ?: null,
            'modes' => $modes,
            'factures' => $factures,
            'encaissement_en_ligne' => $enLigne,
            'operateurs_en_ligne' => $enLigne
                ? array_map(
                    fn ($code) => ['code' => $code, 'libelle' => \App\Models\Paiement::FOURNISSEURS[$code]],
                    \App\Models\Paiement::ENCAISSABLES_EN_LIGNE,
                )
                : [],
        ];
    }

    /**
     * L'échéance qui fait autorité : la PLUS PROCHE de celles qui ouvrent l'écriture.
     *
     * Retenir la plus lointaine laisserait l'installation se croire couverte alors que sa licence
     * de structure est tombée — et la cascade, elle, ne pardonne pas.
     */
    private static function echeance($ouvrants, ?Installation $installation = null)
    {
        $fins = $ouvrants->pluck('periode_fin')->filter();

        if ($fins->isEmpty()) {
            // Aucun abonnement en cours : on accorde une période d'essai plutôt que de fermer une
            // installation qui vient de s'activer et n'a pas encore été facturée.
            //
            // ON RELIT LA DATE POSÉE À L'ACTIVATION, ON NE LA RECALCULE PAS. L'ancienne version
            // rendait `now() + essai_jours` à chaque émission : la synchronisation nocturne
            // repoussait donc l'échéance toutes les nuits, et l'essai ne se terminait jamais.
            // Voir la migration ajouter_essai_fin_aux_installations.
            //
            // Le repli sur un calcul ne sert qu'aux installations activées AVANT cette migration,
            // qui n'ont pas encore de date : elles en reçoivent une au prochain passage.
            if ($installation?->essai_fin) {
                return $installation->essai_fin;
            }

            if ($installation) {
                $installation->forceFill([
                    'essai_fin' => now()->addDays(Reglage::entier('essai_jours', 30)),
                ])->save();

                return $installation->essai_fin;
            }

            return now()->addDays(Reglage::entier('essai_jours', 30));
        }

        return $fins->min();
    }

    /**
     * La grille de tailles de la licence en cours — null si son offre n'en a pas (accès, offre à
     * prix fixe, essai).
     *
     * Les échelons partent tels quels : `max` (plafond de l'échelon, null pour le dernier) et les
     * deux prix. Le produit y cherche lui-même la ligne qui correspond à son effectif d'entités.
     *
     * @return array<string, mixed>|null
     */
    private static function taille($ouvrants): ?array
    {
        $licence = $ouvrants->first(fn (Abonnement $a) => in_array(
            $a->plan?->nature, [Plan::LICENCE, Plan::COMBINEE], true
        ));

        if (! $licence?->plan?->suitLaTaille()) {
            return null;
        }

        $plan = $licence->plan;

        $taille = [
            'offre' => $plan->nom,
            'periode_mois' => (int) $plan->periode_mois,
            'socle_usd_cents' => (int) $plan->prix_usd_cents,
            'socle_cdf' => (int) $plan->prix_cdf,
            'par_entite_usd_cents' => null,
            'par_entite_cdf' => null,
            'echelons' => null,
        ];

        if ($plan->aUnTarifParEntite()) {
            $taille['par_entite_usd_cents'] = (int) $plan->prix_par_entite_usd_cents;
            $taille['par_entite_cdf'] = (int) $plan->prix_par_entite_cdf;

            return $taille;
        }

        // Repli : une offre signée à l'ancien mode (tranches) continue de s'afficher comme elle a
        // été vendue. Voir Plan::prixPourTaille().
        $taille['echelons'] = array_map(fn (array $e) => [
            'max' => isset($e['max']) ? (int) $e['max'] : null,
            'prix_usd_cents' => (int) ($e['prix_usd_cents'] ?? 0),
            'prix_cdf' => (int) ($e['prix_cdf'] ?? 0),
        ], $plan->paliers_taille ?: []);

        return $taille;
    }

    /**
     * Le mois offert le plus récent, et sur combien d'entités — ou null si aucune promo.
     *
     * On rend l'ANNÉE et non un booléen : le produit doit pouvoir décider s'il l'affiche encore
     * (un « décembre 2026 offert » lu en juin 2027 n'intéresse plus personne), ce qu'un simple
     * drapeau ne permettrait pas.
     *
     * @return array{annee: int, entites: int}|null
     */
    private static function promoDecembre($ouvrants): ?array
    {
        $annees = $ouvrants->pluck('promo_decembre_annee')->filter();

        if ($annees->isEmpty()) {
            return null;
        }

        $recente = (int) $annees->max();

        return [
            'annee' => $recente,
            'entites' => $ouvrants->filter(
                fn ($a) => (int) $a->promo_decembre_annee === $recente
            )->count(),
        ];
    }

    private static function offrePrincipale($ouvrants): ?string
    {
        $licence = $ouvrants->first(fn (Abonnement $a) => in_array(
            $a->plan?->nature, [Plan::LICENCE, Plan::COMBINEE], true
        ));

        return ($licence ?? $ouvrants->first())?->plan?->nom;
    }

    /**
     * Les modules ouverts, en clés complètes « espace.module ».
     *
     * `null` dès qu'UNE offre ouvre tout : la nuance compte, une licence à null recevra les modules
     * ajoutés au produit après sa signature, une liste figée non.
     *
     * @return array<int, string>|null
     */
    private static function modules($ouvrants): ?array
    {
        if ($ouvrants->isEmpty()) {
            return null;   // essai : tout ouvert, le temps de voir le produit
        }

        $cles = [];

        foreach ($ouvrants as $abonnement) {
            $duPlan = Modules::pourLaLicence($abonnement->plan?->fonctionnalites);

            if ($duPlan === null) {
                return null;
            }

            $cles = array_merge($cles, $duPlan);
        }

        return array_values(array_unique($cles));
    }

    /**
     * Le quota le plus GÉNÉREUX parmi les abonnements ouvrants.
     *
     * « Sans limite » (null) l'emporte sur n'importe quel chiffre, et se traite dans une seconde
     * passe : mélangé au calcul du maximum, il devient zéro à la première comparaison — c'est-à-dire
     * l'inverse exact de ce qu'il veut dire.
     */
    private static function quotas($ouvrants): array
    {
        $quotas = [];
        $illimites = [];

        foreach ($ouvrants as $abonnement) {
            foreach (($abonnement->plan?->quotas ?? []) as $cle => $valeur) {
                if ($valeur === null) {
                    $illimites[$cle] = true;

                    continue;
                }

                $quotas[$cle] = max($quotas[$cle] ?? 0, (int) $valeur);
            }
        }

        foreach (array_keys($illimites) as $cle) {
            $quotas[$cle] = null;
        }

        return $quotas;
    }

    /**
     * Le détail par entité, désigné comme l'installation les désigne elle-même (type + référence).
     *
     * Pas de clé étrangère vers `entites` : une resynchronisation peut recréer ces lignes, alors
     * qu'un abonnement ne doit jamais perdre son bénéficiaire.
     */
    private static function entites(Installation $installation, $abonnements): array
    {
        $detail = [];

        foreach ($abonnements as $abonnement) {
            $reference = $abonnement->beneficiaire_type.':'.$abonnement->beneficiaire_ref;

            $detail[$reference] = [
                'offre' => $abonnement->plan?->nom,
                'statut' => $abonnement->statut,
                'ouvre_ecriture' => $abonnement->ouvreLEcriture(),
                'fin' => $abonnement->periode_fin?->toIso8601String(),
                'modules' => Modules::pourLaLicence($abonnement->plan?->fonctionnalites),
            ];
        }

        return $detail;
    }
}
