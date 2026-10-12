<?php

declare(strict_types=1);

namespace App\Domaine;

/** Source de vérité des montants : la configuration de l'école, jamais le navigateur. */
final class Tarification
{
    public const LIBRE = 'libre';

    /** @param array<string, mixed> $ecole contenu de config/ecole.php */
    public function __construct(private readonly array $ecole)
    {
    }

    /** @return array{id: string, libelle: string, detail: string, montant: int|array<string, int>, par_eleve: bool}|null */
    public function contribution(string $id): ?array
    {
        foreach ($this->ecole['contributions'] as $contribution) {
            if ($contribution['id'] === $id) {
                return $contribution;
            }
        }
        return null;
    }

    public function existe(string $id): bool
    {
        return $this->contribution($id) !== null || ($id === self::LIBRE && $this->libreActif());
    }

    public function libreActif(): bool
    {
        return (bool) ($this->ecole['montant_libre']['actif'] ?? false);
    }

    public function libelle(string $id): string
    {
        return $this->contribution($id)['libelle'] ?? $this->ecole['montant_libre']['libelle'];
    }

    public function montant(DemandePaiement $demande): int
    {
        $contribution = $this->contribution($demande->contribution);
        if ($contribution === null) {
            return (int) $demande->montantLibre;
        }
        if (is_array($contribution['montant'])) {
            // Tarif par classe : chaque enfant paie le montant de sa classe.
            return array_sum(array_map(
                static fn (array $eleve): int => (int) ($contribution['montant'][$eleve['classe']] ?? 0),
                $demande->eleves,
            ));
        }
        return $contribution['par_eleve']
            ? $contribution['montant'] * count($demande->eleves)
            : $contribution['montant'];
    }
}
