<?php

declare(strict_types=1);

namespace App\Http;

final class Response
{
    /** @param array<string, string> $entetes */
    public function __construct(
        public readonly string $contenu = '',
        public readonly int $statut = 200,
        public readonly array $entetes = [],
    ) {
    }

    public static function html(string $contenu, int $statut = 200): self
    {
        return new self($contenu, $statut, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function json(mixed $donnees, int $statut = 200): self
    {
        return new self(
            (string) json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $statut,
            ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store'],
        );
    }

    public static function redirection(string $url, int $statut = 303): self
    {
        return new self('', $statut, ['Location' => $url]);
    }

    public function envoyer(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statut);
            foreach ($this->entetes as $nom => $valeur) {
                header("$nom: $valeur");
            }
        }
        echo $this->contenu;
    }
}
