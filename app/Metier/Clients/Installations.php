<?php

namespace App\Metier\Clients;

use App\Metier\Journal\Journal;
use App\Metier\Licence\Rappel;
use App\Models\Client;
use App\Models\Installation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * LES CLIENTS ET LEURS INSTALLATIONS — seul écrivain hors API.
 *
 * On DÉSACTIVE une installation, on ne la supprime pas : ses entités portent (ou porteront) des
 * abonnements et des factures, et « qui avait accès en mars ? » doit garder une réponse.
 */
class Installations
{
    /** @param  array<string, mixed>  $donnees */
    public static function creerClient(array $donnees, ?User $par): Client
    {
        $client = Client::query()->create($donnees);

        Journal::tracer('CLIENT_CREE', $client, 'Client « '.$client->nom.' » créé', [], $par);

        return $client;
    }

    /** @param  array{nom?: string|null, url?: string|null}  $donnees */
    public static function ajouter(Client $client, array $donnees, ?User $par): Installation
    {
        $installation = $client->installations()->create([
            'nom' => $donnees['nom'] ?? null,
            'url' => $donnees['url'] ?? null,
        ]);

        Journal::tracer('INSTALLATION_AJOUTEE', $installation, 'Installation « '.$installation->libelle().' » ajoutée à « '.$client->nom.' »', [], $par);

        return $installation;
    }

    public static function desactiver(Installation $installation, ?User $par): void
    {
        if ($installation->estDesactivee()) {
            return;
        }

        $installation->forceFill(['desactivee_le' => Carbon::now()])->save();

        Journal::tracer('INSTALLATION_DESACTIVEE', $installation, 'Installation « '.$installation->libelle().' » désactivée', [], $par);
    }

    public static function reactiver(Installation $installation, ?User $par): void
    {
        if (! $installation->estDesactivee()) {
            return;
        }

        $installation->forceFill(['desactivee_le' => null])->save();

        Journal::tracer('INSTALLATION_REACTIVEE', $installation, 'Installation « '.$installation->libelle().' » réactivée', [], $par);
    }

    /**
     * Remet l'installation à l'état d'une installation qui vient d'être activée : tout ouvert, pour
     * la durée d'essai des réglages, comptée à partir d'aujourd'hui.
     *
     * Les abonnements en cours sont RÉSILIÉS (jamais effacés : périodes et factures restent
     * l'historique) — sans cela, une vente encore valable rouvrirait ses accès par-dessus l'essai.
     * Une facture non soldée reste due : la remise à l'essai n'efface pas une dette.
     */
    public static function remettreALEssai(Installation $installation, string $motif, ?User $par): void
    {
        if ($installation->estDesactivee()) {
            throw ValidationException::withMessages(['motif' => 'Cette installation est désactivée : réactivez-la d\'abord.']);
        }

        $maintenant = Carbon::now();

        $resilies = DB::transaction(function () use ($installation, $maintenant) {
            $abonnements = $installation->abonnements()->whereNull('resilie_le')->get();

            foreach ($abonnements as $abonnement) {
                $abonnement->forceFill(['resilie_le' => $maintenant, 'motif_resiliation' => 'Remise à l\'essai'])->save();
            }

            // Même instant que les résiliations : `EtatLicence` ignore une licence résiliée jusqu'à
            // cet instant inclus.
            $installation->forceFill(['essai_relance_le' => $maintenant])->save();

            return $abonnements->count();
        });

        Journal::tracer('INSTALLATION_REMISE_A_LESSAI', $installation, 'Installation « '.$installation->libelle().' » remise à l\'essai', [
            'motif' => $motif,
            'abonnements_resilies' => $resilies,
        ], $par);

        Rappel::prevenir($installation);
    }
}
