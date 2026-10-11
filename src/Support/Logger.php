<?php

declare(strict_types=1);

namespace App\Support;

/** Journal applicatif minimal (une ligne JSON par événement). */
final class Logger
{
    public function __construct(private readonly string $fichier)
    {
    }

    /** @param array<string, mixed> $contexte */
    public function info(string $message, array $contexte = []): void
    {
        $this->ecrire('INFO', $message, $contexte);
    }

    /** @param array<string, mixed> $contexte */
    public function avertissement(string $message, array $contexte = []): void
    {
        $this->ecrire('WARNING', $message, $contexte);
    }

    /** @param array<string, mixed> $contexte */
    public function erreur(string $message, array $contexte = []): void
    {
        $this->ecrire('ERROR', $message, $contexte);
    }

    /** @param array<string, mixed> $contexte */
    private function ecrire(string $niveau, string $message, array $contexte): void
    {
        $ligne = json_encode([
            'date'     => date('c'),
            'niveau'   => $niveau,
            'message'  => $message,
            'contexte' => $contexte,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

        if (@file_put_contents($this->fichier, $ligne . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            error_log($ligne);
        }
    }
}
