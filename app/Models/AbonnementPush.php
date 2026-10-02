<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UN APPAREIL ABONNÉ AUX NOTIFICATIONS PUSH D'UN COMPTE.
 *
 * `endpoint` est l'URL fournie par le navigateur (`PushManager.subscribe()`) : elle identifie
 * l'appareil pour le service qui le pousse (FCM pour Chrome, Mozilla pour Firefox…), jamais le
 * compte lui-même. Un compte peut en avoir plusieurs — un par appareil sur lequel il a accepté.
 *
 * @property int $id
 * @property int $user_id
 * @property string $endpoint
 * @property string $endpoint_hache
 * @property string $cle_p256dh
 * @property string $cle_auth
 */
class AbonnementPush extends Model
{
    protected $table = 'abonnements_push';

    /** @var list<string> */
    protected $fillable = ['user_id', 'endpoint', 'endpoint_hache', 'cle_p256dh', 'cle_auth'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
