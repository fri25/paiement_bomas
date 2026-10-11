<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Infrastructure\FedaPay\SignatureWebhook;
use App\Service\ServicePaiement;
use App\Support\Logger;

/**
 * POST /webhooks/fedapay : FedaPay notifie chaque changement de transaction.
 * Garantit l'enregistrement du paiement même si le parent ferme son navigateur avant le retour.
 */
final class WebhookController
{
    public function __construct(
        private readonly SignatureWebhook $signature,
        private readonly ServicePaiement $service,
        private readonly Logger $journal,
    ) {
    }

    public function fedapay(Request $requete): Response
    {
        if (!$this->signature->estValide($requete->corps, $requete->entete('x-fedapay-signature'))) {
            $this->journal->avertissement('Webhook FedaPay refusé : signature invalide', ['ip' => $requete->ip]);
            throw new HttpException(400, 'Signature invalide.');
        }

        $evenement = $requete->json();
        $transactionId = $evenement['entity']['id'] ?? null;
        $this->journal->info('Webhook FedaPay reçu', [
            'evenement'   => $evenement['name'] ?? null,
            'transaction' => $transactionId,
        ]);

        // Le contenu de l'événement sert seulement à savoir QUELLE transaction relire.
        if (is_int($transactionId) || (is_string($transactionId) && ctype_digit($transactionId))) {
            $this->service->synchroniser((string) $transactionId);
        }

        return Response::json(['recu' => true]);
    }
}
