<?php

declare(strict_types=1);

namespace Tests\Infrastructure;

use App\Domaine\StatutPaiement;
use App\Infrastructure\DepotPaiements;
use PDO;
use PHPUnit\Framework\TestCase;

final class DepotPaiementsAdminTest extends TestCase
{
    private PDO $pdo;
    private DepotPaiements $depot;

    protected function setUp(): void
    {
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

        // Jeu de données de test
        // 1. Payé, CE1
        $this->insererPaiement(1, 'CS-AAAA-1111-2222', 'annuelle', 'Contribution annuelle', '2026-2027', 15000, 'paye', 'KOUAME', 'Paul', '0197000001', 'paul@test.com', '2026-10-01 10:00:00', '2026-10-01 10:05:00');
        $this->insererEleve(1, 'Junior KOUAME', 'CE1');

        // 2. En attente, CP
        $this->insererPaiement(2, 'CS-BBBB-3333-4444', 'trimestre', 'Contribution du trimestre', '2026-2027', 5000, 'en_attente', 'MENSAH', 'Afi', '0197000002', 'afi@test.com', '2026-10-02 11:00:00', null);
        $this->insererEleve(2, 'Koffi MENSAH', 'CP');

        // 3. Payé avec 2 élèves (CM1 et CM2)
        $this->insererPaiement(3, 'CS-CCCC-5555-6666', 'annuelle', 'Contribution annuelle', '2026-2027', 30000, 'paye', 'DOSSA', 'Marc', '0197000003', '', '2026-10-03 14:00:00', '2026-10-03 14:02:00');
        $this->insererEleve(3, 'Elie DOSSA', 'CM1');
        $this->insererEleve(3, 'Sarah DOSSA', 'CM2');

        // 4. Refusé
        $this->insererPaiement(4, 'CS-DDDD-7777-8888', 'libre', 'Autre montant', '2026-2027', 2000, 'refuse', 'TOSSOU', 'Jean', '0197000004', '', '2026-10-04 09:00:00', null);
        $this->insererEleve(4, 'David TOSSOU', 'CI');
    }

