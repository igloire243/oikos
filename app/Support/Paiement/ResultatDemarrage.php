<?php

namespace App\Support\Paiement;

/**
 * Ce que renvoie une passerelle quand on lui demande de lancer un encaissement (« Payer
 * maintenant »). À ce stade rien n'est payé : on sait seulement si la demande a bien été poussée
 * vers le téléphone du client, et sous quelle référence la suivre.
 */
class ResultatDemarrage
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $orderNumber,
        public readonly string $message,
        public readonly array $brut = [],
    ) {}

    public static function succes(string $orderNumber, string $message, array $brut = []): self
    {
        return new self(true, $orderNumber, $message, $brut);
    }

    public static function echec(string $message, array $brut = []): self
    {
        return new self(false, null, $message, $brut);
    }
}
