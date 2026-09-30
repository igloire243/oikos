# oikos-console — contexte de travail

La console de l'**éditeur** d'Oikos : on y tient les clients, leurs installations, les offres, les
abonnements, les factures, et on y **signe les licences** que le produit lit. Reconstruite à zéro
sur la branche `claude/console-v2` ; l'ancienne console reste sur `master`, **uniquement comme
référence** (son manuel `README-OIKOS.md` y est lisible avec `git show master:README-OIKOS.md`).

Le produit est `generation-joel-v2` (dépôt `igloire243/Mgj`). Les deux applications ne partagent
**aucune table** : la console ne connaît d'une installation que ce qu'elle lui pousse (un arbre
d'entités, des compteurs, une carte de visite), et l'installation ne reçoit de la console qu'un
**état signé** — jamais un ordre, jamais du code, jamais une donnée à écrire.

---

## Le stack — le même que le produit

| Brique | Version | À savoir |
|---|---|---|
| PHP | 8.4 | sous Git Bash : `export PATH="/c/Users/hp/.config/herd/bin/php84:$PATH"` |
| Laravel | 13.x | |
| Jetstream | 5.5, **Inertia 2 + Vue 3**, sans Teams | inscription **désactivée**, suppression de compte **désactivée**, double authentification **active** |
| Tailwind | **v3.4** | une seule version dans le projet |
| Base | MySQL `oikos_console` | tests sur `oikos_console_test` — jamais SQLite |
| Port | **8001** | 8000 = ancien produit, 8002 = produit v2 |

---

## Langue et style

Les mêmes règles que le produit : **tout en français** sauf ce que le framework possède ; les
commentaires disent **pourquoi**, jamais quoi ; un bug corrigé s'écrit dans le code.

---

## Les invariants

### 1. Tout ce qui appartient à l'éditeur vit sous `/console`

Connexion comprise (`config/fortify.php` → `prefix`), profil compris (les routes de Jetstream sont
remontées sous `/console` par `routes/web.php`). La racine reviendra au site commercial (Lot C4) ;
une route d'administration égarée à la racine se voit en relisant `routes/web.php`.

**Pas d'inscription publique.** La console détient la clé qui signe les licences de tout le parc :
un compte d'opérateur se crée depuis le serveur, `php artisan oikos:operateur`, jamais depuis un
formulaire qu'Internet peut remplir.

### 2. Le catalogue appartient au PRODUIT

`resources/catalogue/modules.json` est l'export de `App\Metier\Acces\Modules` du produit, copié tel
quel ; `App\Metier\Catalogue\Modules` le lit. **On ne l'édite pas à la main** : on le régénère côté
produit et on recopie le fichier. L'écran « Catalogue » est en lecture seule, pour la même raison.

L'ancienne console tenait sa propre liste (`config/modules.php`), recopiée à la main, et rien ne
vérifiait que les deux restaient identiques : une clé d'un seul côté donnait un module vendu que
rien n'ouvre, ou ouvert que rien ne facture. Ici le fichier porte une **empreinte** (SHA-256 des
clés triées) : un test vérifie qu'elle correspond à ses clés — une copie retouchée se trahit — et
chaque installation annoncera l'empreinte de son propre catalogue à la synchronisation (Lot C1).

**64 modules, 4 espaces, 51 vendables** — les mêmes chiffres que le produit, verrouillés par
`tests/Feature/Catalogue/CatalogueTest.php`.

### 3. À reprendre de l'ancienne console, et à corriger (lots suivants)

Ces règles viennent de l'ancien manuel ; elles s'écriront dans le code au lot indiqué, avec leurs
tests. Les défauts connus de l'ancienne version sont notés pour ne pas les refaire.

- **On vend à l'ENTITÉ, pas au client** (C2) : douze églises = douze abonnements, douze échéances.
- **La cascade** (C2) : un accès ne peut exister que sous une licence en cours, et ne peut jamais
  lui survivre. *Défaut de l'ancienne : le prorata à l'émission était annoncé publiquement mais
  « pas encore contraint dans le code » — ici il l'est.*
