<?php

namespace App\Metier\Licence;

use App\Metier\Catalogue\Modules;
use App\Metier\Clients\IdentiteRecue;
use App\Metier\Journal\Journal;
use App\Models\Installation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * L'ACTIVATION — le seul moment où une clé courte sert.
 *
 * Une installation présente une clé et son empreinte ; la console vérifie, consomme la clé, émet
 * une NOUVELLE clé de synchronisation et rend l'état de licence. Cette route ne crée rien : le
 * client et l'installation existent déjà dans la console avant qu'une clé soit émise. Une route
 * publique qui créerait des lignes serait un moyen de remplir la base depuis Internet.
 *
 * Pourquoi renouveler la clé de synchronisation ICI : la base n'en garde que l'empreinte, elle ne
 * peut donc pas la relire — la seule façon d'en remettre une est d'en fabriquer une neuve. Effet
 * heureux : réactiver invalide la précédente, et un vieux serveur oublié cesse de se synchroniser.
 */
class Activation
{
    /**
     * @param  array<string, mixed>  $donnees  validées par le contrôleur
     * @return array{jeton: string, licence: array<string, mixed>}
     *
     * @throws ActivationRefusee
     */
    public static function activer(array $donnees, ?string $ip): array
    {
        $cle = Cles::parCode($donnees['cle'] ?? null);

        // Un message unique et vague pour un code inconnu : préciser « inconnue » plutôt
        // qu'« expirée » aiderait qui essaie des clés au hasard à savoir quand il approche.
        if ($cle === null) {
            throw new ActivationRefusee("Clé d'activation invalide.");
        }

        if ($raison = Cles::raisonDuRefus($cle)) {
            throw new ActivationRefusee($raison);
        }

        /** @var Installation $installation */
        $installation = $cle->installation;

        if ($installation->estDesactivee()) {
            throw new ActivationRefusee('Cette installation est désactivée. Contactez votre fournisseur.');
        }

        // UNE MACHINE, UNE INSTALLATION. Si cette empreinte est déjà rattachée ailleurs, c'est
        // qu'on active le même serveur sous deux fiches : l'accepter ferait facturer deux fois,
        // ou ouvrir l'abonnement de l'une à l'autre. L'opérateur tranche.
        $ailleurs = Installation::query()
            ->where('empreinte', $donnees['empreinte'])
            ->whereKeyNot($installation->id)
            ->first();

        if ($ailleurs !== null) {
            throw new ActivationRefusee('Ce serveur est déjà rattaché à une autre installation. Contactez votre fournisseur.');
        }

        $jeton = Str::random(48);

        DB::transaction(function () use ($installation, $cle, $donnees, $ip, $jeton) {
            // AVANT la sauvegarde : la carte de visite peut renseigner le nom de l'installation.
            $remplis = IdentiteRecue::appliquer($installation, $donnees['identite'] ?? null);

            $installation->forceFill([
                'empreinte' => $donnees['empreinte'],
                'cle_synchro_hash' => hash('sha256', $jeton),
                'cle_synchro_apercu' => substr($jeton, 0, 6).'…',
                'url' => $donnees['url'] ?? $installation->url,
                'version' => $donnees['version'] ?? $installation->version,
                'rappel_jeton' => $donnees['rappel'] ?? $installation->rappel_jeton,
                'catalogue_empreinte' => $donnees['catalogue'] ?? $installation->catalogue_empreinte,
                // La PREMIÈRE activation seulement : c'est d'elle que part l'essai, et réactiver
                // un serveur (changement de disque, réinstallation) ne doit pas l'offrir à nouveau.
                'activee_le' => $installation->activee_le ?? Carbon::now(),
                'vue_le' => Carbon::now(),
            ])->save();

            $cle->forceFill(['utilisee_le' => Carbon::now(), 'empreinte' => $donnees['empreinte'], 'ip' => $ip])->save();

            Journal::tracer('INSTALLATION_ACTIVEE', $installation, 'Installation « '.$installation->libelle().' » activée', [
                'empreinte' => $donnees['empreinte'],
                'url' => $donnees['url'] ?? null,
                // Ce que la fiche a gagné toute seule : on distinguera plus tard ce que l'opérateur a
                // saisi de ce que l'installation a déclaré.
                'fiche_remplie' => $remplis,
                'catalogue_different' => isset($donnees['catalogue']) && $donnees['catalogue'] !== Modules::empreinte(),
            ]);
        });

        return ['jeton' => $jeton, 'licence' => EtatLicence::pour($installation->refresh())];
    }
}
