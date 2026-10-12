<?php

declare(strict_types=1);

namespace App;

use App\Controller\AdminController;
use App\Controller\PageController;
use App\Controller\PaiementController;
use App\Controller\WebhookController;
use App\Domaine\Tarification;
use App\Domaine\ValidateurDemande;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Infrastructure\Database;
use App\Infrastructure\DepotPaiements;
use App\Infrastructure\FedaPay\ClientFedaPay;
use App\Infrastructure\FedaPay\SignatureWebhook;
use App\Service\ServicePaiement;
use App\Support\Logger;
use App\Support\Vue;

/** Assemble les dépendances, déclare les routes et transforme les erreurs en réponses. */
final class Application
{
    private Logger $journal;
    private Vue $vue;
    private Tarification $tarification;
    private ?ServicePaiement $service = null;
    private ?DepotPaiements $depot = null;

    /**
     * @param array<string, mixed> $config contenu de config/app.php
     * @param array<string, mixed> $ecole  contenu de config/ecole.php
     */
    public function __construct(
        private readonly array $config,
        private readonly array $ecole,
    ) {
        require_once __DIR__ . '/Support/helpers.php';

        error_reporting(E_ALL);
        ini_set('display_errors', $config['debug'] ? '1' : '0');

        $this->journal = new Logger($config['log']);
        $this->tarification = new Tarification($ecole);
        $this->vue = new Vue(dirname(__DIR__) . '/templates', [
            'ecole' => $ecole,
            'url'   => fn (string $chemin = ''): string => $config['url'] . $chemin,
            'asset' => fn (string $chemin): string => $config['url'] . '/assets/' . $chemin
                . '?v=' . (@filemtime(dirname(__DIR__) . '/public/assets/' . $chemin) ?: '1'),
        ]);
    }

    public function traiter(Request $requete): Response
    {
        $requete = $requete->avecBase(rtrim((string) parse_url($this->config['url'], PHP_URL_PATH), '/'));

        try {
            return $this->routes()->dispatcher($requete);
        } catch (HttpException $e) {
            return $this->erreur($requete, $e->statut, $e->getMessage(), $e->erreurs);
        } catch (\Throwable $e) {
            $this->journal->erreur($e->getMessage(), [
                'exception' => $e::class,
                'fichier'   => $e->getFile() . ':' . $e->getLine(),
                'route'     => $requete->methode . ' ' . $requete->chemin,
            ]);
            $message = $this->config['debug']
                ? $e::class . ' : ' . $e->getMessage()
                : 'Une erreur est survenue de notre côté. Réessayez dans un instant.';
            return $this->erreur($requete, 500, $message);
        }
    }

    private function routes(): Router
    {
        $routeur = new Router();

        $routeur->get('/', fn () => (new PageController($this->vue, $this->ecole))->formulaire());
        $routeur->post('/paiements', fn (Request $r) => $this->paiements()->creer($r));
        $routeur->get('/paiements/retour', fn (Request $r) => $this->paiements()->retour($r));
        $routeur->get('/recu/{reference}', fn (Request $r, array $p) => $this->paiements()->recu($r, $p));
        $routeur->post('/webhooks/fedapay', fn (Request $r) => (new WebhookController(
            new SignatureWebhook((string) $this->config['fedapay']['webhook_secret']),
            $this->service(),
            $this->journal,
        ))->fedapay($r));

        // Panneau d'administration (Directeur)
        $routeur->get('/admin', fn (Request $r) => $this->admin()->index($r));
        $routeur->get('/admin/connexion', fn (Request $r) => $this->admin()->pageConnexion($r));
        $routeur->post('/admin/connexion', fn (Request $r) => $this->admin()->traiterConnexion($r));
        $routeur->post('/admin/deconnexion', fn (Request $r) => $this->admin()->deconnexion($r));
        $routeur->get('/admin/export', fn (Request $r) => $this->admin()->exporterCsv($r));
        $routeur->post('/admin/paiements/guichet', fn (Request $r) => $this->admin()->encaisserAuGuichet($r));
        $routeur->post('/admin/paiements/{reference}/synchroniser', fn (Request $r, array $p) => $this->admin()->synchroniser($r, $p));
        $routeur->post('/admin/eleves', fn (Request $r) => $this->admin()->ajouterEleves($r));
        $routeur->post('/admin/eleves/{id}/supprimer', fn (Request $r, array $p) => $this->admin()->supprimerEleve($r, $p));

        return $routeur;
    }

    private function admin(): AdminController
    {
        return new AdminController(
            $this->vue,
            fn (): DepotPaiements => $this->depot(),
            fn (): ?ServicePaiement => $this->serviceOuNull(),
            new ValidateurDemande($this->ecole, $this->tarification),
            $this->tarification,
            $this->ecole,
            $this->config['admin'] ?? [],
            $this->config['url'],
        );
    }

    private function serviceOuNull(): ?ServicePaiement
    {
        try {
            return $this->service();
        } catch (\Throwable) {
            return null;
        }
    }

    private function paiements(): PaiementController
    {
        return new PaiementController(
            $this->vue,
            new ValidateurDemande($this->ecole, $this->tarification),
            fn (): ServicePaiement => $this->service(),
            $this->depot(),
        );
    }

    private function service(): ServicePaiement
    {
        $cleSecrete = (string) ($this->config['fedapay']['secret_key'] ?? '');
        if ($cleSecrete === '') {
            $this->journal->erreur('FEDAPAY_SECRET_KEY manquante dans .env : paiement impossible.');
            throw new HttpException(503, "Le paiement en ligne n'est pas disponible pour le moment. Contactez l'école.");
        }

        return $this->service ??= new ServicePaiement(
            $this->depot(),
            $this->tarification,
            new ClientFedaPay($cleSecrete, (string) $this->config['fedapay']['environment']),
            $this->ecole,
            $this->config['url'],
            $this->journal,
        );
    }

    /** La connexion à la base n'est ouverte que pour les routes qui en ont besoin. */
    private function depot(): DepotPaiements
    {
        return $this->depot ??= new DepotPaiements(Database::connecter($this->config['db']));
    }

    /** @param array<string, string> $erreurs */
    private function erreur(Request $requete, int $statut, string $message, array $erreurs = []): Response
    {
        if ($requete->attendJson() || str_starts_with($requete->chemin, '/webhooks/')) {
            return Response::json(['message' => $message, 'erreurs' => (object) $erreurs], $statut);
        }
        return Response::html($this->vue->rendre('erreur', ['statut' => $statut, 'message' => $message]), $statut);
    }
}
