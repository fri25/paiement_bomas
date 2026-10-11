<?php

declare(strict_types=1);

namespace App\Domaine;

enum StatutPaiement: string
{
    case EnAttente = 'en_attente';
    case Paye = 'paye';
    case Refuse = 'refuse';
    case Annule = 'annule';
    case Rembourse = 'rembourse';

    /** Statuts FedaPay : pending, approved, declined, canceled, refunded, transferred. */
    public static function depuisFedaPay(string $statut): self
    {
        return match ($statut) {
            'approved', 'transferred' => self::Paye,
            'declined' => self::Refuse,
            'canceled' => self::Annule,
            'refunded' => self::Rembourse,
            default => self::EnAttente,
        };
    }

    /** Un paiement ne revient jamais en arrière, sauf remboursement d'un paiement payé. */
    public function peutPasserA(self $suivant): bool
    {
        return $this !== $suivant && match ($this) {
            self::EnAttente => true,
            self::Paye => $suivant === self::Rembourse,
            default => false,
        };
    }

    public function libelle(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente',
            self::Paye => 'Payé',
            self::Refuse => 'Refusé',
            self::Annule => 'Annulé',
            self::Rembourse => 'Remboursé',
        };
    }
}
