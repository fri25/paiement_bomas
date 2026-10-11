<?php

declare(strict_types=1);

namespace App\Http;

/** Erreur à renvoyer telle quelle au client (404, 419, 422…). Le message est affichable. */
class HttpException extends \RuntimeException
{
    /** @param array<string, string> $erreurs erreurs par champ */
    public function __construct(
        public readonly int $statut,
        string $message,
        public readonly array $erreurs = [],
    ) {
        parent::__construct($message, $statut);
    }
}
