<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * UNE CLÉ D'ACTIVATION — courte, dictable, à usage unique. Voir `App\Metier\Licence\Cles`.
 *
 * Son état se LIT sur ses dates : utilisée, révoquée, expirée, ou encore utilisable.
 *
 * @property int $id
 * @property int $installation_id
 * @property string $code_hash
 * @property string $code_apercu
 * @property Carbon $expire_le
 * @property Carbon|null $utilisee_le
 * @property Carbon|null $revoquee_le
 * @property string|null $empreinte
 * @property string|null $ip
 * @property string|null $note
 * @property int|null $emise_par_id
 */
class CleActivation extends Model
{
    protected $table = 'cles_activation';

    public const UTILISABLE = 'UTILISABLE';

    public const UTILISEE = 'UTILISEE';

    public const REVOQUEE = 'REVOQUEE';

    public const EXPIREE = 'EXPIREE';

    /** @var array<string, string> */
    public const ETATS = [
        self::UTILISABLE => 'Utilisable',
        self::UTILISEE => 'Utilisée',
        self::REVOQUEE => 'Révoquée',
        self::EXPIREE => 'Expirée',
    ];

    /** @var list<string> */
    protected $fillable = [
        'installation_id', 'code_hash', 'code_apercu', 'expire_le', 'utilisee_le', 'revoquee_le',
        'empreinte', 'ip', 'note', 'emise_par_id',
    ];

    /** @var list<string> */
    protected $hidden = ['code_hash'];

    /** @var array<string, string> */
    protected $casts = ['expire_le' => 'datetime', 'utilisee_le' => 'datetime', 'revoquee_le' => 'datetime'];

    /** @return BelongsTo<Installation, $this> */
    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function emisePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emise_par_id');
    }

    public function etat(): string
    {
        return match (true) {
            $this->utilisee_le !== null => self::UTILISEE,
            $this->revoquee_le !== null => self::REVOQUEE,
            $this->expire_le->isPast() => self::EXPIREE,
            default => self::UTILISABLE,
        };
    }
}
