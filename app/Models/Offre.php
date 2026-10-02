<?php

namespace App\Models;

use App\Metier\Catalogue\Modules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * UNE OFFRE — ce qu'on vend, et pour combien.
 *
 * Deux natures, deux rythmes : la LICENCE met le système en service et ouvre l'espace de la Vision
 * (annuelle : un réseau ne se met pas en place au mois) ; l'ACCÈS ouvre l'espace d'une antenne ou
 * d'une église (mensuel : on ajoute et on retire des églises en cours d'année).
 *
 * @property int $id
 * @property string $code
 * @property string $nature
 * @property string $niveau
 * @property string $palier
 * @property string $nom
 * @property string|null $argumentaire
 * @property int $periode_mois
 * @property int $prix_usd_centimes
 * @property int $prix_cdf_centimes
 * @property list<array{max: int|null, prix_usd_centimes: int, prix_cdf_centimes: int}>|null $paliers_taille
 * @property string|null $plafond_acces
 * @property list<string>|null $modules
 * @property bool $publique
 * @property int $ordre
 * @property Carbon|null $retiree_le
 */
class Offre extends Model
{
    public const LICENCE = 'LICENCE';

    public const ACCES = 'ACCES';

    /** @var array<string, string> */
    public const NATURES = [self::LICENCE => 'Licence', self::ACCES => 'Accès'];

    public const STARTER = 'STARTER';

    public const STANDARD = 'STANDARD';

    public const PREMIUM = 'PREMIUM';

    /** L'ordre compte : c'est lui qui dit qu'un Premium dépasse un Standard. */
    public const PALIERS = [self::STARTER => 'Starter', self::STANDARD => 'Standard', self::PREMIUM => 'Premium'];

    /**
     * Les espaces du produit qu'ouvre une offre de ce niveau. Une église emporte l'espace de ses
     * départements : un chef de chorale travaille pour l'église qui l'a ouvert, pas à part.
     *
     * @var array<string, list<string>>
     */
    public const ESPACES = [
        Entite::VISION => ['vision'],
        Entite::ANTENNE => ['antenne'],
        Entite::EXTENSION => ['extension', 'departement'],
    ];

    /** @var list<string> */
    protected $fillable = [
        'code', 'nature', 'niveau', 'palier', 'nom', 'argumentaire', 'periode_mois',
        'prix_usd_centimes', 'prix_cdf_centimes', 'paliers_taille', 'plafond_acces', 'modules',
        'publique', 'ordre', 'retiree_le',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'paliers_taille' => 'array',
        'modules' => 'array',
        'publique' => 'boolean',
        'retiree_le' => 'datetime',
        'periode_mois' => 'integer',
        'prix_usd_centimes' => 'integer',
        'prix_cdf_centimes' => 'integer',
        'ordre' => 'integer',
    ];

    /** @return HasMany<PeriodeAbonnement, $this> */
    public function periodes(): HasMany
    {
        return $this->hasMany(PeriodeAbonnement::class);
    }

    /** @param  Builder<Offre>  $requete */
    public function scopeEnVente(Builder $requete): void
    {
        $requete->whereNull('retiree_le');
    }

    public function estRetiree(): bool
    {
        return $this->retiree_le !== null;
    }

    public function estUneLicence(): bool
    {
        return $this->nature === self::LICENCE;
    }

    /** Ce palier tient-il sous ce plafond ? (Pas de Premium sous une licence Starter.) */
    public static function autorise(?string $plafond, string $palier): bool
    {
        $rangs = array_flip(array_keys(self::PALIERS));

        return $plafond !== null && ($rangs[$palier] ?? 99) <= ($rangs[$plafond] ?? -1);
    }

    /**
     * Les clés de modules que l'offre ouvre — toutes celles de ses espaces quand `modules` est
     * nul, vendables ou non : un module non vendable (les comptes, les délégations) vient avec
     * l'espace, il ne s'achète pas à part.
     *
     * @return list<string>
     */
    public function clesOuvertes(): array
    {
        $espaces = self::ESPACES[$this->niveau] ?? [];
        $cles = [];

        foreach (Modules::toutes() as $cle => $module) {
            if (! in_array($module['espace'], $espaces, true)) {
                continue;
            }
            if (! $module['vendable'] || $this->modules === null || in_array($cle, $this->modules, true)) {
                $cles[] = $cle;
            }
        }

        return $cles;
    }

    /**
     * Les modules VENDUS que l'offre ouvre, rangés par espace et nommés en toutes lettres.
     *
     * Une seule façon de la lire, pour l'écran « Offres » de l'opérateur ET pour la vitrine publique :
     * deux listes écrites chacune de son côté finiraient par promettre au visiteur autre chose que
     * ce que l'opérateur vend. Les écrans qui ne se vendent pas ne comptent pas : ils sont toujours
     * ouverts.
     *
     * @return list<array{espace: string, modules: list<string>}>
     */
    public function modulesParEspace(): array
    {
        return collect($this->clesOuvertes())
            ->filter(fn (string $cle) => Modules::estVendable($cle))
            ->groupBy(fn (string $cle) => Modules::toutes()[$cle]['espace'])
            ->map(fn ($cles, $espace) => [
                'espace' => Modules::libelleEspace((string) $espace),
                'modules' => $cles->map(fn (string $cle) => Modules::libelle($cle))->values()->all(),
            ])->values()->all();
    }

    /**
     * Le prix pour un réseau de cette taille, dans cette devise — la grille d'une licence, ou le
     * prix fixe de l'offre.
     */
    public function prixPour(string $devise, int $taille): int
    {
        $colonne = $devise === 'CDF' ? 'prix_cdf_centimes' : 'prix_usd_centimes';

        foreach ($this->paliers_taille ?? [] as $palier) {
            if ($palier['max'] === null || $taille <= $palier['max']) {
                return (int) $palier[$colonne];
            }
        }

        return (int) $this->{$colonne};
    }
}
