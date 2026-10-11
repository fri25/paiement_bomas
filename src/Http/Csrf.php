<?php

declare(strict_types=1);

namespace App\Http;

/** Jeton anti-CSRF stocké en session, envoyé par le front dans l'en-tête X-CSRF-Token. */
final class Csrf
{
    private const CLE = '_csrf';

    public static function jeton(): string
    {
        self::demarrerSession();
        return $_SESSION[self::CLE] ??= bin2hex(random_bytes(32));
    }

    public static function champ(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::jeton(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function verifier(Request $requete): void
    {
        self::demarrerSession();
        $attendu = (string) ($_SESSION[self::CLE] ?? '');
        $recu = (string) ($requete->entete('x-csrf-token') ?? $requete->post('_csrf') ?? $_POST['_csrf'] ?? '');
        if ($attendu === '' || !hash_equals($attendu, $recu)) {
            throw new HttpException(419, 'Votre session a expiré. Rechargez la page, puis réessayez.');
        }
    }

    private static function demarrerSession(): void
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
}
