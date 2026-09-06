# Oikos — manuel de fonctionnement

**Ce document décrit ce qui EXISTE et comment le faire tourner.**
Il ne décrit pas des intentions. `README-SAAS.md` (dans `generation-joel`) est le document de
conception — le raisonnement, les arbitrages, ce qui reste à décider. Celui-ci est le mode d'emploi :
de la base vide au premier abonnement payé et activé.

Il est écrit pour être suivi dans l'ordre au moins une fois. Ensuite il sert de référence.

---

## Sommaire

1. [Les deux applications, et où passe la frontière](#1-les-deux-applications)
2. [Le vocabulaire — cinq mots à ne jamais confondre](#2-le-vocabulaire)
3. [Le modèle commercial : licence + accès](#3-le-modèle-commercial)
4. [Mettre la console en route depuis une base vide](#4-mettre-la-console-en-route)
5. [Installer le produit depuis une base vide](#5-installer-le-produit)
6. [La liaison — comment un produit et la console se parlent](#6-la-liaison)
7. [Vendre, facturer, encaisser](#7-vendre-facturer-encaisser)
8. [Activer l'abonnement côté produit](#8-activer-labonnement)
9. [Ce que la licence ferme, et ce qu'elle ne ferme pas](#9-ce-que-la-licence-ferme)
10. [Les trois manières dont une installation apprend qu'elle est payée](#10-les-trois-rafraîchissements)
11. **[Le test complet depuis zéro — la recette pas à pas](#11-le-test-complet-depuis-zéro)** ← commencez ici pour tester
12. [Dépannage](#12-dépannage)
13. [Ce qui n'est pas encore fait](#13-ce-qui-nest-pas-encore-fait)

---

## 1. Les deux applications

Deux dépôts, deux bases de données, deux serveurs. Ils ne partagent **aucune** table.

```
┌───────────────────────────────────────┐        ┌───────────────────────────────────────┐
│           OIKOS-CONSOLE               │        │          GENERATION-JOEL              │
│           (vous, l'éditeur)           │        │          (le client, une église)      │
│                                       │        │                                       │
│  /                → site public       │  HTTPS │  /                → site de l'église   │
│  /tarifs, /contact…                   │◄──────►│  /admin           → les 6 espaces      │
│  /console         → votre admin       │        │  /installer       → l'installateur     │
│  /api/v1/…        → les installations │        │  /oikos/rafraichir→ canal entrant      │
│                                       │        │                                       │
│  Base : clients, installations,       │        │  Base : membres, cultes, offrandes,    │
│  entites, plans, abonnements,         │        │  départements… — RIEN de commercial.   │
│  factures, paiements, cles_activation │        │  La licence vit dans storage/, pas     │
│                                       │        │  en base.                              │
└───────────────────────────────────────┘        └───────────────────────────────────────┘
        UNE console                                    N installations (une par client)
```

**La règle qui explique tout le reste :** la console ne connaît des installations que ce qu'elles
lui poussent — un **arbre d'entités** (antennes, églises, cellules) et des **compteurs** (membres,
comptes). Jamais un membre, jamais une offrande, jamais un contenu. À l'inverse, une installation ne
reçoit de la console qu'un **état** — jamais un ordre, jamais du code, jamais une donnée à écrire.

### Les URL, côté console

| Adresse | Qui | Quoi |
|---|---|---|
| `/` `/tarifs` `/fonctionnalites` `/comment-payer` `/references` `/contact` | tout le monde | le site commercial |
| `/console/connexion` | vous | connexion (+ mot de passe oublié) |
| `/console` | vous | tableau de bord, clients, offres, factures, demandes, réglages |
| `/api/v1/activation` · `/api/v1/synchronisation` | les serveurs clients | les deux seules routes machine |

Tout ce qui vous appartient est sous `/console`, connexion comprise. Une route d'administration
égarée à la racine se voit immédiatement en relisant `routes/web.php`.

---

## 2. Le vocabulaire

Ces cinq mots reviennent partout. Les confondre est la première source d'erreur.

| Mot | Ce que c'est | Où il vit |
|---|---|---|
| **Client** | une organisation qui vous paie. Un nom, un contact, une facturation. | console, table `clients` |
| **Installation** | **un serveur** où tourne une copie du produit. Un client peut en avoir plusieurs (production, recette). | console, table `installations` |
| **Entité** | ce que l'installation contient : une VISION, une ANTENNE, une EXTENSION (église/cellule). Remontée par la synchronisation, jamais saisie à la main. | console, table `entites` |
| **Abonnement** | **une vente, rattachée à une ENTITÉ**, pas au client en bloc. Douze églises = douze abonnements, douze échéances. | console, table `abonnements` |
| **Licence** (côté produit) | l'état d'abonnement tel que le produit le connaît : jusqu'à quand, quels modules, quels quotas. | produit, `storage/licence.json` |

> Vendre à l'**entité** et non au client est ce qui permet à une Vision de payer pour ses antennes,
> et à une église indépendante de payer pour elle seule, avec le même code.

---

## 3. Le modèle commercial

### Deux natures d'offre, deux rythmes

| Nature | Ce qu'elle ouvre | Rythme | Pourquoi ce rythme |
|---|---|---|---|
| **Licence** | l'espace **superadmin** (la Vision, le pilotage du réseau) | **annuel** | c'est l'engagement de tête. Un réseau ne se met pas en place au mois. |
| **Accès** | les espaces **antenne / secteur / département / commission** | **mensuel** | on ajoute et on retire des églises en cours d'année. |
| **Combinée** | tout, pour une église seule sans réseau | **mensuel** | une église indépendante n'a pas de « réseau » à licencier. |

Trois paliers : **Starter**, **Standard**, **Premium**. La licence porte en plus une **grille de
taille de réseau** (`paliers_taille`) : le prix suit le nombre d'entités déclarées, sans multiplier
les lignes du catalogue.

### La cascade — la règle en une phrase

> **Un accès ne peut exister que sous une licence en cours, et ne peut jamais lui survivre.**

Concrètement, `App\Support\Cascade` refuse une vente dans quatre cas (chacun renvoie une phrase en
français, affichée telle quelle) :

1. pas de licence en cours sur l'installation ;
2. la licence est expirée ou hors période de grâce ;
3. le palier de l'accès dépasse celui de la licence (pas de Premium sous une Starter) ;
4. le `plafond_acces` de la licence est atteint.

La règle du **pro rata à l'émission** — un accès vendu en cours d'année doit s'arrêter avec la
licence — est écrite sur la page publique mais **n'est pas encore contrainte dans le code**. À
surveiller à la main pour l'instant (voir §13).

### Les modules

**33 modules répartis sur 6 espaces**, dont **28 vendables**. La clé porte toujours son espace :
`secteur.rapports`, `antenne.rapports` et `department.rapports` sont **trois modules différents** —
un rapport d'antenne et un rapport de secteur ne sont pas le même écran.

Le catalogue vit à deux endroits qui doivent rester **identiques** :

- `oikos-console/config/modules.php` — ce que vous vendez ;
- `generation-joel/app/Support/Modules.php` — ce que le produit ouvre.

> Si une clé existe d'un côté et pas de l'autre, vous vendez un module que rien n'ouvrira, ou vous
> ouvrez un module que rien ne facture. **Toute modification se fait des deux côtés dans la même
> séance.**

Dans une offre, `fonctionnalites = null` signifie **tout, y compris les modules futurs**. Une liste
énumérée est figée : les modules ajoutés plus tard n'y entreront pas tout seuls.

---

## 4. Mettre la console en route

Depuis une base **vide**. Dossier : `oikos-console`.

```bash
# 1. Dépendances (une seule fois)
composer install
npm install

# 2. .env — voir le tableau ci-dessous, puis :
php artisan key:generate

# 3. Le schéma
php artisan migrate

# 4. Les données indispensables
php artisan db:seed --class=ReglageSeeder   # taux CDF, essai, grâce, modes de paiement
php artisan db:seed --class=PlanSeeder      # le catalogue d'offres

# 5. Votre compte
php artisan oikos:admin

# 6. Les assets + le serveur
npm run dev            # dans un terminal à part, à laisser tourner
php artisan serve --port=8001
```

Le port **8001** est une convention de ce document : le produit prendra 8000. Deux applications
Laravel ne peuvent pas écouter le même port.

### `.env` de la console

| Clé | Rôle |
|---|---|
| `APP_URL` | **doit être juste** : c'est l'adresse que les installations appelleront |
| `DB_*` | la base de la console |
| `MAIL_*` | mot de passe oublié + accusés de réception |
| `PAIEMENT_TITULAIRE` | le nom au nom duquel on paie, affiché sur la page publique |
| `PAIEMENT_MPESA` / `PAIEMENT_MPESA_NUMERO` | mode actif + numéro. **Un mode actif sans numéro est ignoré** |
| `PAIEMENT_ORANGE_MONEY` / `..._NUMERO` | idem |
| `PAIEMENT_TAUX_CDF` | taux indicatif USD→CDF (bouton de conversion sur la page publique) |
| `PAIEMENT_DEVISE_AFFICHEE` | `USD` |
| `PAIEMENT_DELAI_REOUVERTURE` | le délai annoncé au client, ex. `24 heures ouvrables` |

Ces valeurs sont surchargeables **sans toucher au `.env`** depuis `/console/reglages` : les
réglages en base gagnent, le `config/` sert de repli.

**Vérification :** `/` affiche la vitrine avec les tarifs ; `/console` demande la connexion ;
`/console/offres` liste les offres du `PlanSeeder`.

---

## 5. Installer le produit

Dossier : `generation-joel`. Deux voies mènent au même résultat.

### Voie A — l'installateur web (celle que le client verra)

Prérequis : un `.env` avec `APP_KEY` généré, et **aucun** `storage/installed.lock`.

```bash
composer install
npm install && npm run build
php artisan key:generate
php artisan serve --port=8000
```

Ouvrir `http://127.0.0.1:8000/installer`. Cinq étapes :

| # | Étape | Ce qui s'y passe |
|---|---|---|
| 1 | **Jeton** | l'installateur écrit `storage/installer-token.txt` ; il faut recopier ce jeton dans le navigateur. Personne ne peut installer votre logiciel sans accès au serveur. |
| 2 | **Prérequis + base** | versions PHP/extensions, puis les identifiants MySQL, testés avant d'être écrits dans le `.env` |
| 3 | **Schéma** | les migrations, passées **par lots** (`/installer/schema/lot`) pour ne pas dépasser le temps d'exécution d'un hébergement mutualisé |
| 4 | **Communauté** | le nom de l'église, le compte superadmin, et — facultatif — la **clé d'activation** : collée ici, le système sort de l'installateur déjà branché à la console. Le champ n'apparaît que si `CONSOLE_URL` est renseignée. |
| 5 | **Terminé** | `APP_URL` est fixée à l'adresse réelle du site, le résultat du branchement est affiché, et `storage/installed.lock` est écrit — **l'installateur se ferme définitivement** |

> Une clé refusée à l'étape 4 **n'annule pas l'installation** : le système est installé, simplement
> pas encore branché. L'écran `/admin/activation` permet de réessayer.

Le garde `installer.guard` referme les routes dès que le verrou existe. Pour rejouer une
installation, il faut supprimer `storage/installed.lock` (et vider la base).

### Voie B — la ligne de commande (pour vos tests)

```bash
php artisan joel:creer-base --force      # crée/recrée la base du .env
php artisan joel:installer               # migrations + communauté + compte
php artisan db:seed                      # les données de démonstration
```

`joel:creer-base --force` **supprime** la base si elle existe. C'est exactement ce qu'il faut pour
repartir de zéro, et exactement ce qu'il ne faut jamais lancer sur un serveur client.

### `.env` du produit

| Clé | Valeur | Rôle |
|---|---|---|
| `CONSOLE_URL` | `http://127.0.0.1:8001` | **l'adresse de votre console.** Sans elle, aucune activation n'est possible |
| `LICENCE_EXIGEE` | `false` en développement, `true` chez le client | quand c'est `true`, `/admin` est fermé tant que le système n'est pas activé |
| `PRODUIT_NOM` | `Oikos` | le nom du **logiciel** — jamais celui de l'église |
| `PRODUIT_EDITEUR` | votre nom | affiché dans l'installateur et la page d'activation |

> `CONSOLE_URL` sans `/api` ni barre finale : le produit ajoute lui-même `/api/v1/...`.

> **Il n'y a AUCUNE clé à mettre dans le `.env`.** Une version antérieure de la console affichait une
> « clé d'installation » à recopier sous le nom `CONSOLE_CLE` : c'était un reste d'une conception
> abandonnée, et le produit ne l'a jamais lue. Le seul secret qu'il détient, il le reçoit tout seul
> à l'activation, et il l'écrit dans `storage/`. Si vous voyez encore `CONSOLE_CLE` quelque part,
> c'est une ligne morte — vous pouvez la supprimer.

---

## 6. La liaison

### Les quatre fichiers, dans `storage/`

La licence vit dans des **fichiers**, pas en base. Ce n'est pas un détail : une restauration de
sauvegarde de la base ne doit pas rouvrir un abonnement expiré, ni fermer un abonnement payé.

| Fichier | Contenu | Créé quand | Survit à |
|---|---|---|---|
| `empreinte.txt` | l'identité **stable** de cette installation | au premier besoin | tout — y compris une désactivation. C'est volontaire : la même installation doit se reconnaître |
| `console-jeton.txt` | la **clé de synchronisation** (secret sortant) | à l'activation | tant qu'on ne réactive pas |
| `licence.json` | l'état lu : statut, fin, grâce, modules, quotas | à chaque échange réussi | la restauration de la base |
| `console-rappel.txt` | le **secret entrant**, celui que la console présente pour nous réveiller | au premier besoin | tout |

`App\Support\Licence::oublier()` efface les trois derniers et **garde l'empreinte**.

### Les deux secrets, et pourquoi ils sont deux

|  | Clé de synchronisation | Jeton de rappel |
|---|---|---|
| Sens | produit → console | console → produit |
| Stockée dans la console | **en empreinte SHA-256** — illisible | **en clair** (`installations.rappel_jeton`) |
| Ce qu'elle permet | pousser l'arbre, lire la licence | **uniquement** déclencher l'appel sortant habituel |
| Si elle fuite | on peut lire l'état d'abonnement d'une installation | on peut demander à une installation de se rafraîchir. C'est tout. |

La route entrante `/oikos/rafraichir` **n'accepte aucune donnée**. Elle vérifie le secret
(`hash_equals`, comparaison à temps constant) et rappelle la console par le canal normal. Une
console compromise ne peut donc rien faire écrire à mille installations.

C'est aussi la raison pour laquelle la clé de synchronisation est **renouvelée à chaque
activation** : la console n'en garde que l'empreinte, elle ne peut donc pas la relire — la seule
façon d'en remettre une est d'en fabriquer une neuve. Effet secondaire heureux : réactiver invalide
la clé précédente, et un vieux serveur oublié quelque part cesse de se synchroniser.

### Les trois échanges

```
1. ACTIVATION  ────────────────────────────────────────────────────────────────►
   POST {CONSOLE_URL}/api/v1/activation
   { cle: "OIKOS-XXXX-XXXX-XXXX", empreinte, url, version, rappel, identite }
   ◄──── { jeton: "<clé de synchro, en clair, UNE seule fois>", licence: {…} }

2. SYNCHRONISATION  ───────────────────────────────────────────────────────────►
   POST {CONSOLE_URL}/api/v1/synchronisation      Authorization: Bearer <jeton>
   { version, url, rappel, identite, entites: [...], compteurs: {...} }
   ◄──── { licence: {…} }

3. RAPPEL  ◄───────────────────────────────────────────────────────────────────
   POST {url de l'installation}/oikos/rafraichir  Authorization: Bearer <rappel>
   (aucun corps utile)  →  l'installation déclenche elle-même l'échange n°2
```

La réponse `licence` contient : `statut`, `offre`, `fin` (**la plus proche** des fins de période —
une installation n'est ouverte que tant que *toutes* ses périodes le sont), `grace_jours`,
`modules` (union des offres ; `null` si une offre ouvre tout), `quotas` (`null` gagne) et la carte
des `entites`.

**Ce qui ne descend jamais :** rien de modifiable. La réponse est un état, pas un ordre.
**Ce qui ne monte jamais :** aucune donnée d'église. Ni membre, ni finance, ni contenu.

### La carte de visite (`identite`) — la fiche client se remplit toute seule

L'installation déclare le nom de la communauté, sa ville, son pays, et les coordonnées du **seul**
compte avec qui vous êtes en relation commerciale — le superadmin. Rien d'autre : pas un membre,
pas un ouvrier.

Côté console, une règle unique gouverne ce qui en est fait : **on ne remplit que les cases vides.**
Une valeur que vous avez saisie n'est jamais écrasée, ni à la première activation ni à la
trois-centième synchronisation. Concrètement :

- créez un client avec juste un nom provisoire, ajoutez une installation **sans nom**, émettez la
  clé — et à l'activation, la fiche se complète : nom exact, ville, pays, responsable, e-mail,
  téléphone, et le nom de l'installation ;
- si vous corrigez ensuite quoi que ce soit à la main, votre correction gagne pour toujours.

Ce qui a été rempli automatiquement est noté dans le journal (`INSTALLATION_ACTIVEE` →
`fiche_remplie`, ou `FICHE_COMPLETEE`), pour qu'on puisse distinguer plus tard ce que vous avez
saisi de ce que l'installation a déclaré.

---

## 7. Vendre, facturer, encaisser

### La chaîne

```
Fiche client → l'entité choisie → « Vendre »
      │
      ├─► Abonnement créé au statut IMPAYÉ (ouvert pendant la grâce)
      └─► Facture ÉMISE, avec un numéro
                │
                ├─► le client paie par M-Pesa / Orange Money et vous donne la référence
                │
                └─► /console/factures → « Encaisser »
                          │
                          ├─ reste > 0  → versement partiel enregistré, facture toujours ÉMISE
                          └─ reste = 0  → facture PAYÉE
                                          + abonnement ACTIF
                                          + rappel de l'installation (hors transaction)
```

### Trois règles à connaître

**La facture décide, pas le paiement.** On enregistre des versements ; c'est la facture qui dit
quand elle est couverte. Les paiements **partiels** — fréquents en mobile money, où le plafond
journalier oblige à payer en deux fois — fonctionnent donc sans code particulier.

**La référence de l'opérateur est unique en base** (index unique `fournisseur` + `reference`).
C'est ce qui empêche de saisir deux fois le même versement M-Pesa — l'erreur la plus banale quand
on encaisse à la main, et celle qui offre un mois gratuit sans que personne s'en aperçoive.

**Un versement annoncé mais jamais reçu se marque « non reçu », il ne s'efface pas.** « Ce client a
annoncé un paiement le 12 qui n'est jamais arrivé » est une information qui sert le jour où il
affirme le contraire.

**L'ordre des écritures :** l'argent d'abord, l'accès ensuite, le rappel de l'installation en
dernier et **hors transaction**. Un client injoignable ne doit jamais faire échouer l'enregistrement
d'un paiement.

### Ce que le client voit de son côté

Le produit expose **`/admin/mon-abonnement`** (menu *Mon abonnement*, espace de la Vision, réservé
au superadmin). La page lit `storage/licence.json` — **aucun appel réseau au chargement** — et
montre, dans cet ordre :

1. l'offre en cours et l'échéance, avec le ton qui va avec (vert, ambre à 14 jours, ambre en grâce,
   rouge une fois périmée) ;
2. **ce qui est dû** — le bloc n'apparaît que s'il y a des factures ouvertes ;
3. **comment régler** — les numéros M-Pesa / Orange Money issus de vos Réglages, le titulaire, le
   délai de réouverture, le taux CDF indicatif, et le rappel que **le numéro de facture est la
   référence à citer** ;
4. ce que l'offre comprend, replié, groupé par espace et en libellés lisibles.

Un bouton **Actualiser** force la synchronisation ; c'est le seul élément de la page qui touche au
réseau.

Dans les **cinq autres espaces** (antenne, secteur, département, commission, extension), un
`<x-bandeau-abonnement />` s'affiche en haut du contenu — **et seulement** quand il y a une raison :
échéance à moins de 14 jours, délai de grâce, ou abonnement expiré. Il ne mentionne **jamais** de
montant ni de numéro de facture : les finances de l'église ne regardent pas un responsable de
cellule.

> **Par défaut, aucun paiement ne se règle depuis le produit** : le client verse en citant son
> numéro de facture, vous encaissez dans la console, le rappel rouvre l'installation. Ce que la page
> apporte, c'est qu'il sache exactement **quoi payer, à qui, et avec quelle référence**.

### L'encaissement automatique — FlexPay (optionnel, éteint par défaut)

Quand `FLEXPAY_ACTIF=true` dans le `.env` de la console, un bouton **« Payer maintenant »**
apparaît à deux endroits : sur `/console/factures` (l'opérateur le déclenche, souvent au téléphone
avec le client) **et** sur `/admin/mon-abonnement` côté produit (le client le déclenche lui-même).

Le déroulé, dans les deux cas :

```
« Payer maintenant » (opérateur, ou n° de tél. + opérateur)
      │
      ├─ console : App\Support\Paiement\DemarrerPaiementEnLigne
      │            (le produit passe par POST /api/v1/paiement/demarrer, jamais par FlexPay direct)
      │
      ├─► FlexPay pousse une invite USSD sur le téléphone du client   → Paiement EN_ATTENTE
      │
      ├─► le client valide (ou non)
      │
      └─► FlexPay POST /webhooks/flexpay
                │  1. signature partagée vérifiée (FLEXPAY_SECRET_WEBHOOK)
                │  2. état RE-DEMANDÉ à FlexPay — jamais cru sur le seul corps du callback
                │  3. App\Support\EncaissementFacture (identique à l'encaissement manuel)
                └─► facture PAYÉE + abonnement ACTIF + rappel de l'installation
```

**Le produit n'a ni compte marchand, ni factures, ni abonnements** : il délègue tout à la console.
**Le webhook répond toujours 200** (sauf signature falsifiée → 403), parce qu'un agrégateur rejoue
son appel tant qu'il n'a pas d'accusé ; l'index unique `(fournisseur, reference)` rend le rejeu
inoffensif. **On n'encaisse jamais sur la foi du callback** : `verifier()` redemande l'état à
FlexPay, signé par notre jeton, avant de solder quoi que ce soit.

Brancher un autre agrégateur un jour = une classe qui implémente `App\Support\Paiement\PasserellePaiement`
et une branche dans `AppServiceProvider`. Le reste du code ne connaît que l'interface.

### Le renouvellement

Depuis la fiche client, « Renouveler » repart de **la fin de l'ancienne période** si elle est encore
future — pas de la date du jour. Payer en avance ne fait donc pas perdre de jours.

---

## 8. Activer l'abonnement

### Côté console — émettre la clé

1. `/console/clients` → le client → sa fiche.
2. Si l'installation n'existe pas encore : « Ajouter une installation » (nom + URL).
3. Section **Clés d'activation** → « Émettre une clé ».
4. La clé s'affiche **une seule fois**, au format `OIKOS-XXXX-XXXX-XXXX`. Le bouton **Copier** évite
   la sélection à la main.

L'alphabet exclut `O`, `I`, `L`, `0` et `1` : la clé se dicte au téléphone sans ambiguïté. Elle est
stockée **hachée**, à usage unique, et expire par défaut au bout de 30 jours.

### Côté produit — la consommer

1. Se connecter en superadmin, aller sur **`/admin/activation`**.
2. La page affiche l'**empreinte** de l'installation (utile pour vérifier au téléphone qu'on parle
   bien de la même machine).
3. Coller la clé → « Activer ».

Ce qui se passe alors, dans cet ordre : la console vérifie la clé, marque l'installation vue,
enregistre le jeton de rappel, **consomme** la clé, renouvelle la clé de synchronisation et renvoie
`{ jeton, licence }`. Le produit écrit **d'abord** `console-jeton.txt`, **ensuite** `licence.json` —
si l'écriture échoue à mi-course, on garde de quoi se resynchroniser plutôt qu'un état orphelin.

La même page porte un bouton **« Actualiser depuis la console »** : c'est la synchronisation à la
demande, sans attendre la nuit.

---

## 9. Ce que la licence ferme

### Deux gardes orthogonaux — ne jamais les confondre

| | Question posée | Middleware | Réponse en cas de refus |
|---|---|---|---|
| **Permission** | *qui, dans l'équipe du client, a le droit d'entrer ?* | `permission:` `secteur.module:` `staff.module` `berger` | « vous n'avez pas ce droit » |
| **Licence** | *le client a-t-il acheté cet écran ?* | `module:espace.cle` | « ce module n'est pas inclus dans votre offre » |

Ils se **superposent**. Un pasteur peut avoir toutes les permissions et ne pas voir un écran que son
église n'a pas acheté ; un module acheté reste fermé à qui n'a pas le rôle. **Les fusionner casserait
les deux.**

### Les trois gardes de licence

| Middleware | Quand il bloque | Ce qu'il fait |
|---|---|---|
| `licence.activee` | système jamais activé, **et** `LICENCE_EXIGEE=true` | redirige vers `/admin/activation` |
| `abonnement.actif` | abonnement périmé, grâce dépassée | **laisse lire, bloque l'écriture** (POST/PUT/PATCH/DELETE), avec le formulaire conservé |
| `module:espace.cle` | l'offre n'ouvre pas cette clé | `403` avec le libellé du module |

> Le choix « lecture ouverte, écriture fermée » est délibéré. Couper l'accès à une église le jour où
> sa facture traîne, c'est lui faire perdre ses données au moment où elle en a besoin. Elle consulte,
> elle n'ajoute plus. Le message est clair, la relation ne se casse pas.

Les routes `activation.*` et la déconnexion sont **exemptées** — sinon un système sans abonnement ne
pourrait plus enregistrer la clé qui le rouvre.

---

### Le durcissement — trois verrous, et ce qu'ils valent

Disons-le d'abord : **aucun de ces mécanismes ne rend le logiciel inviolable.** Qui possède le
serveur possède aussi le code, et peut retirer l'appel qui vérifie. Ils font une chose, et ils la
font bien : transformer des gestes évidents, qu'on trouve tout seul, en manipulations délibérées
qu'il faut chercher. Le seul vrai verrou reste **qui héberge**.

**1. La licence est signée (RSA-SHA256, via `openssl`).** La console signe l'état avec une clé
privée ; le produit
vérifie avec la clé publique **posée dans le code**, pas dans le `.env`. L'algorithme a été
choisi sur un seul critère : `ext-openssl` figure dans les dépendances **obligatoires** de
laravel/framework, donc tout serveur capable de faire tourner le produit la possède. `ext-sodium`
n'y figure pas et manque par défaut sous Windows — une vérification qui échoue faute d'extension
fermerait le système d'un client à jour, ce qui serait pire que pas de protection. Ouvrir `licence.json` pour
remplacer 2026 par 2099 ne marche plus : le fichier est rejeté, et un fichier rejeté vaut « aucune
licence », donc écran d'activation.

La signature couvre aussi l'**empreinte** de la machine destinataire — le `licence.json` d'un client
bien abonné, copié sur un autre serveur, y est refusé. C'est l'attaque la plus simple qui soit
(copier un fichier), c'est donc la première à fermer.

**2. Le silence a une durée maximale** (`silence_jours`, 45 jours par défaut, réglé côté console).
Passé ce délai sans échange réussi, le produit se déclare périmé quoi que dise la date de fin.
Sinon, débrancher le réseau suffirait à figer un abonnement puis à rejouer le même fichier
indéfiniment. Le délai est généreux exprès : il doit absorber une coupure de fibre de trois
semaines, pas punir une panne.

**3. Une marque en base survit à la suppression du fichier.** Effacer `storage/licence.json` ne rend
plus l'installation vierge : la table `licence_marque` dit qu'elle a été activée un jour, et
`EnsureLicenceActivee` renvoie vers l'écran d'activation. Il faut désormais trouver et effacer deux
choses de nature différente. Pour vos propres tests : `php artisan licence:oublier --tout`.

Et `licence_exigee` **n'obéit plus au `.env` qu'en développement** (`APP_ENV=local`). En production
la réponse est `true`, quoi que dise le fichier.

#### La bascule — l'ordre compte, et une erreur ferme tout le parc

```bash
# 1. SUR LA CONSOLE — fabriquer la paire de clés
php artisan oikos:cles-signature
#    → coller LICENCE_CLE_PRIVEE=... dans le .env de la console
#    Sous Windows, si openssl refuse : la commande affiche les deux façons d'en sortir
#    (OPENSSL_CONF, ou génération avec l'outil openssl de Git pour Windows).
php artisan optimize:clear
php artisan migrate          # ajoute installations.empreinte

# 2. VÉRIFIER que les installations se synchronisent toujours.
#    Elles reçoivent maintenant des licences signées, sans encore les vérifier.

# 3. SUR LE PRODUIT — seulement après avoir vérifié l'étape 2
php artisan migrate          # crée licence_marque
#    → coller la clé PUBLIQUE dans config/produit.php ('licence_cle_publique')
php artisan optimize:clear
```

> **Dans l'ordre inverse**, les produits vérifieraient des licences que la console ne signe pas
> encore, et se fermeraient tous en même temps. C'est la seule manœuvre de ce projet où l'ordre est
> critique.

Tant que `licence_cle_publique` est vide, **rien ne change** : le produit ne vérifie pas. Les deux
moitiés s'allument séparément, exprès, pour qu'un parc bascule sans coupure.

## 10. Les trois rafraîchissements

Une installation apprend qu'elle est payée de trois manières, du plus lent au plus rapide :

1. **La nuit.** `routes/console.php` planifie `abonnement:synchroniser --silencieux` à **03h17**,
   `withoutOverlapping()`, en arrière-plan. Il faut que le cron du serveur appelle
   `php artisan schedule:run` toutes les minutes.
2. **Le bouton.** « Actualiser depuis la console » sur `/admin/activation`. Immédiat, à la main.
3. **Le rappel.** Dès qu'une facture est soldée, la console appelle
   `{url}/oikos/rafraichir` (6 s de délai maximum, jamais bloquant). L'installation se
   resynchronise d'elle-même. C'est le chemin normal : le client paie, et son système se rouvre
   pendant qu'il est encore au téléphone.

Le rappel exige que la console **connaisse l'URL** de l'installation et détienne son
`rappel_jeton` — les deux sont transmis à l'activation. **Une installation activée avant l'ajout de
ce canal doit être réactivée une fois** pour que le rappel fonctionne.

La commande renvoie `SUCCESS` quand le réseau échoue (une panne de votre console ne doit pas faire
crier le cron d'un client) et `FAILURE` seulement quand le message annonce une réactivation.

---

## 11. Le test complet depuis zéro

Cette section est une **recette linéaire**. Deux bases vides, deux serveurs, et à la fin un
abonnement vendu, payé et activé. Suivez-la dans l'ordre, sans sauter d'étape ; chaque bloc se
termine par une vérification qui dit si vous pouvez continuer.

> **Le piège de l'ordre des clés ne vous concerne PAS ici.** Vous lirez plus haut (§9) qu'il faut
> faire signer la console *avant* de faire vérifier le produit. Cette précaution existe pour un parc
> **déjà en service**, où les installations tournent pendant que vous basculez. En partant de zéro,
> vous posez les deux clés d'un coup, avant la première activation. C'est plus simple, et c'est ce
> que fait cette recette.

Convention de ce document : la **console** écoute sur le port `8001`, le **produit** sur le `8000`.

---

### Étape 0 — Table rase

**Console :**

```sql
DROP DATABASE oikos_console;  CREATE DATABASE oikos_console;
```

**Produit :** la commande fait le travail (elle supprime et recrée la base du `.env`) —

```bash
php artisan joel:creer-base --force
```

**Produit — les fichiers d'état.** Supprimez dans `storage/` :

| Fichier | Pourquoi |
|---|---|
| `installed.lock` | sinon l'installateur reste fermé |
| `installer-token.txt` | il sera réécrit |
| `licence.json` | l'état d'abonnement |
| `console-jeton.txt` | la clé de synchronisation |
| `console-rappel.txt` | le secret du canal entrant |

**`empreinte.txt` : à vous de voir.** Le garder simule le même serveur qu'avant ; le supprimer
simule un serveur neuf. Les deux méritent d'être essayés, mais commencez par le **garder** — ça
fait une variable de moins.

> La base du produit est vide, donc la table `licence_marque` a disparu avec elle. Rien à faire de
> plus : `php artisan licence:oublier --tout` sert quand on veut repartir de zéro **sans** vider la
> base.

---

### Étape 1 — La console

```bash
php artisan migrate
php artisan db:seed --class=ReglageSeeder
php artisan db:seed --class=PlanSeeder
php artisan oikos:admin
php artisan optimize:clear
npm run dev                       # dans un terminal à part, à laisser tourner
php artisan serve --port=8001     # dans un autre
```

**Vérifiez :** `http://127.0.0.1:8001/` affiche la vitrine avec des tarifs chiffrés. S'ils sont à
zéro, le `PlanSeeder` n'est pas passé.

Puis connectez-vous à `/console/reglages` et renseignez le **titulaire** et au moins **un numéro de
paiement** (M-Pesa ou Orange Money). Sans numéro, un mode de paiement est ignoré — et l'écran
« Mon abonnement » du client n'aura rien à lui montrer.

---

### Étape 2 — Les clés de signature

```bash
php artisan oikos:cles-signature
```

La commande affiche deux valeurs. **Gardez ce terminal ouvert**, vous en avez besoin deux fois.

1. **La clé privée** → dans le `.env` de la **console** :

   ```
   LICENCE_CLE_PRIVEE=<la longue chaîne affichée>
   ```

   Puis `php artisan optimize:clear`.

2. **La clé publique** → dans `config/produit.php` du **produit** :

   ```php
   'licence_cle_publique' => '<la longue chaîne affichée>',
   ```

**Vérifiez :** rien à vérifier tout de suite — la première activation le dira. Mais notez la règle :
ces deux valeurs vont **ensemble**. Régénérer l'une sans remplacer l'autre invalide toutes les
licences.

> Ne collez jamais la clé privée ailleurs que dans ce `.env` : ni dans Git, ni dans un message.

---

### Étape 3 — Installer le produit

```bash
# .env du produit, avant de démarrer :
#   CONSOLE_URL=http://127.0.0.1:8001
#   APP_ENV=local          (pour vos tests ; en production, laissez production)
#   LICENCE_EXIGEE=true    (n'est lu que si APP_ENV=local — voir §9)

php artisan optimize:clear
php artisan serve --port=8000
```

Ouvrez `http://127.0.0.1:8000/installer` et déroulez les cinq étapes.

À l'**étape 4**, laissez le champ « Clé d'activation » **vide** pour ce premier essai : on veut
voir la console et le produit se brancher séparément avant de tout enchaîner d'un coup.

Puis, une fois l'installateur terminé :

```bash
php artisan migrate       # crée licence_marque
php artisan db:seed       # les données de démonstration
php artisan optimize:clear
```

**Vérifiez :** vous vous connectez, et `/admin` **vous renvoie vers l'écran d'activation**. C'est le
comportement attendu — `LICENCE_EXIGEE=true` et aucune licence enregistrée.

---

### Étape 4 — Brancher le produit à la console

**Dans la console :** `/console/clients` → *Nouveau client*. Un nom provisoire suffit, le reste se
remplira tout seul. Puis, sur sa fiche, *Ajouter une installation* :

- **Nom** : laissez vide — il prendra le nom exact de la communauté.
- **Adresse** : `http://127.0.0.1:8000` — celle-ci, renseignez-la, c'est elle qui permet le rappel
  après paiement.

Puis *Émettre une clé d'activation* et copiez-la (bouton **Copier**).

**Dans le produit :** `/admin/activation` → collez la clé → **Activer**.

**Vérifiez — c'est le contrôle le plus important de toute la recette.** Ouvrez
`storage/licence.json`. Il doit contenir :

```json
"empreinte": "…",
"silence_jours": 45,
"signature": "…"
```

- `signature` à `null` → la console ne signe pas. Revoyez `LICENCE_CLE_PRIVEE` et refaites
  `optimize:clear` **côté console**. N'allez pas plus loin.
- `/admin` s'ouvre normalement → la clé publique du produit vérifie bien cette signature. Tout est
  en place.

**Vérifiez aussi côté console :** la fiche client s'est remplie toute seule — nom exact de la
communauté, ville, pays, responsable, e-mail, téléphone — et l'installation a pris le nom de la
communauté.

Cliquez enfin sur **Actualiser depuis la console** dans le produit, puis rechargez la fiche client :
l'**arbre des entités** doit apparaître (VISION, ANTENNE, EXTENSION) avec les compteurs.

---

### Étape 5 — Vendre

Sur la fiche client, à côté d'une entité **VISION** → *Vendre* → choisissez une offre de **licence**
→ enregistrer.

Puis, à côté d'une entité **EXTENSION** → *Vendre* → une offre d'**accès**.

**Vérifiez la cascade au passage** : essayez de vendre un accès **Premium** sous une licence
**Starter**. La vente doit être refusée, avec une phrase en français qui dit pourquoi.

**Vérifiez :** `/console/factures`, onglet *À encaisser* — vos factures y sont, avec un numéro.

---

### Étape 6 — Ce que le client voit avant de payer

Dans le produit : **Actualiser**, puis menu **Mon abonnement**.

L'écran doit montrer l'offre, l'échéance, le bloc **À régler** avec le numéro de facture, et
**Comment régler** avec le numéro de paiement saisi à l'étape 1.

> Si le bloc « Comment régler » est absent, c'est qu'aucun mode n'a de numéro dans les Réglages de
> la console. Un mode actif sans numéro est volontairement ignoré.

---

### Étape 7 — Encaisser

Dans `/console/factures` :

1. Saisissez un versement **partiel** (la moitié du montant). La facture reste *émise*, et affiche
   « reste X $ ».
2. Ressaisissez **la même référence d'opérateur** : refusé, avec le message d'unicité. C'est ce qui
   empêche d'encaisser deux fois le même paiement M-Pesa.
3. Complétez le versement. La facture passe **payée**, l'abonnement **actif**, et un message
   confirme que l'installation a été prévenue.

**Vérifiez — sans rien faire côté produit :** rechargez **Mon abonnement**. Le bloc *À régler* a
disparu et l'échéance a bougé. C'est le **rappel** qui a fait le travail, pas vous.

---

### Étape 8 — Les gardes de licence

Un module non vendu doit se fermer : ouvrez un écran d'un module absent de l'offre achetée. Vous
devez obtenir un `403` nommant le module en toutes lettres.

---

### Étape 9 — Les trois verrous du durcissement

C'est ici qu'on essaie de tricher. Après chaque essai, **réparez avec « Actualiser »** avant de
passer au suivant.

**a. Le fichier falsifié.** Ouvrez `storage/licence.json`, remplacez la date de `fin` par
`"2099-01-01T00:00:00+00:00"`, enregistrez, rechargez `/admin`.
→ Vous devez être renvoyé vers l'écran d'activation. La signature ne couvre plus le contenu.

**b. Le fichier supprimé.** Supprimez `storage/licence.json`, rechargez `/admin`.
→ Vous devez *encore* être renvoyé vers l'activation. C'est la marque en base qui parle. Avant ce
chantier, ce geste rouvrait tout le système.

**c. La licence d'un autre.** Copiez un `licence.json` valide de côté, puis modifiez une lettre
dans `storage/empreinte.txt`, et remettez le fichier en place.
→ Refusé : la signature destinait cette licence à une autre machine.

**Remettre d'aplomb après ces essais :**

```bash
php artisan licence:oublier --tout    # efface l'état ET la marque
```

Puis réémettez une clé depuis la console et réactivez.

---

### Étape 10 — L'installation en un seul geste

Maintenant que chaque morceau est vérifié séparément, rejouez l'étape 0 puis l'étape 3 — mais cette
fois, **collez la clé d'activation directement à l'étape 4 de l'installateur**.

Émettez la clé dans la console **avant** de lancer l'installation, puis déroulez l'installateur.

**Vérifiez :** la page finale annonce « Abonnement activé », et `/admin` s'ouvre sans passer par
l'écran d'activation. C'est le parcours que vivra un vrai client.

---

### Le tableau de bord de vos essais

| Ce que je veux vérifier | Le geste | Ce qui doit se passer |
|---|---|---|
| la console signe | ouvrir `licence.json` | `signature` non nul |
| le produit vérifie | falsifier `fin` | renvoi vers l'activation |
| la marque tient | supprimer `licence.json` | renvoi vers l'activation |
| la licence est nominative | changer `empreinte.txt` | refus |
| la fiche se remplit seule | activer | nom, ville, responsable renseignés |
| le rappel fonctionne | solder une facture | l'échéance bouge sans rien toucher |
| la cascade tient | vendre Premium sous Starter | refus expliqué |
| l'unicité tient | ressaisir une référence | refus expliqué |
| le module ferme | ouvrir un écran non vendu | `403` nommant le module |

## 12. Dépannage

| Symptôme | Cause la plus probable |
|---|---|
| « Clé d'activation invalide » | clé déjà consommée, expirée, ou tapée avec un `O`/`0` — l'alphabet les exclut. Émettez-en une neuve, c'est gratuit. |
| « Cette installation est désactivée » | la bascule de l'installation est sur inactif dans la console |
| Activation : « impossible de joindre la console » | `CONSOLE_URL` faux, console éteinte, ou barre finale / `/api` en trop |
| La facture est payée mais le produit ne le sait pas | `installations.url` vide ou fausse, ou installation activée **avant** l'ajout du canal de rappel → réactiver une fois. En attendant, le bouton « Actualiser » fait le travail. |
| Aucune entité dans la fiche client | l'installation n'a pas encore synchronisé → bouton « Actualiser » côté produit |
| Les couleurs / le style de la console sont cassés | `npm run dev` n'est pas lancé, ou une classe Tailwind est construite par concaténation PHP — le JIT ne la génère alors jamais. **Écrire les classes de statut en toutes lettres.** |
| `/installer` renvoie vers `/` | `storage/installed.lock` existe déjà |
| Le cron ne synchronise pas | `php artisan schedule:run` n'est pas appelé chaque minute par le système |
| Une classe supprimée provoque une erreur d'autoload | `composer dump-autoload` |
| `signature` vaut `null` dans licence.json | la console ne signe pas : `LICENCE_CLE_PRIVEE` absente du `.env` de la **console**, ou `optimize:clear` non fait de ce côté-là |
| Toutes les installations renvoient vers l'activation après une mise à jour | la clé publique a été livrée au produit avant que la console ne signe. Posez la clé privée côté console, puis « Actualiser » |
| « Mon abonnement » n'affiche pas de moyens de paiement | aucun mode n'a de numéro dans `/console/reglages` — un mode actif sans numéro est ignoré |
| `oikos:cles-signature` échoue sous Windows | plus depuis que la commande écrit son propre `openssl.cnf`. Si cela revient, le message affiche les deux sorties de secours |
| `licence:oublier` ne rouvre rien | c'est voulu : sans `--tout`, la marque en base reste et l'installation redemande une clé |
| La fiche client ne se remplit pas toute seule | elle n'était pas vide : la règle est « on ne remplit que le vide ». Videz la case, puis relancez « Actualiser » côté produit. |

---

## 13. Ce qui n'est pas encore fait

Écrit ici pour que rien ne se découvre en production.

0. **L'encaissement automatique** par agrégateur mobile money (FlexPay) est **écrit et testé, mais
   éteint** : `FLEXPAY_ACTIF=false` tant qu'un compte marchand n'est pas ouvert. Dans cet état, le
   bouton « Payer maintenant » n'apparaît nulle part et tout versement se saisit à la main comme
   avant. Pour l'allumer : ouvrir le compte FlexPay, renseigner `FLEXPAY_MARCHAND`, `FLEXPAY_JETON`
   et `FLEXPAY_SECRET_WEBHOOK` dans le `.env`, passer `FLEXPAY_ACTIF=true`, et déclarer l'URL
   `POST /webhooks/flexpay` comme URL de callback chez FlexPay. Voir §7 pour le déroulé complet.
1. **Les modules sont par installation, pas par entité.** Si une Vision achète un module pour une
   antenne, il est ouvert pour toutes. C'est **permissif** — personne n'est bloqué à tort — mais
   c'est un manque à gagner sur les grands réseaux.
2. **Le pro rata à l'émission n'est pas contraint.** Rien n'empêche aujourd'hui de vendre un accès
   dont la période dépasse la fin de la licence. La règle est annoncée publiquement ; à tenir à la
   main.
3. **La délégation par compte** pour les espaces antenne et département reste à construire.
5. **Les seeders de démonstration côté produit** n'ont pas encore été refondus pour produire un
   arbre d'entités taillé pour la vente (une vision, des antennes, des églises, des cellules, des
   départements cohérents). C'est le prochain chantier.

---

*Ce document décrit l'état du code au moment où il a été écrit. Quand un comportement change, il
change ici aussi — un mode d'emploi faux est pire que pas de mode d'emploi.*
