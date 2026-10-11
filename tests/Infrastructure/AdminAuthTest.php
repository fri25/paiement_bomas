<?php

declare(strict_types=1);

namespace Tests\Infrastructure;

use App\Support\AdminAuth;
use PHPUnit\Framework\TestCase;

final class AdminAuthTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }

    public function testNonConnecteParDefaut(): void
    {
        self::assertFalse(AdminAuth::estConnecte());
    }

    public function testConnexionEtDeconnexion(): void
    {
        AdminAuth::connecter();
        self::assertTrue(AdminAuth::estConnecte());

        AdminAuth::deconnecter();
        self::assertFalse(AdminAuth::estConnecte());
    }

    public function testVerificationMotDePasse(): void
    {
        self::assertTrue(AdminAuth::verifierMotDePasse('secret123', 'secret123'));
        self::assertFalse(AdminAuth::verifierMotDePasse('mauvais', 'secret123'));
        self::assertFalse(AdminAuth::verifierMotDePasse('', 'secret123'));
        self::assertFalse(AdminAuth::verifierMotDePasse('secret123', ''));
    }

    public function testFlashes(): void
    {
        AdminAuth::ajouterFlash('succes', 'Opération réussie');
        AdminAuth::ajouterFlash('erreur', 'Échec');

        $flashes = AdminAuth::consommerFlashes();
        self::assertCount(2, $flashes);
        self::assertSame('succes', $flashes[0]['type']);
        self::assertSame('Opération réussie', $flashes[0]['message']);

        // Vérifier que les messages ont été consommés et ne sont plus présents
        self::assertEmpty(AdminAuth::consommerFlashes());
    }
}
