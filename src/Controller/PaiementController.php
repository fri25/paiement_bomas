<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domaine\ErreurValidation;
use App\Domaine\StatutPaiement;
use App\Domaine\ValidateurDemande;
use App\Http\Csrf;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Infrastructure\DepotPaiements;
use App\Infrastructure\FedaPay\ExceptionFedaPay;
use App\Service\ServicePaiement;
use App\Support\Vue;

final class PaiementController
{
    public function __construct(
        private readonly Vue $vue,
        private readonly ValidateurDemande $validateur,
        private readonly ServicePaiement $service,
        private readonly DepotPaiements $depot,
    ) {
    }

    /** POST /paiements (JSON) : crée le paiement et renvoie l'URL de paiement FedaPay. */
    public function creer(Request $requete): Response
    {
        Csrf::verifier($requete);

        try {
            $demande = $this->validateur->valider($requete->json());
            $redirection = $this->service->initier($demande);
        } catch (ErreurValidation $e) {
            throw new HttpException(422, $e->getMessage(), $e->erreurs);
        } catch (ExceptionFedaPay) {
            throw new HttpException(502, 'Le service de paiement est momentanément indisponible. Réessayez dans quelques minutes.');
        }

        return Response::json(['redirection' => $redirection], 201);
    }

    /** GET /paiements/retour?id=…&status=… : FedaPay y renvoie le parent après le paiement. */
    public function retour(Request $requete): Response
    {
        $transactionId = (string) ($requete->query['id'] ?? '');
        if (!preg_match('/^\d{1,20}$/', $transactionId)) {
            throw new HttpException(400, 'Lien de retour de paiement invalide.');
        }

        // Le paramètre "status" de l'URL est ignoré : on redemande le statut à FedaPay.
        $paiement = $this->service->synchroniser($transactionId)
            ?? throw new HttpException(404, 'Paiement introuvable.');

        return Response::redirection($this->service->urlRecu($paiement));
    }

    /** GET /recu/{reference} */
    public function recu(Request $requete, array $parametres): Response
    {
        $paiement = $this->depot->parReference((string) $parametres['reference'])
            ?? throw new HttpException(404, 'Reçu introuvable. Vérifiez la référence.');

        if ($paiement->statut === StatutPaiement::EnAttente) {
            $paiement = $this->service->actualiser($paiement);
        }

        $reponse = Response::html($this->vue->rendre('recu', [
            'titre'    => 'Reçu ' . $paiement->reference,
            'paiement' => $paiement,
        ]));
        return new Response($reponse->contenu, 200, $reponse->entetes + [
            'Cache-Control' => 'no-store',
            'X-Robots-Tag'  => 'noindex',
        ]);
    }
}
