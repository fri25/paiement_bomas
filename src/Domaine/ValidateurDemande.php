<?php

declare(strict_types=1);

namespace App\Domaine;

/**
 * Valide et normalise les données envoyées par le formulaire.
 * Les règles sont les mêmes que côté navigateur, mais seules celles-ci font foi.
 */
final class ValidateurDemande
{
    /** @param array<string, mixed> $ecole contenu de config/ecole.php */
    public function __construct(
        private readonly array $ecole,
        private readonly Tarification $tarification,
    ) {
    }

    /**
     * @param array<string, mixed> $donnees
     * @param bool $telephoneObligatoire false au guichet : tous les parents n'ont pas de portable
     */
    public function valider(array $donnees, bool $telephoneObligatoire = true): DemandePaiement
    {
        $erreurs = [];

        // Enfants
        $eleves = [];
        $saisis = is_array($donnees['eleves'] ?? null) ? array_values($donnees['eleves']) : [];
        if ($saisis === []) {
            $erreurs['eleves'] = 'Ajoutez au moins un enfant.';
        } elseif (count($saisis) > $this->ecole['max_eleves']) {
            $erreurs['eleves'] = 'Vous pouvez payer pour ' . $this->ecole['max_eleves'] . ' enfants au maximum.';
        } else {
            foreach ($saisis as $i => $eleve) {
                $nom = self::texte($eleve['nom'] ?? '');
                $classe = self::texte($eleve['classe'] ?? '');
                if (mb_strlen($nom) < 2 || mb_strlen($nom) > 120) {
                    $erreurs["eleves.$i.nom"] = "Indiquez le nom de l'enfant.";
                }
                if (!in_array($classe, $this->ecole['classes'], true)) {
                    $erreurs["eleves.$i.classe"] = 'Choisissez la classe.';
                }
                $eleves[] = ['nom' => $nom, 'classe' => $classe];
            }
        }

        // Contribution
        $contribution = self::texte($donnees['contribution'] ?? '');
        $montantLibre = null;
        if (!$this->tarification->existe($contribution)) {
            $erreurs['contribution'] = 'Choisissez ce que vous payez.';
        } elseif ($contribution === Tarification::LIBRE) {
            $config = $this->ecole['montant_libre'];
            $montantLibre = (int) preg_replace('/\D/', '', (string) ($donnees['montant_libre'] ?? ''));
            if ($montantLibre < $config['minimum']) {
                $erreurs['montant_libre'] = $montantLibre === 0
                    ? 'Indiquez le montant à payer.'
                    : 'Le montant minimum est de ' . fcfa($config['minimum']) . '.';
            } elseif ($montantLibre > $config['maximum']) {
                $erreurs['montant_libre'] = 'Le montant maximum est de ' . fcfa($config['maximum']) . '.';
            }
        }

        // Parent
        $parent = is_array($donnees['parent'] ?? null) ? $donnees['parent'] : [];
        $nom = self::texte($parent['nom'] ?? '');
        $prenom = self::texte($parent['prenom'] ?? '');
        $telephone = self::telephone((string) ($parent['telephone'] ?? ''));
        $email = self::texte($parent['email'] ?? '');

        if (mb_strlen($nom) < 2 || mb_strlen($nom) > 80) {
            $erreurs['parent.nom'] = 'Indiquez votre nom.';
        }
        if (mb_strlen($prenom) < 2 || mb_strlen($prenom) > 80) {
            $erreurs['parent.prenom'] = 'Indiquez vos prénoms.';
        }
        if (($telephoneObligatoire || $telephone !== '') && !preg_match('/^01\d{8}$/', $telephone)) {
            $erreurs['parent.telephone'] = 'Entrez un numéro à 10 chiffres commençant par 01.';
        }
        if ($email !== '' && (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 160)) {
            $erreurs['parent.email'] = "Cette adresse e-mail n'est pas valide.";
        }

        if ($erreurs !== []) {
            throw new ErreurValidation($erreurs);
        }

        return new DemandePaiement(
            $eleves,
            $contribution,
            $montantLibre,
            ['nom' => $nom, 'prenom' => $prenom, 'telephone' => $telephone, 'email' => $email],
        );
    }

    /** Garde uniquement les chiffres et retire l'indicatif +229. */
    public static function telephone(string $saisie): string
    {
        $chiffres = (string) preg_replace('/\D/', '', $saisie);
        if (strlen($chiffres) === 13 && str_starts_with($chiffres, '229')) {
            $chiffres = substr($chiffres, 3);
        }
        return $chiffres;
    }

    private static function texte(mixed $valeur): string
    {
        return is_scalar($valeur) ? trim((string) preg_replace('/\s+/u', ' ', (string) $valeur)) : '';
    }
}
