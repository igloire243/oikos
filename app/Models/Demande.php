<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Une demande venue du site public.
 *
 * `$fillable` NE CONTIENT QUE CE QUE LE VISITEUR ÉCRIT. `statut`, `note_interne` et `traite_le`
 * en sont volontairement absents : ce sont VOS champs. S'ils y figuraient, un formulaire enrichi
 * d'un champ caché suffirait à faire arriver une demande déjà marquée « traitée », c'est-à-dire
 * invisible dans votre boîte de réception.
 */
class Demande extends Model
{
    protected $table = 'demandes';
    protected $primaryKey = 'demande_id';

    // La table porte ses propres dates (cree_le / traite_le) : Eloquent ne doit pas chercher
    // created_at ni updated_at.
    public $timestamps = false;

    public const NOUVELLE = 'NOUVELLE';
    public const LUE = 'LUE';
    public const TRAITEE = 'TRAITEE';
    public const SPAM = 'SPAM';

    public const STATUTS = [
        self::NOUVELLE => 'Nouvelle',
        self::LUE => 'Lue',
        self::TRAITEE => 'Traitée',
        self::SPAM => 'Indésirable',
    ];

    protected $fillable = [
        'nom', 'organisation', 'email', 'telephone', 'ville', 'niveau', 'message', 'ip', 'agent',
    ];

    protected $casts = [
        'cree_le' => 'datetime',
        'traite_le' => 'datetime',
    ];

    public function estOuverte(): bool
    {
        return in_array($this->statut, [self::NOUVELLE, self::LUE], true);
    }
}
