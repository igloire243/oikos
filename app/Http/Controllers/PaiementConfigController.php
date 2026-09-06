<?php

namespace App\Http\Controllers;

use App\Models\Reglage;
use App\Support\Paiement\ConfigPasserelle;
use App\Support\Paiement\PasserellePaiement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * L'ÉCRAN /console/paiement — choisir l'agrégateur mobile money et saisir ses identifiants, sans
 * toucher au .env.
 *
 * LES SECRETS SONT MASQUÉS ET CHIFFRÉS. Un champ `secret` n'est jamais réaffiché : on montre
 * « ●●●●●● (défini) » ou « (vide) », et on ne réécrit sa valeur que si l'opérateur en saisit une
 * nouvelle. Le tout est rangé chiffré dans un seul réglage (voir ConfigPasserelle::enregistrerSecrets).
 */
class PaiementConfigController extends Controller
{
    public function edit()
    {
        return view('paiement.config', [
            'agregateurs' => ConfigPasserelle::AGREGATEURS,
            'choisi' => ConfigPasserelle::agregateur(),
            'actif' => ConfigPasserelle::actif(),
            'secrets' => ConfigPasserelle::secretsBruts(),
            'passerelle' => app(PasserellePaiement::class),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'agregateur' => ['required', Rule::in(array_keys(ConfigPasserelle::AGREGATEURS))],
            'actif' => ['nullable', 'boolean'],
            'champs' => ['array'],
            'champs.*' => ['nullable', 'string', 'max:2000'],
        ]);

        $agregateur = $data['agregateur'];
        $champsDef = ConfigPasserelle::AGREGATEURS[$agregateur]['champs'] ?? [];
        $existants = ConfigPasserelle::secretsBruts();
        $nouveaux = [];

        foreach ($champsDef as $cle => $def) {
            $saisi = $data['champs'][$cle] ?? null;

            if ($def['secret'] ?? false) {
                // Champ secret : on garde l'ancienne valeur si rien n'est saisi.
                $nouveaux[$cle] = ($saisi !== null && $saisi !== '')
                    ? $saisi
                    : ($existants[$cle] ?? '');
            } else {
                $nouveaux[$cle] = $saisi ?? ($def['defaut'] ?? '');
            }
        }

        ConfigPasserelle::enregistrerSecrets($nouveaux);

        $this->reglage('paiement_agregateur', $agregateur, 'Agrégateur mobile money');
        $this->reglage('paiement_actif', $request->boolean('actif') ? '1' : '0', "Encaissement en ligne activé");

        // Le singleton PasserellePaiement est reconstruit au prochain accès (nouvelle requête).
        return redirect()->route('paiement.config')
            ->with('ok', $agregateur === ConfigPasserelle::AUCUN
                ? "Encaissement en ligne désactivé — tout se fait à la main dans les Factures."
                : "Agrégateur « ".(ConfigPasserelle::AGREGATEURS[$agregateur]['nom'])." » enregistré.");
    }

    private function reglage(string $cle, string $valeur, string $libelle): void
    {
        Reglage::updateOrCreate(
            ['cle' => $cle],
            ['valeur' => $valeur, 'type' => 'texte', 'groupe' => 'paiement', 'libelle' => $libelle, 'ordre' => 990],
        );
    }
}
