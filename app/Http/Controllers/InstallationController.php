<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\EntreeJournal;
use App\Models\Installation;
use Illuminate\Http\Request;

class InstallationController extends Controller
{
    public function enregistrer(Request $request, Client $client)
    {
        $data = $request->validate([
            // LE NOM EST FACULTATIF, ET C'EST VOULU. L'installation le déclarera elle-même à son
            // activation — elle connaît le nom exact de la communauté, vous non. Laissé vide, il se
            // remplit tout seul ; saisi, il ne sera jamais écrasé. Voir App\Support\IdentiteRecue.
            'nom' => ['nullable', 'string', 'max:190'],

            // L'adresse, elle, MÉRITE d'être saisie si vous la connaissez : sans elle, la console
            // ne peut pas rappeler l'installation après un paiement, et la réouverture attend la
            // synchronisation de la nuit. L'installation la déclare aussi, mais seulement une fois
            // qu'elle a parlé — donc trop tard pour la toute première activation.
            'url' => ['nullable', 'url', 'max:255'],
        ], [
            'url.url' => "L'adresse doit être complète : https://eglise-bethel.cd",
        ]);

        $installation = new Installation([
            'client_id' => $client->client_id,
            'nom' => trim((string) ($data['nom'] ?? '')),
            'url' => $data['url'] ?? null,
            'cle_hash' => '',
            'cle_apercu' => '',
        ]);
        $installation->save();

        // UNE CLÉ EST POSÉE, MAIS ELLE N'EST MONTRÉE À PERSONNE — et c'est le point important.
        //
        // La colonne `cle_hash` ne peut pas rester vide ; on y met donc l'empreinte d'une clé
        // jetable. Sa valeur en clair n'intéresse personne : le produit ne lit AUCUNE clé dans son
        // .env, il en reçoit une à l'activation, et celle-ci remplace justement celle-là.
        //
        // L'afficher, comme le faisait cette page auparavant sous le nom de « clé d'installation »
        // à recopier dans le .env, ne pouvait qu'égarer : on donnait à recopier un secret que rien
        // ne lisait, et qui devenait faux dès la première activation.
        $installation->renouvelerCle();

        EntreeJournal::noter('INSTALLATION_CREEE', $installation, ['client' => $client->nom]);

        return back()->with('installation_neuve', $installation->installation_id);
    }

    /**
     * Renouveler la clé de synchronisation — c'est-à-dire RÉVOQUER l'ancienne.
     *
     * C'est un geste de coupure, pas de distribution : la clé neuve n'est montrée à personne parce
     * que personne ne la tape. L'installation cesse simplement de se synchroniser jusqu'à ce
     * qu'elle soit réactivée avec une clé d'activation, qui lui en remettra une elle-même.
     *
     * L'usage prévu : un serveur client dérobé, revendu, ou remplacé, dont on veut être sûr qu'il
     * ne parle plus à la console.
     */
    public function renouvelerCle(Installation $installation)
    {
        $installation->renouvelerCle();
        EntreeJournal::noter('CLE_RENOUVELEE', $installation);

        return back()->with('avertissement', "L'ancienne clé de synchronisation ne fonctionne plus. Cette "
            ."installation cessera de se synchroniser jusqu'à ce qu'elle soit RÉACTIVÉE avec une "
            ."clé d'activation neuve — c'est la réactivation qui lui remet une clé, il n'y a rien "
            .'à recopier à la main sur son serveur.');
    }

    public function basculer(Installation $installation)
    {
        $installation->update(['active' => ! $installation->active]);
        EntreeJournal::noter($installation->active ? 'INSTALLATION_REACTIVEE' : 'INSTALLATION_DESACTIVEE', $installation);

        return back()->with('ok', $installation->active
            ? 'Installation réactivée.'
            : 'Installation désactivée : elle ne peut plus se synchroniser.');
    }
}
