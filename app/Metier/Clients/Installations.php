<?php

namespace App\Metier\Clients;

use App\Metier\Journal\Journal;
use App\Models\Client;
use App\Models\Installation;
use App\Models\User;
use Illuminate\Support\Carbon;

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
}
