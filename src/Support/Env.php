<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Lecture du fichier .env (format CLE=valeur). Les variables d'environnement
 * réelles du serveur ont priorité sur le fichier.
 */
final class Env
{
    /** @var array<string, string> */
    private static array $valeurs = [];

    public static function charger(string $fichier): void
    {
        if (!is_file($fichier)) {
            return;
        }
        foreach (file($fichier, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $ligne) {
            $ligne = trim($ligne);
            if ($ligne === '' || $ligne[0] === '#' || !str_contains($ligne, '=')) {
                continue;
            }
            [$cle, $valeur] = array_map('trim', explode('=', $ligne, 2));
            self::$valeurs[$cle] = trim($valeur, "\"'");
        }
    }

    public static function get(string $cle, ?string $defaut = null): ?string
    {
        $valeur = getenv($cle);
        if ($valeur === false || $valeur === '') {
            $valeur = self::$valeurs[$cle] ?? '';
        }
        return $valeur === '' ? $defaut : $valeur;
    }

    public static function bool(string $cle, bool $defaut = false): bool
    {
        $valeur = self::get($cle);
        return $valeur === null ? $defaut : filter_var($valeur, FILTER_VALIDATE_BOOL);
    }
}
