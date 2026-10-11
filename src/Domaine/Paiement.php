<?php

declare(strict_types=1);

namespace App\Domaine;

/** Un paiement enregistré en base. */
final class Paiement
{
    /** @param list<array{nom: string, classe: string}> $eleves */
    public function __construct(
        public readonly int $id,
        public readonly string $reference,
        public readonly string $contribution,
        public readonly string $libelle,
        public readonly string $annee,
        public readonly int $montant,
        public readonly StatutPaiement $statut,
        public readonly string $parentNom,
        public readonly string $parentPrenom,
        public readonly string $parentTelephone,
        public readonly string $parentEmail,
        public readonly array $eleves,
        public readonly ?string $transactionId,
        public readonly ?string $referenceFedaPay,
        public readonly \DateTimeImmutable $creeLe,
        public readonly ?\DateTimeImmutable $payeLe,
    ) {
    }

    /** Génère une référence publique non devinable, ex. CS-7K2M-9QXA-4TPE. */
    public static function nouvelleReference(): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $blocs = [];
        for ($b = 0; $b < 3; $b++) {
            $bloc = '';
            for ($i = 0; $i < 4; $i++) {
                $bloc .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $blocs[] = $bloc;
        }
        return 'CS-' . implode('-', $blocs);
    }
}