- **La licence est signée** RSA-SHA256 par openssl (C1) — l'extension obligatoire de Laravel, pas
  sodium, absente par défaut sous Windows : une vérification qui échoue faute d'extension fermerait
  un client honnête. Le message signé est trié par clés ; la signature couvre l'**empreinte** de la
  machine destinataire.
- **Deux secrets, deux sens** (C1) : la clé de synchronisation (produit → console, gardée en
  SHA-256, renouvelée à chaque activation) et le jeton de rappel (console → produit, qui ne peut
  que demander à l'installation de se resynchroniser).
- **La carte de visite ne remplit que le VIDE** (C1) : ce que l'opérateur a saisi n'est jamais
  écrasé par une synchronisation.
- **La facture décide, pas le paiement** (C3) : versements partiels, **référence d'opérateur unique**
  en base, un versement annoncé mais jamais reçu se marque « non reçu », il ne s'efface pas.
  L'ordre des écritures : l'argent, puis l'accès, puis le rappel — **hors transaction**.
- **Lecture ouverte, écriture fermée** côté produit quand l'abonnement tombe (P1) : on ne coupe pas
  une église de ses données le jour où une facture traîne.
- *Défaut de l'ancienne : les modules étaient ouverts pour TOUTE l'installation (l'union des
  abonnements), si bien qu'une antenne bien abonnée ouvrait ses écrans à une église qui n'avait pas
  payé autant. La barrière `module:` du produit (P1) lira le détail PAR ENTITÉ.*

---

## Le design

Les composants viennent du produit (`resources/js/Composants/` : `EnTetePage`, `CarteStat`,
`Badge`, `Bouton`, `Tableau`, `Modale`, `Onglets`, `FiltreBoutons`, `Pagination`, `Champ*`,
`LienTelechargement`, `Graphiques/*`), avec ses règles : le téléphone d'abord, 44 px par cible, un
tableau devient des cartes, `min-w-0` sur un enfant de grille.

La couleur de la console est un **violet** qu'aucun espace du produit ne porte, posé en variables
`--marque-*` sur `:root` (`app.css`) : une modale est téléportée dans `<body>`, hors de la mise en
page. `Layouts/LayoutConsole.vue` porte la barre latérale, le tiroir et la barre du bas ; son menu
vient de `App\Metier\Console\Menu` — une entrée sans écran reste visible, marquée « à venir ».

Les props partagées `auth`, `menu` et `flash` sont **réservées** : une prop de page du même nom
les écraserait sans erreur (piège vécu côté produit).

---

## Les pièges déjà rencontrés

**GitHub refuse les archives de paquets depuis le conteneur de travail** (API 403), alors que `git`
passe. `composer install --prefer-source` clone au lieu de télécharger. `phpstan/phpstan` n'existe
qu'en archive : il a été installé depuis la copie du produit, puis `composer.lock` a été remis sur
l'adresse officielle — **un lock ne doit jamais pointer vers un chemin local**, il casserait
l'installation sur un autre poste.

**Jetstream appelle `axios` comme un global** : `resources/js/http.js` fournit une instance nommée,
importée par les deux composants qui s'en servent. Même correctif que le produit.

---

## Commandes

```bash
composer install && npm install --ignore-scripts && npm run build
php artisan migrate
php artisan oikos:operateur vous@exemple.test --nom="Votre nom"
php artisan serve --port=8001          # puis /console/login

composer qualite                       # Pint + PHPStan niveau 6 + Pest
npm run lint && npm run format
```

---

## Où en est le travail

| Lot | Contenu | État |
|---|---|---|
| **C0 — Socle** | stack, authentification des opérateurs, design, catalogue miroir 64/51 | **livré** |
| C1 — Le branchement | clients, installations, clés d'activation, API d'activation et de synchronisation, licence signée | à venir |
| C2 — Vendre | offres, abonnements par entité, cascade et prorata contraint, renouvellement | à venir |
| C3 — Encaisser | factures, paiements partiels, référence unique, rappel de l'installation | à venir |
| C4 — La vitrine | site commercial, réglages, tableau de bord, demandes de contact | à venir |
| P1 — Côté produit | activation, synchronisation, licence vérifiée, barrière `module:` par entité | à venir (dépôt du produit) |
| C5 — Paiement en ligne | passerelles mobile money, éteint par défaut | à venir |

**26 tests.**
