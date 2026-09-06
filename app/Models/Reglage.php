<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Un réglage modifiable depuis la console.
 *
 * TOUT EST LU EN UNE SEULE REQUÊTE, ET MISE EN CACHE. Le site public affiche le taux, la devise et
 * les coordonnées de paiement sur presque chaque page : les lire un par un ferait une dizaine de
 * requêtes par affichage. On charge la table entière — quelques dizaines de lignes — et on la garde
 * en cache jusqu'à la prochaine modification.
 *
 * LE CACHE EST VIDÉ À L'ÉCRITURE, PAS APRÈS UN DÉLAI. Un réglage changé doit se voir tout de suite :
 * un administrateur qui corrige un numéro de téléphone et voit l'ancien pendant dix minutes le
 * corrige une deuxième fois, puis appelle en disant que ça ne marche pas.
 */
class Reglage extends Model
{
    protected $table = 'reglages';
    protected $primaryKey = 'reglage_id';

    public const CLE_CACHE = 'reglages.tous';

    protected $fillable = ['cle', 'valeur', 'type', 'groupe', 'libelle', 'aide', 'ordre'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CLE_CACHE));
        static::deleted(fn () => Cache::forget(self::CLE_CACHE));
    }

    /** @return array<string, string|null> */
    public static function tous(): array
    {
        return Cache::rememberForever(self::CLE_CACHE, fn () => self::pluck('valeur', 'cle')->all());
    }

    /**
     * La valeur d'un réglage, convertie selon son type.
     *
     * LE REPLI SUR config() N'EST PAS UNE COMMODITÉ, C'EST UNE PROTECTION. Sur une installation
     * neuve, ou si le seeder n'a pas encore tourné, la table est vide : sans repli, la page des
     * tarifs afficherait « 0 FC » et les coordonnées de paiement disparaîtraient. Mieux vaut la
     * valeur d'origine que rien.
     */
    public static function valeur(string $cle, mixed $defaut = null, ?string $cleConfig = null): mixed
    {
        $brut = self::tous()[$cle] ?? null;

        if ($brut === null || $brut === '') {
            return $cleConfig ? config($cleConfig, $defaut) : $defaut;
        }

        return $brut;
    }

    public static function entier(string $cle, int $defaut = 0, ?string $cleConfig = null): int
    {
        return (int) self::valeur($cle, $defaut, $cleConfig);
    }

    public static function booleen(string $cle, bool $defaut = false, ?string $cleConfig = null): bool
    {
        $valeur = self::valeur($cle, $defaut, $cleConfig);

        // Les cases à cocher arrivent en « 1 » / « 0 », le .env en « true » / « false ».
        // filter_var traite les deux ; un simple (bool) rendrait true pour la chaîne « false ».
        return filter_var($valeur, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $defaut;
    }
}
