<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domaine\DemandePaiement;
use App\Domaine\ModePaiement;
use App\Domaine\Paiement;
use App\Domaine\StatutPaiement;
use PDO;

/** Accès aux tables paiements et paiement_eleves. */
final class DepotPaiements
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * En ligne : le paiement part en attente de FedaPay.
     * Au guichet (espèces ou paiement antérieur) : la Direction a déjà reçu l'argent,
     * il est enregistré directement comme payé, à la date indiquée ($payeLe) ou maintenant.
     */
    public function creer(
        DemandePaiement $demande,
        string $libelle,
        string $annee,
        int $montant,
        ModePaiement $mode = ModePaiement::EnLigne,
        ?\DateTimeImmutable $payeLe = null,
    ): Paiement {
        $maintenant = date('Y-m-d H:i:s');
        $statut = $mode->estAuGuichet() ? StatutPaiement::Paye : StatutPaiement::EnAttente;

        $this->pdo->beginTransaction();
        try {
            $reference = $this->referenceLibre();
            $this->pdo->prepare(
                'INSERT INTO paiements (reference, contribution, libelle, annee, montant, statut, mode_paiement,
                    parent_nom, parent_prenom, parent_telephone, parent_email, cree_le, maj_le, paye_le)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $reference, $demande->contribution, $libelle, $annee, $montant, $statut->value, $mode->value,
                $demande->parent['nom'], $demande->parent['prenom'], $demande->parent['telephone'], $demande->parent['email'],
                $maintenant, $maintenant,
                $statut === StatutPaiement::Paye ? ($payeLe?->format('Y-m-d H:i:s') ?? $maintenant) : null,
            ]);
            $id = (int) $this->pdo->lastInsertId();

            $insertion = $this->pdo->prepare('INSERT INTO paiement_eleves (paiement_id, nom, classe) VALUES (?, ?, ?)');
            $recensement = $this->pdo->prepare('
                INSERT INTO eleves (nom, classe, annee)
                SELECT ?, ?, ?
                WHERE NOT EXISTS (SELECT 1 FROM eleves WHERE nom = ? AND classe = ? AND annee = ?)
            ');
            foreach ($demande->eleves as $eleve) {
                $insertion->execute([$id, $eleve['nom'], $eleve['classe']]);
                try {
                    $recensement->execute([$eleve['nom'], $eleve['classe'], $annee, $eleve['nom'], $eleve['classe'], $annee]);
                } catch (\Throwable) {
                    // Si table eleves non disponible, ne bloque pas la transaction
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $this->trouver('id = ?', [$id]) ?? throw new \LogicException('Paiement introuvable après création.');
    }

    public function associerTransaction(int $id, string $transactionId): void
    {
        $this->pdo->prepare('UPDATE paiements SET fedapay_transaction_id = ? WHERE id = ?')
            ->execute([$transactionId, $id]);
    }

    /**
     * Change le statut seulement si le paiement est toujours dans l'état attendu
     * (évite qu'un webhook et un retour navigateur simultanés se marchent dessus).
     */
    public function changerStatut(Paiement $paiement, StatutPaiement $nouveau, ?string $referenceFedaPay): bool
    {
        $requete = $this->pdo->prepare(
            'UPDATE paiements
                SET statut = ?, fedapay_reference = COALESCE(?, fedapay_reference),
                    paye_le = IF(? = \'paye\', NOW(), paye_le)
              WHERE id = ? AND statut = ?'
        );
        $requete->execute([$nouveau->value, $referenceFedaPay, $nouveau->value, $paiement->id, $paiement->statut->value]);
        return $requete->rowCount() === 1;
    }

    public function parReference(string $reference): ?Paiement
    {
        return $this->trouver('reference = ?', [$reference]);
    }

    public function parTransaction(string $transactionId): ?Paiement
    {
        return $this->trouver('fedapay_transaction_id = ?', [$transactionId]);
    }

    /**
     * @param array<string, mixed> $filtres
     * @return list<Paiement>
     */
    public function tous(array $filtres = [], int $limite = 25, int $decalage = 0): array
    {
        [$where, $parametres] = $this->construireClauseWhere($filtres);
        $limite = max(1, min(1000, $limite));
        $decalage = max(0, $decalage);

        $sql = "SELECT * FROM paiements $where ORDER BY cree_le DESC, id DESC LIMIT $limite OFFSET $decalage";
        $requete = $this->pdo->prepare($sql);
        $requete->execute($parametres);
        $lignes = $requete->fetchAll();
        if ($lignes === []) {
            return [];
        }

        $ids = array_map(static fn (array $l): int => (int) $l['id'], $lignes);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $reqEleves = $this->pdo->prepare(
            "SELECT paiement_id, nom, classe FROM paiement_eleves WHERE paiement_id IN ($placeholders) ORDER BY id"
        );
        $reqEleves->execute($ids);

        $elevesParPaiement = [];
        foreach ($reqEleves->fetchAll() as $ligneEleve) {
            $elevesParPaiement[(int) $ligneEleve['paiement_id']][] = [
                'nom'    => $ligneEleve['nom'],
                'classe' => $ligneEleve['classe'],
            ];
        }

        return array_map(
            static fn (array $ligne): Paiement => new Paiement(
                (int) $ligne['id'],
                $ligne['reference'],
                $ligne['contribution'],
                $ligne['libelle'],
                $ligne['annee'],
                (int) $ligne['montant'],
                StatutPaiement::from($ligne['statut']),
                $ligne['parent_nom'],
                $ligne['parent_prenom'],
                $ligne['parent_telephone'],
                $ligne['parent_email'],
                $elevesParPaiement[(int) $ligne['id']] ?? [],
                $ligne['fedapay_transaction_id'],
                $ligne['fedapay_reference'],
                new \DateTimeImmutable($ligne['cree_le']),
                $ligne['paye_le'] ? new \DateTimeImmutable($ligne['paye_le']) : null,
                ModePaiement::tryFrom((string) ($ligne['mode_paiement'] ?? '')) ?? ModePaiement::EnLigne,
            ),
            $lignes
        );
    }

    /** @param array<string, mixed> $filtres */
    public function compter(array $filtres = []): int
    {
        [$where, $parametres] = $this->construireClauseWhere($filtres);
        $requete = $this->pdo->prepare("SELECT COUNT(*) FROM paiements $where");
        $requete->execute($parametres);
        return (int) $requete->fetchColumn();
    }

    /** @return array{nb_total: int, total_encaisse: int, nb_paye: int, nb_attente: int, montant_attente: int, nb_refuse: int, nb_eleves_payes: int} */
    public function statistiques(?string $annee = null): array
    {
        $condition = $annee !== null ? 'WHERE annee = ?' : '';
        $parametres = $annee !== null ? [$annee] : [];

        $sql = "SELECT 
                    COUNT(*) AS nb_total,
                    COALESCE(SUM(CASE WHEN statut = 'paye' THEN montant ELSE 0 END), 0) AS total_encaisse,
                    COALESCE(SUM(CASE WHEN statut = 'paye' THEN 1 ELSE 0 END), 0) AS nb_paye,
                    COALESCE(SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END), 0) AS nb_attente,
                    COALESCE(SUM(CASE WHEN statut = 'en_attente' THEN montant ELSE 0 END), 0) AS montant_attente,
                    COALESCE(SUM(CASE WHEN statut IN ('refuse', 'annule') THEN 1 ELSE 0 END), 0) AS nb_refuse
                FROM paiements $condition";

        $req = $this->pdo->prepare($sql);
        $req->execute($parametres);
        $stats = $req->fetch() ?: [];

        $sqlEleves = "SELECT COUNT(DISTINCT pe.id)
                      FROM paiement_eleves pe
                      JOIN paiements p ON pe.paiement_id = p.id
                      WHERE p.statut = 'paye'" . ($annee !== null ? " AND p.annee = ?" : "");
        $reqEleves = $this->pdo->prepare($sqlEleves);
        $reqEleves->execute($parametres);
        $nbEleves = (int) $reqEleves->fetchColumn();

        return [
            'nb_total'        => (int) ($stats['nb_total'] ?? 0),
            'total_encaisse'  => (int) ($stats['total_encaisse'] ?? 0),
            'nb_paye'         => (int) ($stats['nb_paye'] ?? 0),
            'nb_attente'      => (int) ($stats['nb_attente'] ?? 0),
            'montant_attente' => (int) ($stats['montant_attente'] ?? 0),
            'nb_refuse'       => (int) ($stats['nb_refuse'] ?? 0),
            'nb_eleves_payes' => $nbEleves,
        ];
    }

    /**
     * @param array<string, mixed> $filtres
     * @return array{0: string, 1: list<mixed>}
     */
    private function construireClauseWhere(array $filtres): array
    {
        $conditions = [];
        $parametres = [];

        $recherche = trim((string) ($filtres['recherche'] ?? ''));
        if ($recherche !== '') {
            $like = '%' . $recherche . '%';
            $conditions[] = '(
                paiements.reference LIKE ?
                OR paiements.parent_nom LIKE ?
                OR paiements.parent_prenom LIKE ?
                OR paiements.parent_telephone LIKE ?
                OR paiements.parent_email LIKE ?
                OR paiements.fedapay_reference LIKE ?
                OR EXISTS (
                    SELECT 1 FROM paiement_eleves pe
                    WHERE pe.paiement_id = paiements.id AND pe.nom LIKE ?
                )
            )';
            array_push($parametres, $like, $like, $like, $like, $like, $like, $like);
        }

        $statut = trim((string) ($filtres['statut'] ?? ''));
        if ($statut !== '' && StatutPaiement::tryFrom($statut) !== null) {
            $conditions[] = 'paiements.statut = ?';
            $parametres[] = $statut;
        }

        $classe = trim((string) ($filtres['classe'] ?? ''));
        if ($classe !== '') {
            $conditions[] = 'EXISTS (
                SELECT 1 FROM paiement_eleves pe
                WHERE pe.paiement_id = paiements.id AND pe.classe = ?
            )';
            $parametres[] = $classe;
        }

        $contribution = trim((string) ($filtres['contribution'] ?? ''));
        if ($contribution !== '') {
            $conditions[] = 'paiements.contribution = ?';
            $parametres[] = $contribution;
        }

        $dateDebut = trim((string) ($filtres['date_debut'] ?? ''));
        if ($dateDebut !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDebut)) {
            $conditions[] = 'paiements.cree_le >= ?';
            $parametres[] = $dateDebut . ' 00:00:00';
        }

        $dateFin = trim((string) ($filtres['date_fin'] ?? ''));
        if ($dateFin !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFin)) {
            $conditions[] = 'paiements.cree_le <= ?';
            $parametres[] = $dateFin . ' 23:59:59';
        }

        $annee = trim((string) ($filtres['annee'] ?? ''));
        if ($annee !== '') {
            $conditions[] = 'paiements.annee = ?';
            $parametres[] = $annee;
        }

        $clause = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';
        return [$clause, $parametres];
    }

    /** @param list<mixed> $parametres */
    private function trouver(string $condition, array $parametres): ?Paiement
    {
        $requete = $this->pdo->prepare("SELECT * FROM paiements WHERE $condition LIMIT 1");
        $requete->execute($parametres);
        $ligne = $requete->fetch();
        if ($ligne === false) {
            return null;
        }

        $eleves = $this->pdo->prepare('SELECT nom, classe FROM paiement_eleves WHERE paiement_id = ? ORDER BY id');
        $eleves->execute([$ligne['id']]);

        return new Paiement(
            (int) $ligne['id'],
            $ligne['reference'],
            $ligne['contribution'],
            $ligne['libelle'],
            $ligne['annee'],
            (int) $ligne['montant'],
            StatutPaiement::from($ligne['statut']),
            $ligne['parent_nom'],
            $ligne['parent_prenom'],
            $ligne['parent_telephone'],
            $ligne['parent_email'],
            $eleves->fetchAll(),
            $ligne['fedapay_transaction_id'],
            $ligne['fedapay_reference'],
            new \DateTimeImmutable($ligne['cree_le']),
            $ligne['paye_le'] ? new \DateTimeImmutable($ligne['paye_le']) : null,
            ModePaiement::tryFrom((string) ($ligne['mode_paiement'] ?? '')) ?? ModePaiement::EnLigne,
        );
    }

    /**
     * @param string $annee
     * @param list<string> $classesListe
     * @return array<string, array{classe: string, total: int, nb_payes: int, nb_attente: int, nb_non_payes: int, taux: int, eleves: list<array<string, mixed>>}>
     */
    public function recensementParClasse(string $annee, array $classesListe): array
    {
        $eleves = [];
        try {
            $stmtEleves = $this->pdo->prepare('SELECT id, nom, classe, cree_le FROM eleves WHERE annee = ? ORDER BY nom ASC');
            $stmtEleves->execute([$annee]);
            $eleves = $stmtEleves->fetchAll();
        } catch (\Throwable) {
            $eleves = [];
        }

        $stmtPaiements = $this->pdo->prepare("
            SELECT pe.nom, pe.classe, p.reference, p.montant, p.statut, p.paye_le, p.cree_le,
                   p.parent_nom, p.parent_prenom, p.parent_telephone
            FROM paiement_eleves pe
            JOIN paiements p ON pe.paiement_id = p.id
            WHERE p.annee = ?
            ORDER BY CASE p.statut WHEN 'paye' THEN 1 WHEN 'en_attente' THEN 2 ELSE 3 END ASC, p.id DESC
        ");
        $stmtPaiements->execute([$annee]);
        $paiements = $stmtPaiements->fetchAll();

        // Indexation par clé normalisée (classe + nom)
        $paiementsIndex = [];
        foreach ($paiements as $p) {
            $cle = mb_strtolower(trim((string) $p['classe']) . '|' . trim((string) $p['nom']));
            if (!isset($paiementsIndex[$cle])) {
                $paiementsIndex[$cle] = $p;
            } elseif ($paiementsIndex[$cle]['statut'] !== 'paye' && $p['statut'] === 'paye') {
                $paiementsIndex[$cle] = $p;
            }
        }

        $resultats = [];
        foreach ($classesListe as $c) {
            $resultats[$c] = [
                'classe'       => $c,
                'total'        => 0,
                'nb_payes'     => 0,
                'nb_attente'   => 0,
                'nb_non_payes' => 0,
                'taux'         => 0,
                'eleves'       => [],
            ];
        }

        foreach ($eleves as $e) {
            $classe = (string) $e['classe'];
            if (!isset($resultats[$classe])) {
                $resultats[$classe] = [
                    'classe'       => $classe,
                    'total'        => 0,
                    'nb_payes'     => 0,
                    'nb_attente'   => 0,
                    'nb_non_payes' => 0,
                    'taux'         => 0,
                    'eleves'       => [],
                ];
            }

            $cle = mb_strtolower(trim($classe) . '|' . trim((string) $e['nom']));
            $paiementInfo = $paiementsIndex[$cle] ?? null;

            $statut = 'non_paye';
            $reference = null;
            $montant = null;
            $payeLe = null;
            $parentNom = null;
            $parentTel = null;

            if ($paiementInfo !== null) {
                if ($paiementInfo['statut'] === 'paye') {
                    $statut = 'paye';
                    $reference = $paiementInfo['reference'];
                    $montant = (int) $paiementInfo['montant'];
                    $payeLe = $paiementInfo['paye_le'];
                    $parentNom = trim($paiementInfo['parent_prenom'] . ' ' . $paiementInfo['parent_nom']);
                    $parentTel = $paiementInfo['parent_telephone'];
                } elseif ($paiementInfo['statut'] === 'en_attente') {
                    $statut = 'en_attente';
                    $reference = $paiementInfo['reference'];
                    $montant = (int) $paiementInfo['montant'];
                    $parentNom = trim($paiementInfo['parent_prenom'] . ' ' . $paiementInfo['parent_nom']);
                    $parentTel = $paiementInfo['parent_telephone'];
                }
            }

            $resultats[$classe]['total']++;
            if ($statut === 'paye') {
                $resultats[$classe]['nb_payes']++;
            } elseif ($statut === 'en_attente') {
                $resultats[$classe]['nb_attente']++;
            } else {
                $resultats[$classe]['nb_non_payes']++;
            }

            $resultats[$classe]['eleves'][] = [
                'id'         => (int) $e['id'],
                'nom'        => (string) $e['nom'],
                'classe'     => $classe,
                'statut'     => $statut,
                'reference'  => $reference,
                'montant'    => $montant,
                'paye_le'    => $payeLe,
                'parent_nom' => $parentNom,
                'parent_tel' => $parentTel,
            ];
        }

        foreach ($resultats as $classe => &$donnees) {
            $donnees['taux'] = $donnees['total'] > 0
                ? (int) round(($donnees['nb_payes'] / $donnees['total']) * 100)
                : 0;
        }
        unset($donnees);

        return $resultats;
    }

    /**
     * @param string $classe
     * @param string $annee
     * @param list<string> $noms
     * @return int Nombre d'élèves insérés
     */
    public function ajouterEleves(string $classe, string $annee, array $noms): int
    {
        $insertion = $this->pdo->prepare('
            INSERT INTO eleves (nom, classe, annee)
            SELECT ?, ?, ?
            WHERE NOT EXISTS (
                SELECT 1 FROM eleves WHERE nom = ? AND classe = ? AND annee = ?
            )
        ');

        $nb = 0;
        foreach ($noms as $nom) {
            $nom = trim($nom);
            if ($nom === '') {
                continue;
            }
            $insertion->execute([$nom, $classe, $annee, $nom, $classe, $annee]);
            if ($insertion->rowCount() > 0) {
                $nb++;
            }
        }
        return $nb;
    }

    public function supprimerEleve(int $id): bool
    {
        $req = $this->pdo->prepare('DELETE FROM eleves WHERE id = ?');
        $req->execute([$id]);
        return $req->rowCount() > 0;
    }

    private function referenceLibre(): string
    {
        $verification = $this->pdo->prepare('SELECT 1 FROM paiements WHERE reference = ?');
        do {
            $reference = Paiement::nouvelleReference();
            $verification->execute([$reference]);
        } while ($verification->fetchColumn());
        return $reference;
    }
}
