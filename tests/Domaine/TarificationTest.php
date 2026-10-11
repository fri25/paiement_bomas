<?php

declare(strict_types=1);

namespace Tests\Domaine;

use App\Domaine\DemandePaiement;
use App\Domaine\Tarification;
use PHPUnit\Framework\TestCase;

final class TarificationTest extends TestCase
{
    private const ECOLE = [
        'contributions' => [
            ['id' => 'annuelle', 'libelle' => 'Annuelle', 'detail' => '', 'montant' => 15000, 'par_eleve' => true],
            ['id' => 'forfait',  'libelle' => 'Forfait',  'detail' => '', 'montant' => 7000,  'par_eleve' => false],
        ],
        'montant_libre' => ['actif' => true, 'libelle' => 'Autre montant'],
    ];

    private function demande(string $contribution, int $nbEleves, ?int $libre = null): DemandePaiement
    {
        $eleves = array_fill(0, $nbEleves, ['nom' => 'Awa', 'classe' => 'CP']);
        return new DemandePaiement($eleves, $contribution, $libre, ['nom' => 'X', 'prenom' => 'Y', 'telephone' => '0197000000', 'email' => '']);
    }

    public function testMontantParEleveEstMultiplie(): void
    {
        self::assertSame(45000, (new Tarification(self::ECOLE))->montant($this->demande('annuelle', 3)));
    }

    public function testForfaitNeDependPasDuNombreDEnfants(): void
    {
        self::assertSame(7000, (new Tarification(self::ECOLE))->montant($this->demande('forfait', 3)));
    }

    public function testMontantLibre(): void
    {
        self::assertSame(2500, (new Tarification(self::ECOLE))->montant($this->demande('libre', 1, 2500)));
    }

    public function testContributionInconnueOuLibreDesactive(): void
    {
        $ecole = self::ECOLE;
        $ecole['montant_libre']['actif'] = false;
        $tarification = new Tarification($ecole);

        self::assertFalse($tarification->existe('inconnue'));
        self::assertFalse($tarification->existe('libre'));
        self::assertTrue($tarification->existe('annuelle'));
    }
}
