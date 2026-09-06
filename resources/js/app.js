import './bootstrap';
import { createIcons, icons } from 'lucide';

/**
 * LES ICÔNES.
 *
 * Lucide remplace chaque <i data-lucide="nom"> par le SVG correspondant. On importe le jeu COMPLET
 * plutôt que d'énumérer les icônes utilisées : l'énumération oblige à modifier ce fichier chaque
 * fois qu'une vue en ajoute une, et l'oubli ne se voit qu'à l'écran — un carré vide, sans erreur
 * dans la console.
 *
 * À REVOIR LE JOUR OÙ LE SITE PUBLIC AURA DU TRAFIC. Ce choix était sans conséquence tant que
 * l'application n'avait qu'un seul utilisateur sur votre propre serveur. Depuis l'ajout de la
 * vitrine, ce fichier part aussi vers des visiteurs en 3G qui paient leur forfait : le jeu complet
 * pèse alors pour de bon. La correction consiste à énumérer les icônes réellement employées.
 */
function dessinerLesIcones() {
    createIcons({
        icons,
        attrs: { 'stroke-width': 1.9 },
    });
}

/**
 * LES BANDEAUX DÉFILANTS — un rang de cartes qui glisse à l'horizontale.
 *
 * POURQUOI DU JAVASCRIPT POUR UN DÉGRADÉ. Le dégradé sur les bords dit « il y a autre chose plus
 * loin ». Affiché en permanence, il ment aux deux extrémités : arrivé au bout, l'utilisateur voit
 * encore un voile qui promet une suite inexistante, et cherche à faire défiler ce qui ne défile
 * plus. Ces quelques lignes ne font que ça — allumer chaque voile seulement du côté où il reste
 * réellement quelque chose à voir.
 *
 * Le balisage attendu, dans n'importe quelle vue :
 *
 *   <div data-bandeau>
 *     <div data-bandeau-piste> … les cartes … </div>
 *     <div data-bandeau-gauche></div>
 *     <div data-bandeau-droite></div>
 *   </div>
 */
function activerLesBandeaux() {
    document.querySelectorAll('[data-bandeau]').forEach((bandeau) => {
        const piste = bandeau.querySelector('[data-bandeau-piste]');
        const voileGauche = bandeau.querySelector('[data-bandeau-gauche]');
        const voileDroite = bandeau.querySelector('[data-bandeau-droite]');

        if (!piste) return;

        const rafraichir = () => {
            // Une marge de 4 pixels : les navigateurs rendent des positions fractionnaires, et une
            // comparaison stricte laisserait un voile allumé au bout de la course.
            const resteAGauche = piste.scrollLeft > 4;
            const resteADroite = piste.scrollLeft < piste.scrollWidth - piste.clientWidth - 4;

            if (voileGauche) voileGauche.style.opacity = resteAGauche ? '1' : '0';
            if (voileDroite) voileDroite.style.opacity = resteADroite ? '1' : '0';
        };

        piste.addEventListener('scroll', rafraichir, { passive: true });
        window.addEventListener('resize', rafraichir);

        // Les cartes changent de hauteur quand les polices arrivent, et de largeur quand la
        // fenêtre change : on réévalue à ce moment-là plutôt qu'une seule fois au chargement.
        if ('ResizeObserver' in window) {
            new ResizeObserver(rafraichir).observe(piste);
        }

        rafraichir();
    });
}

/**
 * LA CONVERSION DOLLAR → FRANC, SUR DEMANDE.
 *
 * POURQUOI UN BOUTON ET NON UN RECALCUL AUTOMATIQUE. Recalculer à chaque frappe écraserait un
 * prix en francs volontairement arrondi : 70 000 FC se retient, s'annonce et se paie ;
 * 69 972 FC non. Le taux propose, l'humain décide — et garde la main sur un chiffre qui sera lu
 * par des clients.
 *
 * Le balisage attendu : un ancêtre portant data-taux, et dedans data-prix-usd, data-prix-cdf
 * et data-convertir.
 */
function activerLaConversion() {
    document.querySelectorAll('[data-taux]').forEach((zone) => {
        const taux = parseInt(zone.dataset.taux, 10);
        const usd = zone.querySelector('[data-prix-usd]');
        const cdf = zone.querySelector('[data-prix-cdf]');
        const bouton = zone.querySelector('[data-convertir]');

        if (!taux || !usd || !cdf || !bouton) return;

        bouton.addEventListener('click', () => {
            // La virgule décimale est ce qu'on tape naturellement en français ; parseFloat ne la
            // comprend pas et rendrait 12 pour « 12,50 ».
            const montant = parseFloat(String(usd.value).replace(',', '.'));

            if (!Number.isFinite(montant)) {
                usd.focus();
                return;
            }

            cdf.value = Math.round(montant * taux);
        });
    });
}

