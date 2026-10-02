<?php

namespace App\Metier\Commerce\Passerelles;

use App\Models\DemandePaiement;

/**
 * UN FOURNISSEUR DE PAIEMENT, vu du côté de la console — deux questions, pas davantage.
 *
 * Brancher un vrai fournisseur (mobile money, carte) revient à écrire UNE classe qui répond à ces deux
 * questions et à la déclarer dans `PaiementsEnLigne::passerelle()`. Rien d'autre ne le connaît : ni
 * la facture, ni les écrans, ni l'encaissement.
 */
interface Passerelle
{
    /** Où envoyer le client pour qu'il paie. */
    public function initier(DemandePaiement $demande): string;

    /**
     * Ce que le fournisseur dit de cette demande, interrogé DIRECTEMENT (jamais déduit de ce que le
     * navigateur du client raconte au retour).
     *
     * @return array{statut: string, montant_centimes: int, devise: string, reference_externe: ?string}|null
     *                                                                                                       `statut` vaut 'PAYE', 'ECHEC' ou 'EN_ATTENTE' ; null si le fournisseur ne connaît pas la demande.
     */
    public function verifier(DemandePaiement $demande): ?array;
}
