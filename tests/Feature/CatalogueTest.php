<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Support\Modules;
use Database\Seeders\PlanSeeder;
use ReflectionClass;
use Tests\TestCase;

/**
 * LE CATALOGUE DE LA CONSOLE DIT LA MÊME CHOSE QUE CELUI DU PRODUIT.
 *
 * Les clés des modules voyagent dans la licence : une clé qui diffère d'un caractère donne un module vendu et
 * jamais ouvert. Ces tests verrouillent ce que la console lit (`resources/catalogue/modules.json`, copie du
 * produit), les textes commerciaux, et que chaque offre n'utilise que des clés qui existent.
 */
class CatalogueTest extends TestCase
{
    /** @return array<string, mixed> */
    private function fichier(): array
    {
        return json_decode((string) file_get_contents(base_path('resources/catalogue/modules.json')), true);
    }

    public function test_l_empreinte_du_fichier_est_celle_des_cles_lues_par_la_console(): void
    {
        $this->assertSame($this->fichier()['empreinte'], Modules::calculerEmpreinte());
    }

    public function test_les_quatre_espaces_et_le_compte_du_produit(): void
    {
        $this->assertSame(['vision', 'antenne', 'extension', 'departement'], array_keys(Modules::espaces()));
        $this->assertCount(79, Modules::toutes());
        $this->assertCount(68, Modules::vendables());
    }

    public function test_aucune_ancienne_cle_ne_subsiste(): void
    {
        foreach (array_keys(Modules::toutes()) as $cle) {
            $this->assertDoesNotMatchRegularExpression('/^(superadmin|secteur|department|commission)\./', $cle);
        }
    }

    public function test_chaque_module_a_son_texte_et_aucun_texte_n_est_orphelin(): void
    {
        $textes = require base_path('resources/catalogue/textes.php');
        $cles = array_keys(Modules::toutes());

        $this->assertSame([], array_values(array_diff($cles, array_keys($textes))), 'Modules sans texte');
        $this->assertSame([], array_values(array_diff(array_keys($textes), $cles)), 'Textes sans module');
    }

    public function test_les_offres_n_utilisent_que_des_cles_vendables_qui_existent(): void
    {
        $vendables = array_keys(Modules::vendables());
        $constantes = (new ReflectionClass(PlanSeeder::class))->getConstants();

        foreach ($constantes as $nom => $cles) {
            if (! is_array($cles)) {
                continue;
            }

            $this->assertSame([], array_values(array_diff($cles, $vendables)), "{$nom} : clé inconnue ou invendable");
            $this->assertSame(count($cles), count(array_unique($cles)), "{$nom} : clé en double");
        }
    }

    public function test_les_natures_d_offre_ouvrent_les_bons_espaces(): void
    {
        $this->assertSame(['vision'], Modules::espacesPour(Plan::LICENCE));
        $this->assertSame(['antenne', 'extension', 'departement'], Modules::espacesPour(Plan::ACCES));
        $this->assertSame(['vision', 'antenne', 'extension', 'departement'], Modules::espacesPour(Plan::COMBINEE));
    }

    public function test_une_licence_ne_porte_que_des_modules_vendables(): void
    {
        $licence = Modules::pourLaLicence(['vision.comptes', 'vision.parametres', 'cle.inconnue']);

        $this->assertSame(['vision.comptes'], $licence);
    }
}
