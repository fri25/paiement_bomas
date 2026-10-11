<?php

declare(strict_types=1);

namespace App\Support;

/** Rendu des gabarits PHP de templates/, insérés dans templates/layout.php. */
final class Vue
{
    /** @param array<string, mixed> $partages variables disponibles dans tous les gabarits */
    public function __construct(
        private readonly string $dossier,
        private readonly array $partages = [],
    ) {
    }

    /** @param array<string, mixed> $variables */
    public function rendre(string $gabarit, array $variables = []): string
    {
        $variables = $variables + $this->partages;
        $contenu = $this->inclure($gabarit, $variables);
        return $this->inclure('layout', $variables + ['contenu' => $contenu]);
    }

    /** @param array<string, mixed> $variables */
    private function inclure(string $gabarit, array $variables): string
    {
        $fichier = $this->dossier . '/' . $gabarit . '.php';
        if (!is_file($fichier)) {
            throw new \RuntimeException("Gabarit introuvable : $gabarit");
        }
        extract($variables, EXTR_SKIP);
        ob_start();
        try {
            require $fichier;
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}
