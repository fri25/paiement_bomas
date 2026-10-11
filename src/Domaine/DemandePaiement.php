<?php

declare(strict_types=1);

namespace App\Domaine;

/** Demande de paiement validée, telle que saisie par le parent (sans montant : il est calculé). */
final class DemandePaiement
{
    /**
     * @param list<array{nom: string, classe: string}> $eleves
     * @param array{nom: string, prenom: string, telephone: string, email: string} $parent
     */
    public function __construct(
        public readonly array $eleves,
        public readonly string $contribution,
        public readonly ?int $montantLibre,
        public readonly array $parent,
    ) {
    }
}
