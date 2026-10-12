<?php

declare(strict_types=1);

namespace App\Controller;

use Closure;
use App\Domaine\ErreurValidation;
use App\Domaine\ModePaiement;
use App\Domaine\StatutPaiement;
use App\Domaine\Tarification;
use App\Domaine\ValidateurDemande;
use App\Http\Csrf;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Infrastructure\DepotPaiements;
use App\Service\ServicePaiement;
use App\Support\AdminAuth;
use App\Support\Vue;

final class AdminController
{
    /**
     * @param array<string, mixed> $ecole
     * @param array<string, mixed> $configAdmin
     */
    public function __construct(
        private readonly Vue $vue,
        private readonly DepotPaiements|Closure $depot,
        private readonly ServicePaiement|Closure|null $service,
        private readonly ValidateurDemande $validateur,
        private readonly Tarification $tarification,
        private readonly array $ecole,
        private readonly array $configAdmin,
        private readonly string $urlApplication,
    ) {
    }

    /** GET /admin : Tableau de bord et liste complète des paiements. */
    public function index(Request $requete): Response
    {
        if (!AdminAuth::estConnecte()) {
            return Response::redirection($this->url('/admin/connexion'));
        }

        $filtres = [
            'recherche'    => trim((string) ($requete->query['q'] ?? $requete->query['recherche'] ?? '')),
            'statut'       => trim((string) ($requete->query['statut'] ?? '')),
            'classe'       => trim((string) ($requete->query['classe'] ?? '')),
            'contribution' => trim((string) ($requete->query['contribution'] ?? '')),
            'date_debut'   => trim((string) ($requete->query['date_debut'] ?? '')),
            'date_fin'     => trim((string) ($requete->query['date_fin'] ?? '')),
        ];

        $page = max(1, (int) ($requete->query['page'] ?? 1));
        $limite = 20;

        $total = $this->depot()->compter($filtres);
        $totalPages = max(1, (int) ceil($total / $limite));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $decalage = ($page - 1) * $limite;

        $paiements = $this->depot()->tous($filtres, $limite, $decalage);
        $statistiques = $this->depot()->statistiques($this->ecole['annee']);
        $recensementClasses = $this->depot()->recensementParClasse($this->ecole['annee'], $this->ecole['classes'] ?? []);
        $flashes = AdminAuth::consommerFlashes();

        return Response::html($this->vue->rendre('admin/index', [
            'titre'              => 'Panneau d’administration des paiements',
            'paiements'          => $paiements,
            'statistiques'       => $statistiques,
            'recensementClasses' => $recensementClasses,
            'filtres'            => $filtres,
            'pagination'         => [
                'page'       => $page,
                'totalPages' => $totalPages,
                'total'      => $total,
                'limite'     => $limite,
            ],
            'classes'       => $this->ecole['classes'] ?? [],
            'contributions' => $this->ecole['contributions'] ?? [],
            'flashes'       => $flashes,
            'csrf'          => Csrf::jeton(),
            'estAdmin'      => true,
        ]));
    }

    /** GET /admin/connexion : Formulaire d'authentification pour le directeur. */
    public function pageConnexion(Request $requete): Response
    {
        if (AdminAuth::estConnecte()) {
            return Response::redirection($this->url('/admin'));
        }

        $flashes = AdminAuth::consommerFlashes();

        return Response::html($this->vue->rendre('admin/connexion', [
            'titre'    => 'Connexion Direction',
            'flashes'  => $flashes,
            'csrf'     => Csrf::jeton(),
            'estAdmin' => false,
        ]));
    }

    /** POST /admin/connexion : Traitement de la connexion. */
    public function traiterConnexion(Request $requete): Response
    {
        Csrf::verifier($requete);

        $motDePasseFourni = (string) ($requete->post('mot_de_passe') ?? '');
        $motDePasseAttendu = (string) ($this->configAdmin['password'] ?? '');

        if ($motDePasseAttendu === '' || !AdminAuth::verifierMotDePasse($motDePasseFourni, $motDePasseAttendu)) {
            AdminAuth::ajouterFlash('erreur', 'Mot de passe incorrect. Veuillez réessayer.');
            return Response::redirection($this->url('/admin/connexion'));
        }

        AdminAuth::connecter();
        AdminAuth::ajouterFlash('succes', 'Connexion réussie. Bienvenue dans le panneau de direction !');
        return Response::redirection($this->url('/admin'));
    }

    /** POST /admin/deconnexion : Déconnexion de la session. */
    public function deconnexion(Request $requete): Response
    {
        Csrf::verifier($requete);
        AdminAuth::deconnecter();
        AdminAuth::ajouterFlash('info', 'Vous avez été déconnecté avec succès.');
        return Response::redirection($this->url('/admin/connexion'));
    }

