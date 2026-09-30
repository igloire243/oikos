import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * VRAI SOUS LE POINT DE RUPTURE `sm` (640 px) — le téléphone tenu en portrait.
 *
 * Réactif, et non lu une seule fois : on tourne un téléphone, on redimensionne une fenêtre. Un
 * composant qui change de FORME (un tableau devenu cartes) ne peut pas se contenter de classes
 * `sm:hidden` : rendre les deux formes à la fois doublerait chaque champ, chaque identifiant et
 * chaque formulaire posé dans une cellule.
 */
export function useEstMobile(requete = '(max-width: 639px)') {
    const estMobile = ref(false);
    let liste = null;
    const suivre = () => (estMobile.value = liste.matches);

    onMounted(() => {
        liste = window.matchMedia(requete);
        suivre();
        liste.addEventListener('change', suivre);
    });
    onBeforeUnmount(() => liste?.removeEventListener('change', suivre));

    return estMobile;
}
