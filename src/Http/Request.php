<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    /**
     * @param array<string, mixed>  $query
     * @param array<string, string> $entetes clés en minuscules
     * @param array<string, mixed>  $post
     */
    public function __construct(
        public readonly string $methode,
        public readonly string $chemin,
        public readonly array $query = [],
        public readonly array $entetes = [],
        public readonly string $corps = '',
        public readonly string $ip = '',
        public readonly array $post = [],
    ) {
    }

    public static function depuisGlobales(): self
    {
        $entetes = [];
        foreach ($_SERVER as $cle => $valeur) {
            if (str_starts_with($cle, 'HTTP_')) {
                $entetes[strtolower(str_replace('_', '-', substr($cle, 5)))] = (string) $valeur;
            } elseif (in_array($cle, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $entetes[strtolower(str_replace('_', '-', $cle))] = (string) $valeur;
            }
        }

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'),
            $_GET,
            $entetes,
            (string) file_get_contents('php://input'),
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            $_POST,
        );
    }

    public function entete(string $nom): ?string
    {
        return $this->entetes[strtolower($nom)] ?? null;
    }

    public function post(string $cle, mixed $defaut = null): mixed
    {
        return $this->post[$cle] ?? $defaut;
    }

    public function query(string $cle, mixed $defaut = null): mixed
    {
        return $this->query[$cle] ?? $defaut;
    }

    /** Corps JSON décodé ; tableau vide si le JSON est invalide. */
    public function json(): array
    {
        $donnees = json_decode($this->corps, true);
        return is_array($donnees) ? $donnees : [];
    }

    public function attendJson(): bool
    {
        return str_contains((string) $this->entete('accept'), 'application/json')
            || str_contains((string) $this->entete('content-type'), 'application/json');
    }

    /** Retire le préfixe d'installation (ex. /paiement) pour obtenir la route. */
    public function avecBase(string $base): self
    {
        $chemin = $this->chemin;
        foreach ([$base, '/public'] as $prefixe) {
            if ($prefixe !== '' && ($chemin === $prefixe || str_starts_with($chemin, $prefixe . '/'))) {
                $chemin = substr($chemin, strlen($prefixe));
            }
        }
        $chemin = '/' . trim($chemin, '/');

        return new self($this->methode, $chemin, $this->query, $this->entetes, $this->corps, $this->ip, $this->post);
    }
}
