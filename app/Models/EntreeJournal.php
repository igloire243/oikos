<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Qui a fait quoi, et quand.
 *
 * Suspendre l'accès d'une église, confirmer un paiement, révoquer une clé : ces gestes ont des
 * conséquences chez quelqu'un d'autre. Six mois plus tard, « pourquoi cette église a-t-elle été
 * coupée le 3 août ? » doit avoir une réponse — sinon c'est votre parole contre la leur.
 */
class EntreeJournal extends Model
{
    protected $table = 'journal';
    protected $primaryKey = 'journal_id';
    public $timestamps = false;

    protected $fillable = ['user_id', 'action', 'cible_type', 'cible_id', 'details', 'ip', 'created_at'];

    protected $casts = ['details' => 'array', 'created_at' => 'datetime'];

    public static function noter(string $action, ?Model $cible = null, array $details = []): self
    {
        return static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'cible_type' => $cible ? class_basename($cible) : null,
            'cible_id' => $cible?->getKey(),
            'details' => $details ?: null,
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
