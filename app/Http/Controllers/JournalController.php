<?php

namespace App\Http\Controllers;

use App\Models\EntreeJournal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LE JOURNAL DE LA CONSOLE — en LECTURE SEULE : ni purge, ni correction, ni export. Un journal qu'on
 * peut effacer depuis l'application ne prouve rien, et celui qui aurait une raison de l'effacer est
 * précisément celui qu'il faut pouvoir retracer (même règle que celui du produit).
 */
class JournalController extends Controller
{
    public function index(Request $requete): Response
    {
        $action = (string) $requete->query('action', '');

        $entrees = EntreeJournal::query()
            ->with('auteur')
            ->when($action !== '', fn ($q) => $q->where('action', $action))
            ->latest('id')
            ->paginate(40)
            ->withQueryString()
            ->through(fn (EntreeJournal $e) => [
                'id' => $e->id,
                'action' => $e->action,
                'libelle' => $e->libelle,
                'par' => data_get($e->auteur, 'name', 'Système'),
                'le' => $e->created_at->translatedFormat('D j M Y \à H\hi'),
                'sujet' => $e->sujet_type,
            ]);

        return Inertia::render('Console/Journal/Index', [
            'entrees' => $entrees,
            // Les familles réellement présentes, pas une liste figée qui oublierait la prochaine action.
            'actions' => EntreeJournal::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
            'filtre' => $action,
        ]);
    }
}
