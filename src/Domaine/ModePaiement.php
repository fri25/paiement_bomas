<?php

declare(strict_types=1);

namespace App\Domaine;

/**
 * Comment le parent a réglé : en ligne via FedaPay, en espèces auprès de la Direction,
 * ou avant la mise en service de la plateforme (la Direction l'inscrit après coup).
 */
enum ModePaiement: string
{
    case EnLigne = 'en_ligne';
    case Especes = 'especes';
    case Anterieur = 'anterieur';

    /** Paiements enregistrés par la Direction, déjà encaissés : ils ne passent pas par FedaPay. */
    public function estAuGuichet(): bool
    {
        return $this !== self::EnLigne;
    }

    public function libelle(): string
    {
        return match ($this) {
            self::EnLigne => 'En ligne (FedaPay)',
            self::Especes => 'Espèces, au guichet',
            self::Anterieur => 'Payé avant la plateforme',
        };
    }
}
