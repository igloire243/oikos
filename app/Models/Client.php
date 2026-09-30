<?php

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * UN CLIENT — une organisation qui paie. On lui vend par ENTITÉ (Lot C2), pas en bloc.
 *
 * @property int $id
 * @property string $nom
 * @property string|null $pays
 * @property string|null $ville
 * @property string|null $contact_nom
 * @property string|null $contact_email
 * @property string|null $contact_telephone
 * @property string|null $notes
 */
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['nom', 'pays', 'ville', 'contact_nom', 'contact_email', 'contact_telephone', 'notes'];

    /** @return HasMany<Installation, $this> */
    public function installations(): HasMany
    {
        return $this->hasMany(Installation::class);
    }
}
