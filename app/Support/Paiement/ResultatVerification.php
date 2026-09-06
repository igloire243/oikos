<?php

namespace App\Support\Paiement;

use App\Models\Paiement;

/**
 * L'état d'une transaction tel que la passerelle nous le confirme — soit reçu dans le webhook, soit
 * redemandé explicitement. C'est cette valeur, et jamais le corps brut du callback, qui autorise à
 * solder une facture.
 */
class ResultatVerification
{
    /**
     * @param  string  $statut  l'un des statuts de App\Models\Paiement (CONFIRME | ECHOUE | EN_ATTENTE)
     * @param  int  $montant  en centimes, tel que l'opérateur dit l'avoir encaissé
     */
    public function __construct(
        public readonly string $statut,
        public readonly ?string $reference,
        public readonly int $montant,
        public readonly string $devise,
        public readonly array $brut = [],
    ) {}

    public function estConfirme(): bool
    {
        return $this->statut === Paiement::CONFIRME;
    }

    public function estEchoue(): bool
    {
        return $this->statut === Paiement::ECHOUE;
    }
}