    private function insererPaiement(
        int $id, string $ref, string $contrib, string $libelle, string $annee, int $montant,
        string $statut, string $nom, string $prenom, string $tel, string $email,
        string $creeLe, ?string $payeLe
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO paiements (id, reference, contribution, libelle, annee, montant, statut, parent_nom, parent_prenom, parent_telephone, parent_email, cree_le, maj_le, paye_le)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$id, $ref, $contrib, $libelle, $annee, $montant, $statut, $nom, $prenom, $tel, $email, $creeLe, $creeLe, $payeLe]);
    }

    private function insererEleve(int $paiementId, string $nom, string $classe): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO paiement_eleves (paiement_id, nom, classe) VALUES (?, ?, ?)");
        $stmt->execute([$paiementId, $nom, $classe]);
    }

    public function testTousEtCompterSansFiltre(): void
    {
        self::assertSame(4, $this->depot->compter());
        $tous = $this->depot->tous([], 10, 0);
        self::assertCount(4, $tous);
        // Doit être trié par date décroissante
        self::assertSame('CS-DDDD-7777-8888', $tous[0]->reference);
        self::assertSame('CS-AAAA-1111-2222', $tous[3]->reference);
        // Doit avoir ses élèves attachés
        self::assertCount(2, $tous[1]->eleves); // Paiement 3
    }

    public function testFiltreParStatut(): void
    {
        self::assertSame(2, $this->depot->compter(['statut' => 'paye']));
        $payes = $this->depot->tous(['statut' => 'paye']);
        self::assertCount(2, $payes);
        foreach ($payes as $p) {
            self::assertSame(StatutPaiement::Paye, $p->statut);
        }

        self::assertSame(1, $this->depot->compter(['statut' => 'en_attente']));
        self::assertSame(1, $this->depot->compter(['statut' => 'refuse']));
        self::assertSame(0, $this->depot->compter(['statut' => 'annule']));
    }

    public function testFiltreParClasse(): void
    {
        self::assertSame(1, $this->depot->compter(['classe' => 'CE1']));
        $ce1 = $this->depot->tous(['classe' => 'CE1']);
        self::assertCount(1, $ce1);
        self::assertSame('CS-AAAA-1111-2222', $ce1[0]->reference);

        // Paiement 3 a CM1 et CM2
        self::assertSame(1, $this->depot->compter(['classe' => 'CM1']));
        self::assertSame(1, $this->depot->compter(['classe' => 'CM2']));
    }

    public function testFiltreRechercheTexte(): void
    {
        // Recherche par nom de parent
        self::assertSame(1, $this->depot->compter(['recherche' => 'KOUAME']));
        // Recherche par nom d'élève
        self::assertSame(1, $this->depot->compter(['recherche' => 'Sarah']));
        // Recherche par téléphone
        self::assertSame(1, $this->depot->compter(['recherche' => '0197000003']));
        // Recherche par référence
        self::assertSame(1, $this->depot->compter(['recherche' => 'BBBB']));
    }

    public function testFiltreParDate(): void
    {
        self::assertSame(2, $this->depot->compter([
            'date_debut' => '2026-10-01',
            'date_fin'   => '2026-10-02',
        ]));
    }

    public function testStatistiques(): void
    {
        $stats = $this->depot->statistiques('2026-2027');
        self::assertSame(4, $stats['nb_total']);
        self::assertSame(45000, $stats['total_encaisse']); // 15000 + 30000
        self::assertSame(2, $stats['nb_paye']);
        self::assertSame(1, $stats['nb_attente']);
        self::assertSame(5000, $stats['montant_attente']);
        self::assertSame(1, $stats['nb_refuse']);
        self::assertSame(3, $stats['nb_eleves_payes']); // Junior, Elie, Sarah
    }

    public function testAjouterElevesEtRecensementParClasse(): void
    {
        // 1. Ajouter des élèves dans CI et CP
        $nbCI = $this->depot->ajouterEleves('CI', '2026-2027', ['David TOSSOU', 'Koffi AGBO']);
        self::assertSame(2, $nbCI);

        // Ne doit pas ajouter de doublon
        $nbDoublon = $this->depot->ajouterEleves('CI', '2026-2027', ['David TOSSOU']);
        self::assertSame(0, $nbDoublon);

        $this->depot->ajouterEleves('CP', '2026-2027', ['Koffi MENSAH', 'Nouveau CP']);
        $this->depot->ajouterEleves('CE1', '2026-2027', ['Junior KOUAME']);

        $classes = ['CI', 'CP', 'CE1', 'CE2', 'CM1', 'CM2'];
        $recensement = $this->depot->recensementParClasse('2026-2027', $classes);

        // Toutes les classes doivent être présentes
        self::assertArrayHasKey('CI', $recensement);
        self::assertArrayHasKey('CP', $recensement);
        self::assertArrayHasKey('CE1', $recensement);

        // CE1 : 1 élève (Junior KOUAME), paiement validé
        self::assertSame(1, $recensement['CE1']['total']);
        self::assertSame(1, $recensement['CE1']['nb_payes']);
        self::assertSame(0, $recensement['CE1']['nb_non_payes']);
        self::assertSame(100, $recensement['CE1']['taux']);
        self::assertSame('paye', $recensement['CE1']['eleves'][0]['statut']);

        // CP : 2 élèves (Koffi MENSAH en attente, Nouveau CP non payé)
        self::assertSame(2, $recensement['CP']['total']);
        self::assertSame(0, $recensement['CP']['nb_payes']);
        self::assertSame(1, $recensement['CP']['nb_attente']);
        self::assertSame(1, $recensement['CP']['nb_non_payes']);
        self::assertSame(0, $recensement['CP']['taux']);

        // CI : 2 élèves (David TOSSOU refusé => non_payé, Koffi AGBO non_payé)
        self::assertSame(2, $recensement['CI']['total']);
        self::assertSame(2, $recensement['CI']['nb_non_payes']);
    }

    public function testSupprimerEleve(): void
    {
        $this->depot->ajouterEleves('CM1', '2026-2027', ['Eleve A Supprimer']);
        $eleves = $this->depot->recensementParClasse('2026-2027', ['CM1'])['CM1']['eleves'];
        self::assertCount(1, $eleves);

        $id = $eleves[0]['id'];
        self::assertTrue($this->depot->supprimerEleve($id));

        $apres = $this->depot->recensementParClasse('2026-2027', ['CM1'])['CM1']['eleves'];
        self::assertCount(0, $apres);
    }
}
