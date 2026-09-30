<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * UNE ENTITÉ REMONTÉE PAR UNE INSTALLATION — la Vision, une antenne, une église.
 *
 * Désignée comme l'installation la désigne elle-même : (type, ref). On ne la saisit jamais à la
 * main, et on ne la SUPPRIME jamais sur la foi d'un envoi où elle manque : un lot tronqué
 * effacerait un abonnement facturé. Une entité disparue se lit à sa date de dernière vue.
 *
 * Secteur et cellule ne sont qu'un `sous_type` : le produit les distingue par le nombre de membres,
 * et on ne facture jamais sur cette bascule (invariant n° 3 du produit).
 *
 * @property int $id
 * @property int $installation_id
 * @property string $type
 * @property int $ref
 * @property string $nom
 * @property string|null $sous_type
 * @property int|null $parent_ref
 * @property int|null $effectif
 * @property Carbon|null $vue_le
 */
class Entite extends Model
{
    public const VISION = 'VISION';

    public const ANTENNE = 'ANTENNE';

    public const EXTENSION = 'EXTENSION';

    /** @var array<string, string> */
    public const TYPES = [
        self::VISION => 'Vision',
        self::ANTENNE => 'Antenne',
        self::EXTENSION => 'Église',
    ];

    /** @var list<string> */
    protected $fillable = ['installation_id', 'type', 'ref', 'nom', 'sous_type', 'parent_ref', 'effectif', 'vue_le'];

    /** @var array<string, string> */
    protected $casts = ['vue_le' => 'datetime', 'ref' => 'integer', 'parent_ref' => 'integer', 'effectif' => 'integer'];

    /** @return BelongsTo<Installation, $this> */
    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    /** « EXTENSION:44 » — la désignation que la licence et le produit partagent. */
    public function reference(): string
    {
        return $this->type.':'.$this->ref;
    }
}
