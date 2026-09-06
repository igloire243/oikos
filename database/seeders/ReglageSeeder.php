<?php

namespace Database\Seeders;

use App\Models\Paiement;
use App\Models\Reglage;
use Illuminate\Database\Seeder;

/**
 * LES RÉGLAGES DE DÉPART.
 *
 * `updateOrCreate` sur la CLÉ, et jamais sur la valeur : relancer ce seeder ajoute les réglages
 * apparus depuis, met à jour les libellés, et NE TOUCHE PAS aux valeurs que vous avez saisies.
 * Un seeder qui réécrirait les valeurs remettrait votre taux du jour à celui d'il y a six mois,
 * en silence, à la première mise à jour du produit.
 *
 * Les valeurs initiales sont lues dans config() — c'est-à-dire dans le .env existant. La bascule
 * du fichier vers la base se fait donc sans perte : ce que vous aviez déjà configuré se retrouve
 * dans l'écran, tel quel.
 */
class ReglageSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([...$this->general(), ...$this->paiement(), ...$this->modes()] as $reglage) {
            Reglage::updateOrCreate(
                ['cle' => $reglage['cle']],
                // La valeur n'est écrite qu'à la CRÉATION : `firstOrCreate` la poserait aussi,
                // mais ne mettrait pas à jour les libellés. On combine les deux comportements en
                // retirant `valeur` du tableau de mise à jour quand la ligne existe déjà.
                Reglage::where('cle', $reglage['cle'])->exists()
                    ? collect($reglage)->except('valeur')->all()
                    : $reglage
            );
        }
    }

    private function general(): array
    {
        return [
            [
                'cle' => 'taux_cdf',
                'valeur' => (string) config('paiement.taux_indicatif_cdf', 2800),
                'type' => 'entier',
                'groupe' => 'general',
                'libelle' => 'Taux du franc congolais',
                'aide' => "Combien de francs pour un dollar. Sert à proposer le prix en FC quand vous saisissez un prix en dollars. Il n'est jamais utilisé pour recalculer une facture déjà émise.",
                'ordre' => 10,
            ],
            [
                'cle' => 'essai_jours',
                'valeur' => '30',
                'type' => 'entier',
                'groupe' => 'general',
                'libelle' => "Durée d'essai (jours)",
                'aide' => "Ce dont dispose une installation activée mais pas encore facturée. Zéro fermerait le système à l'instant même de l'activation — ce n'est jamais ce qu'on veut.",
                'ordre' => 30,
            ],
            [
                'cle' => 'grace_jours',
                'valeur' => '14',
                'type' => 'entier',
                'groupe' => 'general',
                'libelle' => 'Délai de grâce (jours)',
                'aide' => "Après l'échéance, et aussi quand une installation n'arrive plus à joindre la console. C'est ce qui empêche une panne de VOTRE serveur de fermer une église un dimanche matin.",
                'ordre' => 40,
            ],
            [
                'cle' => 'devise_affichee',
                'valeur' => (string) config('paiement.devise_affichee', 'USD'),
                'type' => 'texte',
                'groupe' => 'general',
                'libelle' => 'Devise mise en avant',
                'aide' => 'USD ou CDF. Celle qui est écrite en gros sur la grille tarifaire ; la seconde reste visible à côté.',
                'ordre' => 20,
            ],
        ];
    }

    private function paiement(): array
    {
        return [
            [
                'cle' => 'paiement_titulaire',
                'valeur' => (string) config('paiement.titulaire', ''),
                'type' => 'texte',
                'groupe' => 'paiement',
                'libelle' => 'Titulaire des comptes',
                'aide' => "Affiché sur « Comment payer ». C'est ce qui permet à un client de vérifier qu'il ne verse pas à un homonyme, et de reconnaître une fausse coordonnée.",
                'ordre' => 10,
            ],
            [
                'cle' => 'paiement_delai',
                'valeur' => (string) config('paiement.delai_reouverture', '24 heures ouvrables'),
                'type' => 'texte',
                'groupe' => 'paiement',
                'libelle' => 'Délai de réouverture promis',
                'aide' => "C'est sur cette phrase que le client jugera le service le jour où son accès est bloqué. Ne promettez que ce que vous tenez.",
                'ordre' => 20,
            ],
        ];
    }

    /**
     * Un couple actif/numéro par mode d'encaissement, engendré depuis la liste du modèle Paiement
     * plutôt que recopié : ajouter un opérateur là-bas le fera apparaître ici au prochain passage,
     * sans qu'on puisse l'oublier.
     */
    private function modes(): array
    {
        $reglages = [];
        $ordre = 10;

        foreach (Paiement::FOURNISSEURS as $code => $libelle) {
            $depuisConfig = config('paiement.modes.'.$code, []);

            $reglages[] = [
                'cle' => 'mode_'.$code.'_actif',
                'valeur' => ($depuisConfig['actif'] ?? false) ? '1' : '0',
                'type' => 'booleen',
                'groupe' => 'modes',
                'libelle' => $libelle,
                'aide' => null,
                'ordre' => $ordre,
            ];

            // Les espèces n'ont pas de coordonnées : on ne verse pas sur un numéro, on se déplace.
            if ($code !== 'ESPECES') {
                $reglages[] = [
                    'cle' => 'mode_'.$code.'_numero',
                    'valeur' => (string) ($depuisConfig['numero'] ?? ''),
                    'type' => 'texte',
                    'groupe' => 'modes',
                    'libelle' => $libelle.' — numéro ou compte',
                    'aide' => 'Laissé vide, ce mode est retiré de la page publique même s\'il est actif : « Orange Money : — » n\'est pas un moyen de paiement.',
                    'ordre' => $ordre + 1,
                ];
            }

            if ($code === 'VIREMENT') {
                $reglages[] = [
                    'cle' => 'mode_VIREMENT_banque',
                    'valeur' => (string) ($depuisConfig['banque'] ?? ''),
                    'type' => 'texte',
                    'groupe' => 'modes',
                    'libelle' => 'Virement — nom de la banque',
                    'aide' => null,
                    'ordre' => $ordre + 2,
                ];
            }

            $ordre += 10;
        }

        return $reglages;
    }
}
