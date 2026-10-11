<?php

declare(strict_types=1);

namespace App\Service;

use App\Domaine\DemandePaiement;
use App\Domaine\Paiement;
use App\Domaine\StatutPaiement;
use App\Domaine\Tarification;
use App\Infrastructure\DepotPaiements;
use App\Infrastructure\FedaPay\ClientFedaPay;
use App\Infrastructure\FedaPay\ExceptionFedaPay;
use App\Support\Logger;

/**
 * Cycle de vie d'un paiement :
 *  1. initier()       enregistre le paiement (en attente), crée la transaction FedaPay, renvoie le lien de paiement ;
 *  2. synchroniser()  relit la transaction chez FedaPay (retour navigateur, webhook, consultation du reçu)
 *                     et met à jour le statut. Seule la réponse de FedaPay fait foi, jamais les paramètres d'URL.
 */
final class ServicePaiement
{
    /** @param array<string, mixed> $ecole contenu de config/ecole.php */
    public function __construct(
        private readonly DepotPaiements $depot,
        private readonly Tarification $tarification,
        private readonly ClientFedaPay $fedapay,
        private readonly array $ecole,
        private readonly string $urlApplication,
        private readonly Logger $journal,
    ) {
    }

    /** @return string URL de la page de paiement FedaPay vers laquelle envoyer le parent */
    public function initier(DemandePaiement $demande): string
    {
        $montant = $this->tarification->montant($demande);
        $libelle = $this->tarification->libelle($demande->contribution);
        $paiement = $this->depot->creer($demande, $libelle, $this->ecole['annee'], $montant);

        try {
            $transaction = $this->fedapay->creerTransaction($this->donneesTransaction($paiement, $demande));
            $transactionId = (string) $transaction['id'];
            $this->depot->associerTransaction($paiement->id, $transactionId);
            $lien = $this->fedapay->lienPaiement($transactionId);
        } catch (ExceptionFedaPay $e) {
            $this->depot->changerStatut($paiement, StatutPaiement::Annule, null);
            $this->journal->erreur('Création de la transaction FedaPay impossible', [
                'reference' => $paiement->reference,
                'erreur'    => $e->getMessage(),
            ]);
            throw $e;
        }

        $this->journal->info('Paiement initié', [
            'reference'   => $paiement->reference,
            'transaction' => $transactionId,
            'montant'     => $montant,
        ]);
        return $lien;
    }

    /** Met à jour le paiement lié à cette transaction d'après FedaPay. Null si la transaction nous est inconnue. */
    public function synchroniser(string $transactionId): ?Paiement
    {
        $paiement = $this->depot->parTransaction($transactionId);
        if ($paiement === null) {
            $this->journal->avertissement('Transaction FedaPay inconnue', ['transaction' => $transactionId]);
            return null;
        }
        return $this->actualiser($paiement);
    }

    public function actualiser(Paiement $paiement): Paiement
    {
        if ($paiement->transactionId === null) {
            return $paiement;
        }

        try {
            $transaction = $this->fedapay->transaction($paiement->transactionId);
        } catch (ExceptionFedaPay $e) {
            // On garde le statut actuel : le webhook ou une prochaine consultation le mettra à jour.
            $this->journal->avertissement('Lecture de la transaction FedaPay impossible', [
                'reference' => $paiement->reference,
                'erreur'    => $e->getMessage(),
            ]);
            return $paiement;
        }

        if ((int) ($transaction['amount'] ?? -1) !== $paiement->montant) {
            $this->journal->erreur('Montant FedaPay différent du montant attendu', [
                'reference' => $paiement->reference,
                'attendu'   => $paiement->montant,
                'recu'      => $transaction['amount'] ?? null,
            ]);
            return $paiement;
        }

        $nouveau = StatutPaiement::depuisFedaPay((string) ($transaction['status'] ?? ''));
        if (!$paiement->statut->peutPasserA($nouveau)) {
            return $paiement;
        }

        $reference = isset($transaction['reference']) ? (string) $transaction['reference'] : null;
        if ($this->depot->changerStatut($paiement, $nouveau, $reference)) {
            $this->journal->info('Statut du paiement mis à jour', [
                'reference' => $paiement->reference,
                'de'        => $paiement->statut->value,
                'vers'      => $nouveau->value,
            ]);
        }
        return $this->depot->parReference($paiement->reference) ?? $paiement;
    }

    public function urlRecu(Paiement $paiement): string
    {
        return $this->urlApplication . '/recu/' . rawurlencode($paiement->reference);
    }

    /** @return array<string, mixed> */
    private function donneesTransaction(Paiement $paiement, DemandePaiement $demande): array
    {
        $nombre = count($demande->eleves);
        $description = sprintf(
            '%s %s – %d enfant%s – %s',
            $paiement->libelle, $paiement->annee, $nombre, $nombre > 1 ? 's' : '', $this->ecole['nom'],
        );

        $client = [
            'firstname'    => $demande->parent['prenom'],
            'lastname'     => $demande->parent['nom'],
            'phone_number' => ['number' => '+229' . $demande->parent['telephone'], 'country' => 'bj'],
        ];
        if ($demande->parent['email'] !== '') {
            $client['email'] = $demande->parent['email'];
        }

        return [
            'description'     => mb_substr($description, 0, 200),
            'amount'          => $paiement->montant,
            'currency'        => ['iso' => 'XOF'],
            'callback_url'    => $this->urlApplication . '/paiements/retour',
            'customer'        => $client,
            'custom_metadata' => [
                'reference'      => $paiement->reference,
                'ecole'          => $this->ecole['nom'],
                'annee_scolaire' => $paiement->annee,
                'contribution'   => $paiement->contribution,
                'nombre_enfants' => (string) $nombre,
                'enfants'        => implode(' ; ', array_map(
                    static fn (array $e): string => $e['nom'] . ' (' . $e['classe'] . ')',
                    $demande->eleves,
                )),
            ],
        ];
    }
}
