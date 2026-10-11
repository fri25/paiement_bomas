<?php

declare(strict_types=1);

namespace Tests\Domaine;

use App\Domaine\ErreurValidation;
use App\Domaine\Tarification;
use App\Domaine\ValidateurDemande;
use PHPUnit\Framework\TestCase;

final class ValidateurDemandeTest extends TestCase
{
    private ValidateurDemande $validateur;

    protected function setUp(): void
    {
        $ecole = [
            'classes'       => ['CI', 'CP'],
            'contributions' => [['id' => 'annuelle', 'libelle' => 'Annuelle', 'detail' => '', 'montant' => 15000, 'par_eleve' => true]],
            'montant_libre' => ['actif' => true, 'libelle' => 'Autre', 'detail' => '', 'minimum' => 500, 'maximum' => 100000],
            'max_eleves'    => 2,
        ];
        $this->validateur = new ValidateurDemande($ecole, new Tarification($ecole));
    }

    /** @return array<string, mixed> */
    private function donnees(array $surcharge = []): array
    {
        return array_replace_recursive([
            'eleves'       => [['nom' => '  HOUNSOU   Awa ', 'classe' => 'CP']],
            'contribution' => 'annuelle',
            'parent'       => ['nom' => 'HOUNSOU', 'prenom' => 'Marcelline', 'telephone' => '+229 01 97 00 00 00', 'email' => ''],
        ], $surcharge);
    }

    private function erreurs(array $donnees): array
    {
        try {
            $this->validateur->valider($donnees);
        } catch (ErreurValidation $e) {
            return $e->erreurs;
        }
        self::fail('Une erreur de validation était attendue.');
    }

    public function testDemandeValideEstNormalisee(): void
    {
        $demande = $this->validateur->valider($this->donnees());

        self::assertSame('HOUNSOU Awa', $demande->eleves[0]['nom']);
        self::assertSame('0197000000', $demande->parent['telephone']);
        self::assertNull($demande->montantLibre);
    }

    public function testLeMontantEnvoyeParLeNavigateurEstIgnore(): void
    {
        $demande = $this->validateur->valider($this->donnees(['montant' => 1, 'montant_libre' => 1]));
        self::assertNull($demande->montantLibre);
    }

    public function testClasseInconnueEtTelephoneInvalide(): void
    {
        $erreurs = $this->erreurs($this->donnees([
            'eleves' => [['classe' => 'CM9']],
            'parent' => ['telephone' => '97000000'],
        ]));

        self::assertArrayHasKey('eleves.0.classe', $erreurs);
        self::assertArrayHasKey('parent.telephone', $erreurs);
    }

    public function testTropDEnfants(): void
    {
        $eleve = ['nom' => 'Awa', 'classe' => 'CP'];
        self::assertArrayHasKey('eleves', $this->erreurs($this->donnees(['eleves' => [$eleve, $eleve, $eleve]])));
    }

    public function testMontantLibreSousLeMinimum(): void
    {
        $erreurs = $this->erreurs($this->donnees(['contribution' => 'libre', 'montant_libre' => '300']));
        self::assertArrayHasKey('montant_libre', $erreurs);
    }

    public function testMontantLibreValide(): void
    {
        $demande = $this->validateur->valider($this->donnees(['contribution' => 'libre', 'montant_libre' => '10 000']));
        self::assertSame(10000, $demande->montantLibre);
    }

    public function testEmailInvalide(): void
    {
        self::assertArrayHasKey('parent.email', $this->erreurs($this->donnees(['parent' => ['email' => 'pas-un-email']])));
    }
}
