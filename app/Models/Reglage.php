<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * UN RÉGLAGE DE LA CONSOLE — voir `App\Metier\Console\Reglages`, seul écrivain.
 *
 * @property string $cle
 * @property int $valeur
 */
class Reglage extends Model
{
    protected $table = 'reglages';

    protected $primaryKey = 'cle';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['cle', 'valeur', 'modifie_le'];

    /** @var array<string, string> */
    protected $casts = ['valeur' => 'integer', 'modifie_le' => 'datetime'];
}
