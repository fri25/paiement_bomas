<?php

declare(strict_types=1);

namespace Tests\Domaine;

use App\Domaine\StatutPaiement;
use PHPUnit\Framework\TestCase;

final class StatutPaiementTest extends TestCase
{
    public function testCorrespondanceAvecFedaPay(): void
    {
        self::assertSame(StatutPaiement::Paye, StatutPaiement::depuisFedaPay('approved'));
        self::assertSame(StatutPaiement::Paye, StatutPaiement::depuisFedaPay('transferred'));
        self::assertSame(StatutPaiement::Refuse, StatutPaiement::depuisFedaPay('declined'));
        self::assertSame(StatutPaiement::EnAttente, StatutPaiement::depuisFedaPay('pending'));
    }

    public function testUnPaiementPayeNeRedevientPasEnAttente(): void
    {
        self::assertFalse(StatutPaiement::Paye->peutPasserA(StatutPaiement::EnAttente));
        self::assertFalse(StatutPaiement::Paye->peutPasserA(StatutPaiement::Refuse));
        self::assertTrue(StatutPaiement::Paye->peutPasserA(StatutPaiement::Rembourse));
        self::assertTrue(StatutPaiement::EnAttente->peutPasserA(StatutPaiement::Paye));
        self::assertFalse(StatutPaiement::Refuse->peutPasserA(StatutPaiement::Paye));
    }
}
