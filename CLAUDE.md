# oikos-console — la console d'abonnement

Le contexte complet (invariants, pièges, état d'avancement) est dans **`../CLAUDE.md`**, à la racine
de `testtech`. **Le lire en premier.** Le fonctionnement détaillé — modèle commercial, protocole,
encaissement, recette de test — est dans `README-OIKOS.md`, ici même.

Si Claude Code a été lancé depuis ce dossier plutôt que depuis le parent, `../CLAUDE.md` n'est pas
chargé automatiquement : le lire à la main, ou relancer depuis `testtech`.

## Propre à ce dépôt

- **Tailwind v4** (`@tailwindcss/vite`), palette émeraude, icônes **Lucide**. Le produit est en
  Tailwind v3 gris/bleu — ne pas transposer sans vérifier.
- **La frontière est dans l'URL** : tout ce qui est public vit à la racine (`vitrine.*`), tout ce
  qui vous appartient vit sous `/console`, connexion comprise. Une route d'administration égarée à
  la racine se voit immédiatement en relisant `routes/web.php`.
- **`routes/api.php` ne contient que deux routes**, et doit le rester : `/api/v1/activation` et
  `/api/v1/synchronisation`. C'est la seule surface que des serveurs appellent sans qu'un humain
  regarde.
- **Ce que la partie publique peut faire** : lire `plans` (publics) et `clients` (ceux qui ont
  accepté d'être cités), écrire dans `demandes`. Jamais `installations`, jamais `cle_hash`, jamais
  `factures`.
- **Les secrets** : la clé de synchronisation est stockée **hachée** (SHA-256) — elle ne se relit
  pas, elle se remplace. Le `rappel_jeton` est en clair, mais il ne permet que de déclencher notre
  propre appel sortant. `LICENCE_CLE_PRIVEE` est le seul secret dont la fuite se paie en
  abonnements non facturés.
- **La facture décide, pas le paiement** : on enregistre des versements, et c'est la facture qui
  dit quand elle est couverte. C'est ce qui fait marcher les paiements partiels sans code
  particulier. L'index unique `(fournisseur, reference)` empêche le double encaissement.

## Commandes

```bash
php artisan migrate
php artisan db:seed --class=ReglageSeeder
php artisan db:seed --class=PlanSeeder
php artisan oikos:admin
php artisan oikos:cles-signature
php artisan optimize:clear && npm run dev
```
