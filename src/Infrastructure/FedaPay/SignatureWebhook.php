<?php

declare(strict_types=1);

namespace App\Infrastructure\FedaPay;

/**
 * Vérifie l'en-tête X-FEDAPAY-SIGNATURE : "t=<timestamp>,s=<hmac>",
 * où hmac = HMAC-SHA256("<timestamp>.<corps brut>", secret du webhook).
 */
final class SignatureWebhook
{
    public function __construct(
        private readonly string $secret,
        private readonly int $tolerance = 300,
    ) {
    }

    public function estValide(string $corps, ?string $entete, ?int $maintenant = null): bool
    {
        if ($this->secret === '' || $entete === null || $entete === '') {
            return false;
        }

        $horodatage = null;
        $signatures = [];
        foreach (explode(',', $entete) as $partie) {
            [$cle, $valeur] = array_pad(explode('=', trim($partie), 2), 2, '');
            if ($cle === 't' && ctype_digit($valeur)) {
                $horodatage = (int) $valeur;
            } elseif ($cle === 's') {
                $signatures[] = $valeur;
            }
        }
        if ($horodatage === null || $signatures === []) {
            return false;
        }
        if (abs(($maintenant ?? time()) - $horodatage) > $this->tolerance) {
            return false;
        }

        $attendue = hash_hmac('sha256', $horodatage . '.' . $corps, $this->secret);
        foreach ($signatures as $signature) {
            if (hash_equals($attendue, $signature)) {
                return true;
            }
        }
        return false;
    }
}