/**
 * COPIER UN SECRET EN UN CLIC.
 *
 * Une clé d'installation ou une clé d'activation ne se retape pas : elle se copie. Et elle ne
 * s'affiche QU'UNE FOIS — une sélection à la souris qui rate un caractère, et il faut réémettre.
 *
 * DEUX CHEMINS, ET LE SECOND N'EST PAS DU LUXE. `navigator.clipboard` n'existe que dans un
 * contexte sécurisé : https, ou localhost. Le jour où vous ouvrirez la console depuis une IP en
 * http — un test sur le réseau local, une machine de dépannage — il serait absent, et le bouton
 * échouerait en silence. Le repli par zone de texte cachée fonctionne partout.
 *
 * Et si les deux échouent, on le DIT plutôt que de laisser croire que c'est copié : un secret
 * qu'on croit avoir dans le presse-papier et qu'on colle vide, c'est une clé perdue.
 *
 * Balisage : <button data-copier="#identifiant-de-la-cible">
 */
function activerLaCopie() {
    document.querySelectorAll('[data-copier]').forEach((bouton) => {
        bouton.addEventListener('click', async () => {
            const cible = document.querySelector(bouton.dataset.copier);

            if (!cible) return;

            const texte = (cible.innerText || cible.textContent || '').trim();
            retourVisuel(bouton, await copierDansLePressePapier(texte));
        });
    });
}

async function copierDansLePressePapier(texte) {
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(texte);
            return true;
        }
    } catch (e) {
        // On ne s'arrête pas : le repli ci-dessous a de bonnes chances de passer.
    }

    try {
        const zone = document.createElement('textarea');
        zone.value = texte;
        zone.setAttribute('readonly', '');
        zone.style.position = 'fixed';
        zone.style.top = '0';
        zone.style.opacity = '0';

        document.body.appendChild(zone);
        zone.select();

        const ok = document.execCommand('copy');
        document.body.removeChild(zone);

        return ok;
    } catch (e) {
        return false;
    }
}

function retourVisuel(bouton, reussi) {
    // Le libellé d'origine est mémorisé sur le bouton : deux clics rapprochés ne doivent pas
    // figer « Copié » en le prenant pour le texte initial.
    if (!bouton.dataset.libelleOriginal) {
        bouton.dataset.libelleOriginal = bouton.innerHTML;
    }

    bouton.innerHTML = reussi
        ? '<i data-lucide="check" class="w-3.5 h-3.5"></i> Copié'
        : '<i data-lucide="triangle-alert" class="w-3.5 h-3.5"></i> Sélectionnez à la main';

    if (window.dessinerLesIcones) window.dessinerLesIcones();

    clearTimeout(bouton._minuteurCopie);
    bouton._minuteurCopie = setTimeout(() => {
        bouton.innerHTML = bouton.dataset.libelleOriginal;
        if (window.dessinerLesIcones) window.dessinerLesIcones();
    }, 2000);
}

/**
 * TOUT DÉPLIER / TOUT REPLIER sur un arbre d'entités.
 *
 * Le pliage lui-même ne demande aucun JavaScript : il repose sur <details>/<summary>, que le
 * navigateur sait ouvrir et fermer tout seul, y compris au clavier. Ce qui suit ne sert qu'aux
 * deux boutons d'ensemble — parce que « ouvrir les quarante branches une par une » est
 * précisément ce qu'on cherche à éviter sur un réseau de cent églises.
 *
 * Écrit en délégation sur le document : la fiche client contient un arbre par installation, et
 * attacher un écouteur à chacun demanderait de les recompter à chaque rendu.
 */
function activerLesArbres() {
    document.addEventListener('click', (evenement) => {
        const bouton = evenement.target.closest('[data-arbre-action]');

        if (!bouton) return;

        const arbre = document.querySelector(bouton.dataset.arbreCible);

        if (!arbre) return;

        const ouvrir = bouton.dataset.arbreAction === 'ouvrir';

        arbre.querySelectorAll('details').forEach((branche) => {
            branche.open = ouvrir;
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    dessinerLesIcones();
    activerLesBandeaux();
    activerLaConversion();
    activerLaCopie();
    activerLesArbres();
});

// Un menu déroulant, une ligne ajoutée dynamiquement : les icônes doivent pouvoir être redessinées
// sans recharger la page.
window.dessinerLesIcones = dessinerLesIcones;
