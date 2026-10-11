<?php

declare(strict_types=1);

namespace App\Support;

/** Gestion de l'authentification et des messages flash pour l'espace Direction. */
final class AdminAuth
{
    private const CLE_AUTH = '_admin_connecte';
    private const CLE_DATE = '_admin_connecte_a';
    private const CLE_FLASH = '_admin_flash';

    public static function demarrerSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (!headers_sent()) {
            session_name('contribution_session');
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'use_strict_mode' => true,
            ]);
        }
    }

    public static function estConnecte(): bool
    {
        self::demarrerSession();
        return !empty($_SESSION[self::CLE_AUTH]);
    }

    public static function connecter(): void
    {
        self::demarrerSession();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION[self::CLE_AUTH] = true;
        $_SESSION[self::CLE_DATE] = time();
    }

    public static function deconnecter(): void
    {
        self::demarrerSession();
        unset($_SESSION[self::CLE_AUTH], $_SESSION[self::CLE_DATE]);
    }

    public static function verifierMotDePasse(string $motDePasseFourni, string $motDePasseAttendu): bool
    {
        if ($motDePasseAttendu === '') {
            return false;
        }
        return hash_equals($motDePasseAttendu, $motDePasseFourni);
    }

    public static function ajouterFlash(string $type, string $message): void
    {
        self::demarrerSession();
        $_SESSION[self::CLE_FLASH][] = ['type' => $type, 'message' => $message];
    }

    /** @return list<array{type: string, message: string}> */
    public static function consommerFlashes(): array
    {
        self::demarrerSession();
        $flashes = $_SESSION[self::CLE_FLASH] ?? [];
        unset($_SESSION[self::CLE_FLASH]);
        return is_array($flashes) ? $flashes : [];
    }
}
