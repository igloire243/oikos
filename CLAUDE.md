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

**65 modules, 4 espaces, 58 vendables** — les mêmes chiffres que le produit, verrouillés par
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

**La page ne défile pas sur téléphone, le milieu défile.** Sur la capture d'un iPhone, une barre de
défilement grise courait sur toute la hauteur de l'écran, par-dessus l'en-tête et la barre du bas.
C'est l'indicateur de la FENÊTRE : le système le peint au-dessus des barres fixes, aucun CSS ne le
masque, et il paraît presque plein quand la page ne dépasse que de quelques pixels. Sous `lg`,
`LayoutConsole` est donc une colonne de la hauteur de l'écran (`100dvh`) — en-tête, zone qui défile
(`main[scroll-region]`, que le CSS sait taire), barre du bas. Le défilement-fenêtre vaut 0 ; Inertia
remet la zone en haut à chaque page grâce à `scroll-region`.

**Couleur de thème blanche**, comme le produit : verte, elle peignait une bande verte sous l'heure et
la batterie, par-dessus l'écran. **Données à jour au retour arrière** : `resources/js/fraicheur.js`,
le même module que le produit (Inertia réaffiche sinon les données gardées dans l'historique).



**Les notifications push de la console, et l'écran de démarrage.** `App\Metier\Notifications\
PushNotifications` est la même classe que celle du produit (clés VAPID : `php artisan
push:cles-vapid`, à copier dans le `.env`) ; la cloche de la barre du haut abonne l'APPAREIL, pas un
écran. Ce qui sonne n'est pas un geste mais le calendrier (`Alertes`, tâche `alertes:envoyer` à
8 h 11) : factures dépassées et abonnements qui s'achèvent sous 14 jours — une notification par jour
et par sujet, qui compte, jamais une par facture, et sans montant (elle s'affiche écran verrouillé).
L'écran de démarrage est une image par taille d'iPhone/iPad (`public/icons/demarrage/`, liens
`apple-touch-startup-image`) plus un écran dans la page pour l'application installée seulement,
retiré dès que Vue est monté. *Piège de poste* : `composer` ne peut pas télécharger depuis GitHub
dans la session distante ; les paquets du produit ont été copiés dans `vendor/` et déclarés dans
`installed.json` — un `composer install` normal, chez toi, n'y change rien.

**Vendables depuis la demande de l'utilisateur** : `vision.evenements` et `vision.commissions`
(65 modules / 58 vendables). L'offre Vision Standard les porte, comme `antenne.inventaire` et
`extension.inventaire` dans les offres Standard des étages du dessous.

**Le lot C3 est livré — factures et encaissements.** `console.factures.index` : une carte par
facture (mobile d'abord), filtres En attente / Partielles / En retard / Soldées, recherche par numéro,
client ou église, « reste à recevoir » par devise (jamais un total entre devises). Encaisser (montant
tapé « 12,50 », moyen, référence unique, date, note), marquer un versement « non reçu » avec son motif
et le rétablir : `Facturation` reste le seul écrivain, le contrôleur ne traduit que le formulaire.
L'historique d'un client montre, sous chaque période, le numéro et l'état de sa facture.
`FacturationTest` couvre la facture émise à la vente, partielle puis soldée, le trop-perçu refusé, la
référence unique, non reçu/rétabli, le rappel qui ne fait jamais échouer l'encaissement et la
numérotation par année. *Reste à faire côté console* : les demandes de contact, les réglages et le
journal (C4), le paiement en ligne (C5).

**Deux retouches demandées après la livraison de C3.** Sur « Offres », un clic sur le nombre de
modules déroule la liste de ce que l'offre ouvre vraiment, espace par espace (`clesOuvertes()`, la
même que celle que la licence sert — jamais une seconde lecture) ; les écrans qui ne se vendent pas
n'y comptent pas. Et les entités d'une installation reprennent l'**arborescence en dossiers** de
l'ancienne console : la Vision à la racine, ses antennes en sous-dossiers, leurs églises dedans
(`Composants/Console/NoeudEntite.vue`, récursif, deux premiers étages ouverts, nombre d'éléments
affiché une fois plié). Une église dont l'antenne n'a pas été remontée reste visible, dans un dossier
« Églises sans antenne » : la cacher ferait disparaître quelqu'un à qui l'on vend peut-être déjà.

**Le lot C4 est livré — le quotidien de l'opérateur.** `console.demandes.index` : ce que laissent les
visiteurs du formulaire public (`/demande`, seule porte publique de la console avec l'API machine —
champs bornés, cinq envois par minute, champ piège qui renvoie un faux succès aux robots sans rien
enregistrer) ; les plus anciennes non traitées d'abord, courriel et téléphone en liens nus, « traitée »
avec une note — on ne supprime pas une demande. `console.reglages.index` : les quatre durées de la
licence (essai, grâce, silence toléré, validité d'une clé), **en base** (`reglages`, une ligne par
durée, repli sur `config/oikos.php`) et lues par `EtatLicence`, `Abonnement` et `Cles` via
`Reglages::valeur()` — le même principe que les paramètres du produit : un réglage n'entre ici que si
une règle livrée le lit, et l'écran dit où il agit. `console.journal.index` : en lecture seule, filtré
par type, sans purge ni export (comme celui du produit). L'accueil montre d'abord **« À traiter »**,
qui reprend exactement les calculs de la notification du matin (`Alertes`) plus les installations
silencieuses : le tableau de bord ne dit jamais autre chose que ce que le téléphone a sonné.

*Reste du site commercial* : seul le formulaire de demande existe. Une vitrine complète (offres
publiques, tarifs, présentation) est à décider avec l'utilisateur — elle ne peut pas se construire sans
savoir ce qu'on y promet.

**Le site commercial est livré — la vitrine publique, à la racine.** `/` (présentation) et `/tarifs`,
plus le formulaire `/demande` du lot C4 ; la console reste sous `/console`. Rien n'y est promis qui
n'existe pas : pas de chiffre de clientèle, pas de témoignage, pas de bouton « acheter » tant qu'aucun
paiement n'est branché (C5) — la seule action est « Demander une offre ».

**Les tarifs sont LUS dans les offres, jamais écrits dans la page** (`App\Metier\Vitrine\Tarifs`) :
un prix tapé en dur serait une seconde copie, et le jour où l'opérateur change un tarif, le site
annoncerait l'ancien. Seules les offres PUBLIQUES et en vente s'affichent (une offre « négociée » ou
retirée n'apparaît jamais), dans les deux devises au choix du visiteur — jamais une conversion. La
liste des écrans d'une offre vient de `Offre::modulesParEspace()`, la même que celle de l'écran
« Offres » de l'opérateur : deux listes écrites chacune de leur côté promettraient au visiteur autre
chose que ce qui se vend. La page dit aussi comment ça s'assemble (licence d'abord, accès sous le
palier qu'elle permet, écrans de réglages toujours ouverts).

`Layouts/LayoutVitrine.vue` reprend la colonne `100dvh` du reste du produit sous `md` (milieu qui
défile, pied de page dans la zone) : aucun défilement de fenêtre à 390 px. La vitrine est **indexable**
(`<meta name="description">`, pas de `noindex`) alors que la console reste fermée aux moteurs de
recherche : c'est `routeIs('vitrine.*', 'demande.*')` qui décide, dans `app.blade.php`.
*Piège de mise en page* : sur téléphone, l'en-tête n'a la place que d'un bouton — « Connexion » passe
dans le menu, et le bouton d'action se raccourcit (« Nous écrire »).

**`php artisan oikos:cle-publique`** — retrouve la clé publique d'une clé privée déjà posée dans le
`.env`, sans rien générer. `oikos:cles-signature` n'affiche la publique qu'à la fabrication et refuse
d'en refaire une tant qu'il en existe une (une nouvelle paire invaliderait toutes les licences posées) :
perdre sa sortie ne perd donc pas la clé, elle se déduit de la privée.

**Le périmètre de l'application installée englobe le site public.** Le manifeste portait `scope:
/console` : le bouton « Site public » de la barre du haut mène à `/`, hors périmètre, et iOS ouvrait
alors la page dans une vue Safari — barre d'adresse et boutons compris, c'est-à-dire la mise en page
d'une page web ordinaire au lieu de celle d'une application. Le périmètre est maintenant `/` ; le
lancement reste sur `/console` (`start_url`). *À savoir* : un téléphone garde le manifeste lu à
l'installation — il faut retirer l'application de l'écran d'accueil et la réinstaller pour que le
nouveau périmètre s'applique.

---

## C5 — Payer en ligne (éteint par défaut)

`PAIEMENT_EN_LIGNE=true` l'allume ; éteint, chaque route `/payer/*` répond 404 et aucun lien n'est montré.

- **Le fournisseur confirme, le navigateur ne prouve rien.** `PaiementsEnLigne::confirmer()` INTERROGE le
  fournisseur (`Passerelle::verifier()`), vérifie montant et devise, puis encaisse par
  `Facturation::encaisser()` — la seule porte vers `paiements`, moyen `EN_LIGNE`. Le retour du client, la
  notification du fournisseur (`POST /payer/notification/{passerelle}`, hors CSRF) et un rechargement
  arrivent dans n'importe quel ordre : c'est idempotent (la référence du fournisseur est unique).
- **Une tentative n'est pas un paiement** : `demandes_paiement`, écrite avant de partir chez le fournisseur.
  Payé alors que la facture ne peut plus recevoir (soldée entre-temps) → demande ÉCHOUÉE « à rembourser » et
  trace au journal (`PAIEMENT_EN_LIGNE_A_REMBOURSER`) : de l'argent à rendre ne se tait pas.
- **L'adresse publique porte `factures.jeton_paiement`**, tiré au hasard — jamais le numéro, qui se devine.
  Elle paie TOUT le reste dû ; un versement partiel reste une saisie à la main.
- **Brancher un vrai fournisseur = UNE classe** implémentant `Passerelles\Passerelle` (`initier`, `verifier`)
  et une ligne dans `PaiementsEnLigne::passerelle()`. `PasserelleSimulee` (page de simulation) est
  **interdite en production**. 
- L'écran Factures offre « Copier le lien de paiement » quand l'option est allumée.


### Flutterwave (`Passerelles\Flutterwave`, API v3 « Standard »)

`PASSERELLE_PAIEMENT=flutterwave`, plus `FLUTTERWAVE_SECRET_KEY` et `FLUTTERWAVE_SECRET_HASH` dans le `.env`
(jamais dans le code). Le client paie sur la page hébergée du fournisseur ; aucune donnée de paiement ne passe
par la console. Dans le tableau de bord Flutterwave : adresse de notification =
`https://<console>/payer/notification/flutterwave`, et le « secret hash » saisi là est celui du `.env`.

- **Unités, pas centimes** : Flutterwave compte en 12.50 ; la conversion passe par `Montant`, la comparaison au
  retour se refait en centimes.
- **Seul `verify_by_reference` fait foi.** `?status=successful` sur l'adresse de retour ne prouve rien (testé).
- **La notification est authentifiée** par l'en-tête `verif-hash`. Sans secret configuré, TOUTE notification est
  refusée — un contrôle qui s'ouvre quand il n'est pas réglé n'en est pas un. Authentique, elle ne fait que
  DÉSIGNER une demande ; l'argent n'entre que par `verifier()` (`Passerelle::referenceNotifiee`).
- Fournisseur injoignable ou clé mauvaise : le client lit un message sur la page, pas une erreur 500.
- Non essayé contre le vrai service (pas de clés d'essai) : les tests rejouent le contrat avec `Http::fake`.
  Au premier essai réel, vérifier les moyens de paiement et devises ouverts sur le compte marchand.

**128 tests.**

### Les réglages de la console, élargis (paiement, facturation, éditeur)

L'écran « Réglages » n'avait que les quatre durées de la licence : on ne voyait pas le paiement en ligne, et
la clé du prestataire ne pouvait se poser que dans le `.env` du serveur. Il a maintenant quatre onglets —
Licence, Facturation, Paiement en ligne, Éditeur — et la même règle qu'au produit : **un réglage n'y entre que
si un code déjà livré le LIT** (la table `Reglages::AUTRES` dit lequel, colonne `lu_par`).

- `reglages.valeur` est un texte : durées, booléens, choix et **secrets chiffrés** (`Crypt`, jamais renvoyés à
  l'écran : on dit « posée (cet écran) » ou « posée (.env du serveur) », jamais ce qu'elle vaut). Le journal
  écrit « remplacé » ou « effacé », jamais la valeur d'un secret.
- Ce qui est saisi à l'écran l'emporte ; sinon le `.env` sert (`repli`) : un déploiement qui a posé ses clés
  dans le `.env` continue de marcher sans les ressaisir. Un champ secret laissé vide ne change rien.
- **Facturation** : le délai de paiement (15 jours par défaut) remplace la constante `Facture::DELAI_JOURS` ;
  il ne vaut que pour les factures émises ensuite.
- **Paiement en ligne** : allumer, choisir le prestataire, poser les clés Flutterwave, copier l'adresse de
  notification, **essayer la clé** (`Flutterwave::tester()`, une lecture authentifiée des soldes — le contrat
  est rejoué en test, jamais éprouvé contre le service réel).
- **Éditeur** : nom, e-mail, téléphone, adresse, lus par le pied du site commercial et la page de paiement
  (prop partagée `editeur`).

**139 tests.**

**Faire évoluer une offre en cours, et remettre une installation à l'essai.**
Une formule plus haute vendue en renouvellement attendait l'échéance de la précédente. `Ventes::appliquerMaintenant()`
(bouton « Appliquer dès aujourd'hui » dans l'historique d'une entité, ou case à cocher à la vente) fait commencer
la période aujourd'hui et raccourcit celle qu'elle remplace ; **la fin ne bouge pas** (le temps servi est le même,
la formule plus haute court pendant les jours qui restaient) et **rien n'est refacturé**. Les deux seules dates qu'on
réécrit gardent ce qui avait été vendu (`debut_vendu`, `fin_vendue`), tracé `ABONNEMENT_AVANCE`. Refusé si la
période a déjà commencé, si un trou ou une autre période s'intercale, ou si celle en cours commence aujourd'hui.
`Installations::remettreALEssai()` (bouton « Remettre à l'essai », motif obligatoire) résilie les abonnements en cours
— jamais effacés, une facture non soldée reste due — et pose `installations.essai_relance_le` : `EtatLicence` compte
l'essai depuis cette date et ignore une licence résiliée jusqu'à elle ; une licence vendue ensuite reprend la main.

**Catalogue : 71 modules / 60 vendables (R5 du produit ; `vision.assemblee` ajouté en R5b).** `vision.comite` (vendable, offre Standard) et
`vision.delegues` (non vendable) ajoutés ; `catalogue/modules.json` régénéré par `modules:exporter`.
