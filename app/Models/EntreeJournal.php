<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * UNE ENTRÉE DU JOURNAL — elle ne se modifie jamais (pas d'`updated_at`). Voir `App\Metier\Journal\Journal`.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $sujet_type
 * @property int|null $sujet_id
 * @property string $libelle
 * @property array<string, mixed>|null $details
 * @property string|null $ip
 * @property Carbon $created_at
 */
class EntreeJournal extends Model
{
    protected $table = 'journal';

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['user_id', 'action', 'sujet_type', 'sujet_id', 'libelle', 'details', 'ip'];

    /** @var array<string, string> */
    protected $casts = ['details' => 'array', 'created_at' => 'datetime'];

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
