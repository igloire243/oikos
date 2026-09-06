<?php

namespace App\Support;

use App\Models\Plan;

/**
 * LE CATALOGUE DES MODULES, LU AU BON ENDROIT.
 *
 * Six espaces vivent dans config/modules.php, et ce fichier doit rester le miroir exact de
 * app/Support/Modules.php côté Génération Joël : ce sont ces clés qui voyagent dans la licence.
 * Une clé qui diffère d'un caractère entre les deux côtés produit un module vendu et jamais ouvert,
 * et le défaut ne se voit qu'à l'usage, chez le client.
 *
 * POURQUOI CETTE CLASSE PLUTÔT QUE config('modules') PARTOUT
 * -----------------------------------------------------------
 * Parce que « quels modules cette offre peut-elle ouvrir ? » a une réponse qui dépend de la NATURE
 * de l'offre, et que cette règle doit vivre à un seul endroit. Répartie dans les vues, elle finit
 * par diverger : le formulaire proposerait des cases qu'aucune grille n'affiche, et l'on vendrait
 * un module qui n'existe pas dans l'espace concerné.
 */
class Modules
{
    /**
     * Quels espaces une offre de cette nature ouvre-t-elle ?
     *
     *   LICENCE  → l'espace de la vision seul. Elle allume le système et ouvre le siège ; ce que
     *              font les entités en dessous relève de leurs propres accès.
     *   ACCES    → les espaces d'exploitation : antenne, église, département, commission. Une
     *              antenne et une cellule achètent le même palier, elles n'ouvrent simplement pas
     *              les mêmes pages.
     *   COMBINEE → tout. Une église seule EST sa propre vision : son installation a un compte
     *              superadmin, et il serait faux de lui refuser l'espace qu'elle possède forcément.
     */
    public const ESPACES_PAR_NATURE = [
        Plan::LICENCE => ['superadmin'],
        Plan::ACCES => ['antenne', 'secteur', 'department', 'commission'],
        Plan::COMBINEE => ['superadmin', 'antenne', 'secteur', 'department', 'commission'],
    ];

    /** @return array<string, array{libelle:string, modules:array}> */
    public static function espaces(): array
    {
        return config('modules', []);
    }

    public static function libelleEspace(string $espace): string
    {
        return self::espaces()[$espace]['libelle'] ?? $espace;
    }

    /** Les modules d'un espace, indexés par clé complète. */
    public static function espace(string $espace): array
    {
        return self::espaces()[$espace]['modules'] ?? [];
    }

    /** Tous les modules des six espaces, à plat. Les clés portant leur espace, rien ne se chevauche. */
    public static function toutes(): array
    {
        $tous = [];

        foreach (self::espaces() as $definition) {
            $tous += $definition['modules'];
        }

        return $tous;
    }

    /**
     * Ceux qu'un abonnement peut réellement fermer.
     *
     * Les paramètres, la gestion des comptes et le tableau de bord d'une commission en sont exclus :
     * les fermer empêcherait un client de réparer son installation — y compris pour venir payer.
     */
    public static function vendables(): array
    {
        return array_filter(self::toutes(), fn ($m) => $m['vendable'] ?? true);
    }

    public static function libelle(string $cle): string
    {
        return self::toutes()[$cle]['nom'] ?? $cle;
    }

    public static function icone(string $cle): string
    {
        return self::toutes()[$cle]['icone'] ?? 'dot';
    }

    /** @return array<int, string> */
    public static function espacesPour(string $nature): array
    {
        return self::ESPACES_PAR_NATURE[$nature] ?? array_keys(self::espaces());
    }

    /** Les clés qu'une offre de cette nature a le droit de contenir — ce que la validation vérifie. */
    public static function clesPour(string $nature): array
    {
        $cles = [];

        foreach (self::espacesPour($nature) as $espace) {
            $cles = array_merge($cles, array_keys(self::espace($espace)));
        }

        return $cles;
    }

    /**
     * Les modules VENDABLES d'une offre, prêts à partir dans une licence.
     *
     * Les invendables sont retirés du paquet plutôt qu'inclus : le produit les ouvre de toute façon,
     * et les faire voyager donnerait à croire qu'une offre pourrait un jour les fermer.
     *
     * @return array<int, string>|null  null = tous les modules, y compris ceux ajoutés plus tard
     */
    public static function pourLaLicence(?array $fonctionnalites): ?array
    {
        if ($fonctionnalites === null) {
            return null;
        }

        $vendables = array_keys(self::vendables());

        return array_values(array_intersect($fonctionnalites, $vendables));
    }
}
