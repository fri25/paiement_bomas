<?php

declare(strict_types=1);

namespace Tests\Infrastructure;

use App\Controller\AdminController;
use App\Http\Csrf;
use App\Http\Request;
use App\Infrastructure\DepotPaiements;
use App\Support\AdminAuth;
use App\Support\Vue;
use PDO;
use PHPUnit\Framework\TestCase;

final class AdminControllerTest extends TestCase
{
    private PDO $pdo;
    private DepotPaiements $depot;
    private AdminController $controleur;
    private array $ecole;

    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/src/Support/helpers.php';

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec("
            CREATE TABLE paiements (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reference VARCHAR(20) NOT NULL UNIQUE,
                contribution VARCHAR(40) NOT NULL,
                libelle VARCHAR(120) NOT NULL,
                annee VARCHAR(20) NOT NULL,
                montant INT NOT NULL,
                statut VARCHAR(20) NOT NULL DEFAULT 'en_attente',
                parent_nom VARCHAR(80) NOT NULL,
                parent_prenom VARCHAR(80) NOT NULL,
                parent_telephone CHAR(10) NOT NULL,
                parent_email VARCHAR(160) NOT NULL DEFAULT '',
                fedapay_transaction_id VARCHAR(40) NULL,
                fedapay_reference VARCHAR(60) NULL,
                cree_le DATETIME NOT NULL,
                maj_le DATETIME NOT NULL,
                paye_le DATETIME NULL
            );
            CREATE TABLE paiement_eleves (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                paiement_id INT NOT NULL,
                nom VARCHAR(120) NOT NULL,
                classe VARCHAR(20) NOT NULL
            );
            CREATE TABLE eleves (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nom VARCHAR(120) NOT NULL,
                classe VARCHAR(20) NOT NULL,
                annee VARCHAR(20) NOT NULL,
                cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $this->depot = new DepotPaiements($this->pdo);
        $this->ecole = require dirname(__DIR__, 2) . '/config/ecole.php';

        $vue = new Vue(dirname(__DIR__, 2) . '/templates', [
            'ecole' => $this->ecole,
            'url'   => fn (string $ch = '') => 'http://localhost/paiement' . $ch,
            'asset' => fn (string $ch) => 'http://localhost/paiement/assets/' . $ch,
        ]);

        $this->controleur = new AdminController(
            $vue,
            $this->depot,
            null,
            $this->ecole,
            ['password' => 'directeur2026'],
            'http://localhost/paiement',
        );
    }

    public function testRedirectionSiNonConnecte(): void
    {
        $requete = new Request('GET', '/admin');
        $reponse = $this->controleur->index($requete);
        self::assertSame(303, $reponse->statut);
        self::assertSame('http://localhost/paiement/admin/connexion', $reponse->entetes['Location']);
    }

    public function testPageConnexionAfficheFormulaire(): void
    {
        $requete = new Request('GET', '/admin/connexion');
        $reponse = $this->controleur->pageConnexion($requete);
        self::assertSame(200, $reponse->statut);
        self::assertStringContainsString('Espace Direction', $reponse->contenu);
        self::assertStringContainsString('Mot de passe Direction', $reponse->contenu);
    }

    public function testConnexionEchoueeAvecMauvaisMotDePasse(): void
    {
        $jeton = Csrf::jeton();
        $requete = new Request('POST', '/admin/connexion', [], [], '', '', [
            '_csrf'        => $jeton,
            'mot_de_passe' => 'mauvais_mdp',
        ]);

        $reponse = $this->controleur->traiterConnexion($requete);
        self::assertSame(303, $reponse->statut);
        self::assertSame('http://localhost/paiement/admin/connexion', $reponse->entetes['Location']);
        self::assertFalse(AdminAuth::estConnecte());

        $flashes = AdminAuth::consommerFlashes();
        self::assertNotEmpty($flashes);
        self::assertSame('erreur', $flashes[0]['type']);
    }

    public function testConnexionReussieAvecBonMotDePasse(): void
    {
        $jeton = Csrf::jeton();
        $requete = new Request('POST', '/admin/connexion', [], [], '', '', [
            '_csrf'        => $jeton,
            'mot_de_passe' => 'directeur2026',
        ]);

        $reponse = $this->controleur->traiterConnexion($requete);
        self::assertSame(303, $reponse->statut);
        self::assertSame('http://localhost/paiement/admin', $reponse->entetes['Location']);
        self::assertTrue(AdminAuth::estConnecte());
    }

    public function testTableauDeBordConnecte(): void
    {
        AdminAuth::connecter();

        // Insérer un paiement pour l'affichage
        $this->pdo->exec("
            INSERT INTO paiements (reference, contribution, libelle, annee, montant, statut, parent_nom, parent_prenom, parent_telephone, cree_le, maj_le)
            VALUES ('CS-TEST-1234-5678', 'annuelle', 'Contribution annuelle', '2026-2027', 15000, 'paye', 'AGOSSOU', 'Jean', '0197112233', datetime('now'), datetime('now'));
            INSERT INTO paiement_eleves (paiement_id, nom, classe) VALUES (1, 'Junior AGOSSOU', 'CM2');
        ");

        $requete = new Request('GET', '/admin');
        $reponse = $this->controleur->index($requete);
        self::assertSame(200, $reponse->statut);
        self::assertStringContainsString('Direction · Liste des paiements', $reponse->contenu);
        self::assertStringContainsString('CS-TEST-1234-5678', $reponse->contenu);
        self::assertStringContainsString('Junior AGOSSOU', $reponse->contenu);
        self::assertStringContainsString(fcfa(15000), $reponse->contenu);
    }

    public function testExportCsv(): void
    {
        AdminAuth::connecter();

        $this->pdo->exec("
            INSERT INTO paiements (reference, contribution, libelle, annee, montant, statut, parent_nom, parent_prenom, parent_telephone, cree_le, maj_le)
            VALUES ('CS-EXPO-1111-2222', 'annuelle', 'Contribution annuelle', '2026-2027', 15000, 'paye', 'TOSSOU', 'Aimé', '0197000000', datetime('now'), datetime('now'));
            INSERT INTO paiement_eleves (paiement_id, nom, classe) VALUES (1, 'Fleur TOSSOU', 'CE2');
        ");

        $requete = new Request('GET', '/admin/export');
        $reponse = $this->controleur->exporterCsv($requete);

        self::assertSame(200, $reponse->statut);
        self::assertSame('text/csv; charset=utf-8', $reponse->entetes['Content-Type']);
        self::assertStringContainsString('CS-EXPO-1111-2222', $reponse->contenu);
        self::assertStringContainsString('Fleur TOSSOU', $reponse->contenu);
        self::assertStringContainsString('15000', $reponse->contenu);
    }

    public function testDeconnexion(): void
    {
        AdminAuth::connecter();
        self::assertTrue(AdminAuth::estConnecte());

        $jeton = Csrf::jeton();
        $requete = new Request('POST', '/admin/deconnexion', [], [], '', '', ['_csrf' => $jeton]);
        $reponse = $this->controleur->deconnexion($requete);

        self::assertSame(303, $reponse->statut);
        self::assertSame('http://localhost/paiement/admin/connexion', $reponse->entetes['Location']);
        self::assertFalse(AdminAuth::estConnecte());
    }

    public function testAjouterElevesRoute(): void
    {
        AdminAuth::connecter();
        $jeton = Csrf::jeton();

        $requete = new Request('POST', '/admin/eleves', [], [], '', '', [
            '_csrf'  => $jeton,
            'classe' => 'CE1',
            'noms'   => "Alice KOFFI\nBob MENSAH",
        ]);

        $reponse = $this->controleur->ajouterEleves($requete);
        self::assertSame(303, $reponse->statut);
        self::assertStringContainsString('/admin#classe-CE1', $reponse->entetes['Location']);

        $recensement = $this->depot->recensementParClasse('2026-2027', ['CE1'])['CE1'];
        self::assertSame(2, $recensement['total']);
        self::assertSame(2, $recensement['nb_non_payes']);
    }

    public function testSupprimerEleveRoute(): void
    {
        AdminAuth::connecter();
        $jeton = Csrf::jeton();

        $this->depot->ajouterEleves('CP', '2026-2027', ['Eleve Unique']);
        $eleve = $this->depot->recensementParClasse('2026-2027', ['CP'])['CP']['eleves'][0];

        $requete = new Request('POST', '/admin/eleves/' . $eleve['id'] . '/supprimer', [], [], '', '', [
            '_csrf' => $jeton,
        ]);

        $reponse = $this->controleur->supprimerEleve($requete, ['id' => (string) $eleve['id']]);
        self::assertSame(303, $reponse->statut);

        $apres = $this->depot->recensementParClasse('2026-2027', ['CP'])['CP']['eleves'];
        self::assertCount(0, $apres);
    }
}