    /**
     * POST /admin/paiements/guichet : paiement enregistré par la Direction, sans FedaPay.
     *  - espèces : le parent paie maintenant au bureau (pas de portable, pas de Mobile Money) ;
     *  - antérieur : le parent avait déjà payé avant la mise en service de la plateforme.
     * Le paiement est enregistré directement comme payé, puis le reçu s'ouvre pour impression.
     */
    public function encaisserAuGuichet(Request $requete): Response
    {
        if (!AdminAuth::estConnecte()) {
            return Response::redirection($this->url('/admin/connexion'));
        }

        Csrf::verifier($requete);

        $donnees = $requete->post;
        // Le formulaire propose plusieurs lignes d'enfants : on ignore celles laissées vides.
        $donnees['eleves'] = array_values(array_filter(
            is_array($donnees['eleves'] ?? null) ? $donnees['eleves'] : [],
            static fn (mixed $e): bool => is_array($e) && trim((string) ($e['nom'] ?? '')) . trim((string) ($e['classe'] ?? '')) !== '',
        ));

        $mode = ModePaiement::tryFrom((string) ($donnees['mode'] ?? ''));
        $erreurs = [];
        if ($mode === null || !$mode->estAuGuichet()) {
            $erreurs[] = 'Choisissez le mode de paiement.';
        }

        // Date du paiement antérieur (facultative) : jamais dans le futur.
        $payeLe = null;
        $date = trim((string) ($donnees['date_paiement'] ?? ''));
        if ($mode === ModePaiement::Anterieur && $date !== '') {
            $payeLe = \DateTimeImmutable::createFromFormat('!Y-m-d', $date) ?: null;
            if ($payeLe === null || $payeLe > new \DateTimeImmutable('today')) {
                $erreurs[] = 'La date du paiement est invalide.';
            }
        }

        try {
            $demande = $this->validateur->valider($donnees, telephoneObligatoire: false);
        } catch (ErreurValidation $e) {
            $erreurs = array_merge($erreurs, array_values($e->erreurs));
        }

        if ($erreurs !== []) {
            AdminAuth::ajouterFlash('erreur', 'Paiement non enregistré : ' . implode(' ', array_unique($erreurs)));
            return Response::redirection($this->url('/admin'));
        }

        $paiement = $this->depot()->creer(
            $demande,
            $this->tarification->libelle($demande->contribution),
            $this->ecole['annee'],
            $this->tarification->montant($demande),
            $mode,
            $payeLe,
        );

        AdminAuth::ajouterFlash('succes', sprintf(
            'Paiement de %s enregistré : %s (référence %s).',
            fcfa($paiement->montant),
            mb_strtolower($mode->libelle()),
            $paiement->reference,
        ));
        return Response::redirection($this->url('/recu/' . rawurlencode($paiement->reference)));
    }

    /** POST /admin/paiements/{reference}/synchroniser : Actualise le statut d'un paiement chez FedaPay. */
    public function synchroniser(Request $requete, array $parametres): Response
    {
        if (!AdminAuth::estConnecte()) {
            return Response::redirection($this->url('/admin/connexion'));
        }

        Csrf::verifier($requete);

        $reference = (string) ($parametres['reference'] ?? '');
        $paiement = $this->depot()->parReference($reference);
        if ($paiement === null) {
            AdminAuth::ajouterFlash('erreur', "Paiement $reference introuvable.");
            return Response::redirection($this->url('/admin'));
        }

        $service = $this->service();
        if ($service === null) {
            AdminAuth::ajouterFlash('erreur', "Synchronisation impossible : les clés FedaPay ne sont pas configurées.");
            return Response::redirection($this->url('/admin'));
        }

        $ancienStatut = $paiement->statut->libelle();
        $actualise = $service->actualiser($paiement);
        $nouveauStatut = $actualise->statut->libelle();

        if ($ancienStatut !== $nouveauStatut) {
            AdminAuth::ajouterFlash('succes', "Statut du paiement $reference mis à jour : $nouveauStatut (auparavant $ancienStatut).");
        } else {
            AdminAuth::ajouterFlash('info', "Vérification FedaPay effectuée pour $reference : statut inchangé ($nouveauStatut).");
        }

        $retour = (string) ($requete->query['retour'] ?? '/admin');
        if (!str_starts_with($retour, '/admin')) {
            $retour = '/admin';
        }
        return Response::redirection($this->url($retour));
    }

