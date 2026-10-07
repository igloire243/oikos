<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Tighten\Ziggy\Ziggy;

/**
 * LES ROUTES, EN FICHIER ET NON DANS CHAQUE PAGE (même correctif que le produit).
 *
 * `@routes` collait la liste complète des routes dans l'en-tête de CHAQUE page HTML. Servies ici, avec une adresse
 * qui porte la version (`?v=`), elles se téléchargent une fois puis restent dans le cache du navigateur et du service
 * worker. Pas de session ni de cookie : une réponse qu'on veut mettre en cache ne doit rien poser.
 */
class ZiggyController extends Controller
{
    public function __invoke(): Response
    {
        return response('const Ziggy = '.json_encode((new Ziggy)->toArray(), JSON_UNESCAPED_SLASHES).';', 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    /** Change quand un fichier de routes change — l'adresse du script en dépend. */
    public static function version(): string
    {
        return substr(md5(implode('|', array_map(
            fn (string $fichier) => is_file($fichier) ? (string) filemtime($fichier) : '0',
            [base_path('routes/web.php'), base_path('routes/api.php'), base_path('composer.lock')],
        ))), 0, 10);
    }
}
