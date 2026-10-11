<?php

declare(strict_types=1);

namespace App\Infrastructure\FedaPay;

/**
 * Client de l'API REST FedaPay (https://docs.fedapay.com), appelé avec la clé SECRÈTE.
 * Ne jamais l'utiliser depuis le navigateur.
 */
final class ClientFedaPay
{
    public function __construct(
        private readonly string $cleSecrete,
        private readonly string $environnement,
        private readonly int $delai = 20,
    ) {
    }

    /**
     * @param array<string, mixed> $donnees
     * @return array<string, mixed> la transaction créée
     */
    public function creerTransaction(array $donnees): array
    {
        return $this->transaction($this->requete('POST', '/transactions', $donnees));
    }

    /** Lien de la page de paiement hébergée par FedaPay. */
    public function lienPaiement(string $transactionId): string
    {
        $reponse = $this->requete('POST', '/transactions/' . rawurlencode($transactionId) . '/token');
        return is_string($reponse['url'] ?? null)
            ? $reponse['url']
            : throw new ExceptionFedaPay('Réponse FedaPay sans lien de paiement.');
    }

    /** @return array<string, mixed> */
    public function transaction(string|array $idOuReponse): array
    {
        $reponse = is_array($idOuReponse)
            ? $idOuReponse
            : $this->requete('GET', '/transactions/' . rawurlencode($idOuReponse));

        $transaction = $reponse['v1/transaction'] ?? null;
        return is_array($transaction) && isset($transaction['id'])
            ? $transaction
            : throw new ExceptionFedaPay('Réponse FedaPay inattendue : transaction absente.');
    }

    /**
     * @param array<string, mixed>|null $corps
     * @return array<string, mixed>
     */
    private function requete(string $methode, string $chemin, ?array $corps = null): array
    {
        $base = $this->environnement === 'live' ? 'https://api.fedapay.com/v1' : 'https://sandbox-api.fedapay.com/v1';

        $curl = curl_init($base . $chemin);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST  => $methode,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->delai,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->cleSecrete,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);
        if ($corps !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($corps, JSON_UNESCAPED_UNICODE));
        }

        $brut = curl_exec($curl);
        $code = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $erreurReseau = curl_error($curl);
        curl_close($curl);

        if ($brut === false) {
            throw new ExceptionFedaPay("FedaPay injoignable : $erreurReseau");
        }
        $reponse = json_decode((string) $brut, true);
        if ($code >= 400 || !is_array($reponse)) {
            $message = is_array($reponse) ? ($reponse['message'] ?? '') : '';
            throw new ExceptionFedaPay("Erreur FedaPay HTTP $code $message", $code);
        }
        return $reponse;
    }
}
