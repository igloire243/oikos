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

**64 modules, 4 espaces, 55 vendables** — les mêmes chiffres que le produit, verrouillés par
`tests/Feature/Catalogue/CatalogueTest.php`. Le fichier porte aussi une liste `inclus` — les écrans
de « Mon Église » (ouverts d'un bloc par `extension.espace_membre`) et ce qui vit dans tous les
espaces (la Bible, la recherche, les notifications) : affichés sur l'écran Catalogue pour qu'on sache
tout ce qui existe, **hors empreinte**, puisque aucune licence ne les ouvre un par un. Le fichier se
régénère côté produit par `php artisan modules:exporter --vers=<chemin de ce fichier>`.

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
| **C1 — Le branchement** | clients, installations, clés d'activation, API d'activation et de synchronisation, licence signée | **livré** |
| **C2 — Vendre** | offres, abonnements par entité, cascade et prorata contraint, renouvellement | **livré** |
| C3 — Encaisser | factures, paiements partiels, référence unique, rappel de l'installation | à venir |
| C4 — La vitrine | site commercial, réglages, tableau de bord, demandes de contact | à venir |
| P1 — Côté produit | activation, synchronisation, licence vérifiée, barrière `module:` par entité | à venir (dépôt du produit) |
| C5 — Paiement en ligne | passerelles mobile money, éteint par défaut | à venir |

### Le Lot C1, en détail

| Écran / route | Ce qu'il fait |
|---|---|
| `console.clients.index` | les clients, cherchables, avec leur nombre d'installations |
| `console.clients.show` | la fiche : chaque installation avec son **état lu sur ses dates**, l'arbre d'entités qu'elle a remonté, ses clés, et la clé émise **montrée une seule fois** |
| `POST /api/v1/activation` | une clé courte contre une clé de synchronisation et une licence signée |
| `POST /api/v1/synchronisation` | l'arbre, les compteurs, la carte de visite montent ; l'état signé descend |

Les écrivains : `App\Metier\Licence\Cles` (seul écrivain de `cles_activation`), `Activation`,
`Synchronisation`, `EtatLicence` (le **seul** endroit où la réponse se construit — activer et
synchroniser rendent le même objet), `Signature`, `Rappel` ; `App\Metier\Clients\Installations`
côté écran ; `App\Metier\Journal\Journal::tracer()` pour chaque geste qui se conteste.

Ce qui a été corrigé par rapport à l'ancienne console, et que les tests verrouillent :

- **L'essai a une fin FIXE**, calculée depuis la première activation (`activee_le`). L'ancienne
  répondait « maintenant + 30 jours » à chaque appel : un essai repoussé chaque nuit, donc éternel.
  Réactiver un serveur ne l'offre pas une seconde fois.
- **Une machine, une installation** : une empreinte déjà rattachée à une autre fiche est refusée —
  sinon un même serveur se facture deux fois, ou ouvre l'abonnement de l'une à l'autre.
- **Aucune route publique ne crée de ligne** : le client et l'installation existent avant que la
  clé soit émise. Une API qui créerait des fiches remplirait la base depuis Internet.
- **Une entité absente d'une synchronisation n'est jamais supprimée** : l'envoi peut être tronqué.
- **Le jeton de rappel est chiffré en base** (cast `encrypted`) : une copie de la base ne suffit pas
  à réveiller le parc. La clé de synchronisation, elle, n'est gardée qu'en SHA-256.
- **Un code inconnu reçoit un refus vague** ; une clé connue mais usée, révoquée ou expirée dit
  pourquoi — la bonne clé mérite qu'on fasse gagner un appel, un essai au hasard non.

`modules` vaut `null` (tout ouvert) et `entites` est vide tant que rien n'est vendu : c'est le
Lot C2 qui les remplit, et la barrière `module:` du produit (P1) qui les lira.

**46 tests.**

### Le Lot C2, en détail

| Écran / route | Ce qu'il fait |
|---|---|
| `console.offres.index` | le catalogue commercial : licences (annuelles, grille de taille, plafond d'accès) et accès (mensuels) ; modules cochés espace par espace, ou « tous, futurs compris » |
| `console.clients.show` | chaque entité de l'arbre montre son abonnement ; « Vendre » ouvre l'aperçu (période, prix, prorata) calculé par le serveur ; l'historique et la résiliation |

`App\Metier\Commerce\Ventes` est le **seul écrivain** des abonnements ; `Offres`, des offres.
Ce qu'ils tiennent, et que les tests verrouillent :

- **Un abonnement par ENTITÉ** (index unique), et des **périodes** qui ne se modifient pas, prix
  FIGÉ dedans : changer le prix d'une offre ne change pas ce qui a été vendu. Pas de colonne
  `statut` — l'état se lit sur les périodes et `resilie_le`.
- **Un seul geste pour vendre, renouveler et reprendre** : la date proposée est le lendemain de la
  dernière période ; une période qui chevauche est refusée, une antidate de plus d'un mois aussi.
- **La cascade** : pas d'accès sans licence en cours à sa date de début, pas d'accès au-dessus du
  plafond de la licence. **Le prorata est contraint** : l'accès est coupé à la fin de la licence et
  son prix réduit au jour près (l'ancienne console l'annonçait sans l'appliquer).
- **La survie est tenue deux fois** : à la vente, et dans `EtatLicence`, qui ne sert plus aucun
  accès quand la licence est résiliée ou expirée.
- **La licence parle par entité** (`entites` : offre, palier, fin, modules) — c'est ce que la
  barrière `module:` du produit lira (P1). Un module non vendable vient avec l'espace ; un accès
  d'église ouvre aussi l'espace de ses départements.
- **Pas de quotas** : l'ancienne en vendait que le produit n'appliquait pas.

**63 tests.**

## La PWA et la couleur

**Vert WhatsApp**, par les variables `--marque-*` de `resources/css/app.css` (400 `#25D366`, 600
`#128C7E`, 800 `#075E54`). Le rang 500 est volontairement un cran plus profond : `.marque-fond` pose
du texte BLANC dessus, et blanc sur `#25D366` ne fait que 2:1. Les composants Jetstream écrits en
`indigo` suivent la même palette (`tailwind.config.js`) au lieu de rester violets.

**Application installable** : `public/manifest.webmanifest` (cible `/console`, la racine reviendra
au site commercial), `public/sw.js`, `public/hors-ligne.html`, icônes dans `public/icons/` (SVG
sources `icone*.svg`, PNG rendus par Chromium, `favicon.ico` assemblé à la main). Le service worker
ne garde que `/build/` : **jamais une page** — une licence échue lue depuis un cache mentirait, et
resterait lisible sur un téléphone prêté. Le bouton « Installer » (`Composables/installation.js`)
n'apparaît que si le navigateur émet `beforeinstallprompt`. `tests/Feature/PwaTest.php` vérifie que
chaque icône du manifeste existe : sinon le navigateur ne propose rien, sans erreur visible.
