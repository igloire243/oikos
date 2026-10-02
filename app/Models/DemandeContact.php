<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * UNE DEMANDE LAISSÉE SUR LE SITE COMMERCIAL.
 *
 * @property int $id
 * @property string $nom
 * @property string|null $organisation
 * @property string $email
 * @property string|null $telephone
 * @property string|null $pays
 * @property string $message
 * @property Carbon|null $traitee_le
 * @property int|null $traitee_par_id
 * @property string|null $note
 * @property Carbon $created_at
 */
class DemandeContact extends Model
{
    protected $table = 'demandes_contact';

    /** @var list<string> */
    protected $fillable = ['nom', 'organisation', 'email', 'telephone', 'pays', 'message', 'traitee_le', 'traitee_par_id', 'note'];

    /** @var array<string, string> */
    protected $casts = ['traitee_le' => 'datetime'];

    /** @return BelongsTo<User, $this> */
    public function traiteePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traitee_par_id');
    }

    public function estTraitee(): bool
    {
        return $this->traitee_le !== null;
    }
}
