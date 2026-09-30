<?php

namespace App\Metier\Licence;

use App\Metier\Journal\Journal;
use App\Models\CleActivation;
use App\Models\Installation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * LES CLÉS D'ACTIVATION — seul écrivain de `cles_activation`.
 *
 * Une clé courte, qui se dicte au téléphone : `OIKOS-XXXX-XXXX-XXXX`, sur un alphabet sans `O`,
 * `I`, `L`, `0` ni `1` — les caractères qu'on confond à voix haute. Elle est stockée HACHÉE,
 * s'affiche une seule fois, sert une seule fois, et expire (30 jours par défaut) : une clé qui
 * traîne dans un courriel ne doit pas rester une porte ouverte.
 */
class Cles
{
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const PREFIXE = 'OIKOS';

    /** @return array{cle: CleActivation, code: string} le code en clair, à montrer UNE fois */
    public static function emettre(Installation $installation, ?User $par, ?string $note = null, ?int $joursValidite = null): array
    {
        if ($installation->estDesactivee()) {
            throw ValidationException::withMessages([
                'installation' => 'Cette installation est désactivée : réactivez-la avant d\'émettre une clé.',
            ]);
        }

        $code = self::fabriquer();

        $cle = CleActivation::query()->create([
            'installation_id' => $installation->id,
            'code_hash' => self::hacher($code),
            'code_apercu' => substr($code, 0, 11).'…',
            'expire_le' => Carbon::now()->addDays($joursValidite ?? (int) config('oikos.cle_validite_jours', 30)),
            'note' => $note,
            'emise_par_id' => $par?->id,
        ]);

        Journal::tracer('CLE_EMISE', $installation, 'Clé d\'activation émise pour « '.$installation->libelle().' »', [
            'apercu' => $cle->code_apercu,
            'expire_le' => $cle->expire_le->toDateString(),
        ], $par);

        return ['cle' => $cle, 'code' => $code];
    }

    public static function revoquer(CleActivation $cle, ?User $par): void
    {
        if ($cle->etat() !== CleActivation::UTILISABLE) {
            throw ValidationException::withMessages(['cle' => 'Cette clé ne peut plus servir : rien à révoquer.']);
        }

        $cle->update(['revoquee_le' => Carbon::now()]);

        Journal::tracer('CLE_REVOQUEE', $cle->installation, 'Clé d\'activation révoquée : '.$cle->code_apercu, [], $par);
    }

    /** La clé qui correspond à ce code, quel que soit son état — ou null. */
    public static function parCode(?string $code): ?CleActivation
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        return CleActivation::query()->where('code_hash', self::hacher($code))->first();
    }

    /**
     * Pourquoi cette clé ne peut pas servir — ou null si elle le peut.
     *
     * Ici, et seulement ici, on dit la raison : la clé EST la bonne, le dire fait gagner un appel.
     * Pour un code inconnu, l'API répond un message unique et vague (voir `Activation`).
     */
    public static function raisonDuRefus(CleActivation $cle): ?string
    {
        return match ($cle->etat()) {
            CleActivation::UTILISEE => 'Cette clé a déjà servi. Demandez-en une nouvelle.',
            CleActivation::REVOQUEE => 'Cette clé a été révoquée. Demandez-en une nouvelle.',
            CleActivation::EXPIREE => 'Cette clé a expiré. Demandez-en une nouvelle.',
            default => null,
        };
    }

    /** « oikos 7k4p-a2b9 … » tapé au clavier redevient « OIKOS-7K4P-A2B9-… ». */
    public static function normaliser(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
    }

    public static function hacher(string $code): string
    {
        return hash('sha256', self::normaliser($code));
    }

    private static function fabriquer(): string
    {
        $groupes = [];

        for ($g = 0; $g < 3; $g++) {
            $groupe = '';
            for ($i = 0; $i < 4; $i++) {
                $groupe .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $groupes[] = $groupe;
        }

        return self::PREFIXE.'-'.implode('-', $groupes);
    }
}