    /** GET /admin/export : Exporte la liste complète ou filtrée au format CSV (compatible Excel). */
    public function exporterCsv(Request $requete): Response
    {
        if (!AdminAuth::estConnecte()) {
            return Response::redirection($this->url('/admin/connexion'));
        }

        $filtres = [
            'recherche'    => trim((string) ($requete->query['q'] ?? $requete->query['recherche'] ?? '')),
            'statut'       => trim((string) ($requete->query['statut'] ?? '')),
            'classe'       => trim((string) ($requete->query['classe'] ?? '')),
            'contribution' => trim((string) ($requete->query['contribution'] ?? '')),
            'date_debut'   => trim((string) ($requete->query['date_debut'] ?? '')),
            'date_fin'     => trim((string) ($requete->query['date_fin'] ?? '')),
        ];

        $paiements = $this->depot()->tous($filtres, 5000, 0);

        $fichier = fopen('php://temp', 'r+');
        if ($fichier === false) {
            throw new HttpException(500, "Impossible de générer l'export CSV.");
        }

        // BOM UTF-8 pour ouverture correcte des accents dans Microsoft Excel
        fputs($fichier, "\xEF\xBB\xBF");

        // En-têtes CSV (délimiteur point-virgule adapté à Excel francophone)
        fputcsv($fichier, [
            'Date création',
            'Date paiement',
            'Référence',
            'Statut',
            'Montant (FCFA)',
            'Type contribution',
            'Année scolaire',
            'Nom parent',
            'Prénom parent',
            'Téléphone parent',
            'Email parent',
            'Enfants inscrits',
            'Classes',
            'ID Transaction FedaPay',
            'Réf. FedaPay',
        ], ';');

        foreach ($paiements as $p) {
            $nomsEleves = implode(' ; ', array_map(static fn (array $e): string => $e['nom'], $p->eleves));
            $classes = implode(' ; ', array_map(static fn (array $e): string => $e['classe'], $p->eleves));

            fputcsv($fichier, [
                $p->creeLe->format('Y-m-d H:i:s'),
                $p->payeLe ? $p->payeLe->format('Y-m-d H:i:s') : '',
                $p->reference,
                $p->statut->libelle(),
                (string) $p->montant,
                $p->libelle,
                $p->annee,
                $p->parentNom,
                $p->parentPrenom,
                '+229 ' . $p->parentTelephone,
                $p->parentEmail,
                $nomsEleves,
                $classes,
                $p->transactionId ?? '',
                $p->referenceFedaPay ?? '',
            ], ';');
        }

        rewind($fichier);
        $csv = stream_get_contents($fichier);
        fclose($fichier);

        $nomFichier = 'paiements_' . date('Ymd_His') . '.csv';

        return new Response((string) $csv, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $nomFichier . '"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /** POST /admin/eleves : Ajout d'un ou plusieurs élèves au recensement d'une classe. */
    public function ajouterEleves(Request $requete): Response
    {
        if (!AdminAuth::estConnecte()) {
            return Response::redirection($this->url('/admin/connexion'));
        }

        Csrf::verifier($requete);

        $classe = trim((string) ($requete->post('classe') ?? ''));
        $texteNoms = (string) ($requete->post('noms') ?? $requete->post('nom') ?? '');

        if ($classe === '' || !in_array($classe, $this->ecole['classes'] ?? [], true)) {
            AdminAuth::ajouterFlash('erreur', 'Veuillez sélectionner une classe valide.');
            return Response::redirection($this->url('/admin#recensement'));
        }

        $lignes = preg_split('/[\r\n,;]+/', $texteNoms) ?: [];
        $noms = array_values(array_filter(array_map('trim', $lignes), static fn (string $n): bool => $n !== ''));

        if ($noms === []) {
            AdminAuth::ajouterFlash('erreur', 'Veuillez renseigner au moins un nom d’élève.');
            return Response::redirection($this->url('/admin#classe-' . $classe));
        }

        $nbAjoutes = $this->depot()->ajouterEleves($classe, $this->ecole['annee'], $noms);

        if ($nbAjoutes > 0) {
            AdminAuth::ajouterFlash('succes', sprintf('%d élève(s) ajouté(s) avec succès au recensement de la classe %s.', $nbAjoutes, $classe));
        } else {
            AdminAuth::ajouterFlash('info', 'Les élèves indiqués sont déjà recensés dans cette classe.');
        }

        return Response::redirection($this->url('/admin#classe-' . $classe));
    }

    /** POST /admin/eleves/{id}/supprimer : Retrait d'un élève du recensement. */
    public function supprimerEleve(Request $requete, array $parametres): Response
    {
        if (!AdminAuth::estConnecte()) {
            return Response::redirection($this->url('/admin/connexion'));
        }

        Csrf::verifier($requete);

        $id = (int) ($parametres['id'] ?? 0);
        if ($id > 0 && $this->depot()->supprimerEleve($id)) {
            AdminAuth::ajouterFlash('info', 'Élève retiré du recensement.');
        } else {
            AdminAuth::ajouterFlash('erreur', 'Impossible de retirer cet élève (introuvable).');
        }

        return Response::redirection($this->url('/admin#recensement'));
    }

    private function url(string $chemin = ''): string
    {
        return $this->urlApplication . $chemin;
    }

    private function depot(): DepotPaiements
    {
        return $this->depot instanceof Closure ? ($this->depot)() : $this->depot;
    }

    private function service(): ?ServicePaiement
    {
        return $this->service instanceof Closure ? ($this->service)() : $this->service;
    }
}
