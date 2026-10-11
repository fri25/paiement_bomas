<?php

declare(strict_types=1);

namespace App\Http;

/** Routeur minimal : "GET /recu/{reference}" => callable(Request, array $parametres): Response */
final class Router
{
    /** @var list<array{string, string, callable}> */
    private array $routes = [];

    public function get(string $motif, callable $action): void
    {
        $this->routes[] = ['GET', $motif, $action];
    }

    public function post(string $motif, callable $action): void
    {
        $this->routes[] = ['POST', $motif, $action];
    }

    public function dispatcher(Request $requete): Response
    {
        $methodeRefusee = false;
        foreach ($this->routes as [$methode, $motif, $action]) {
            $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $motif) . '$#';
            if (!preg_match($regex, $requete->chemin, $correspondances)) {
                continue;
            }
            if ($methode !== $requete->methode && !($methode === 'GET' && $requete->methode === 'HEAD')) {
                $methodeRefusee = true;
                continue;
            }
            $parametres = array_filter($correspondances, 'is_string', ARRAY_FILTER_USE_KEY);
            return $action($requete, $parametres);
        }

        throw $methodeRefusee
            ? new HttpException(405, 'Méthode non autorisée.')
            : new HttpException(404, "Cette page n'existe pas.");
    }
}
