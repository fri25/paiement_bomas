<?php
/**
 * @var array<string, mixed> $ecole
 * @var callable $url
 * @var callable $asset
 * @var list<\App\Domaine\Paiement> $paiements
 * @var array<string, int> $statistiques
 * @var array<string, array{classe: string, total: int, nb_payes: int, nb_attente: int, nb_non_payes: int, taux: int, eleves: list<array<string, mixed>>}> $recensementClasses
 * @var array<string, string> $filtres
 * @var array{page: int, totalPages: int, total: int, limite: int} $pagination
 * @var list<string> $classes
 * @var list<array{id: string, libelle: string}> $contributions
 * @var list<array{type: string, message: string}> $flashes
 * @var string $csrf
 */

$requeteParamsSansPage = $filtres;
$creerUrlFiltre = function (array $nouveaux = []) use ($url, $filtres): string {
    $params = array_filter(array_merge($filtres, $nouveaux), static fn ($v) => $v !== '' && $v !== null);
    $query = http_build_query($params);
    return $url('/admin' . ($query !== '' ? '?' . $query : ''));
};
$urlExportCsv = $url('/admin/export' . ($filtres !== [] ? '?' . http_build_query(array_filter($filtres)) : ''));
?>
<main class="page-admin">

  <?php foreach ($flashes as $flash): ?>
    <div class="alerte alerte-admin <?= e($flash['type'] === 'erreur' ? 'erreur' : ($flash['type'] === 'succes' ? 'info' : 'attente')) ?>" style="display:block;">
      <?= e($flash['message']) ?>
    </div>
  <?php endforeach ?>

  <!-- Indicateurs clés (KPI) -->
  <section class="grille-stats">
    <div class="carte-stat">
      <div class="stat-icone stat-ok">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
        </svg>
      </div>
      <div class="stat-corps">
        <span class="stat-titre">Total encaissé</span>
        <strong class="stat-valeur texte-ok"><?= fcfa($statistiques['total_encaisse'] ?? 0) ?></strong>
        <span class="stat-detail"><?= (int) ($statistiques['nb_paye'] ?? 0) ?> paiement(s) validé(s)</span>
      </div>
    </div>

    <div class="carte-stat">
      <div class="stat-icone stat-attente">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <polyline points="12 6 12 12 16 14"></polyline>
        </svg>
      </div>
      <div class="stat-corps">
        <span class="stat-titre">En attente</span>
        <strong class="stat-valeur texte-attente"><?= (int) ($statistiques['nb_attente'] ?? 0) ?></strong>
        <span class="stat-detail"><?= fcfa($statistiques['montant_attente'] ?? 0) ?> en cours</span>
      </div>
    </div>

    <div class="carte-stat">
      <div class="stat-icone stat-eleves">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
          <circle cx="9" cy="7" r="4"></circle>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
        </svg>
      </div>
      <div class="stat-corps">
        <span class="stat-titre">Élèves en règle</span>
        <strong class="stat-valeur"><?= (int) ($statistiques['nb_eleves_payes'] ?? 0) ?></strong>
        <span class="stat-detail">inscrits avec reçu validé</span>
      </div>
    </div>

    <div class="carte-stat">
      <div class="stat-icone stat-total">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
          <line x1="8" y1="21" x2="16" y2="21"></line>
          <line x1="12" y1="17" x2="12" y2="21"></line>
        </svg>
      </div>
      <div class="stat-corps">
        <span class="stat-titre">Total transactions</span>
        <strong class="stat-valeur"><?= (int) ($statistiques['nb_total'] ?? 0) ?></strong>
        <span class="stat-detail">Année scolaire <?= e($ecole['annee']) ?></span>
      </div>
    </div>
  </section>

  <!-- Section Recensement et Cartes par classe -->
  <section class="section-recensement" id="recensement">
    <div class="recensement-barre-tete">
      <div>
        <h2 class="recensement-titre">Recensement des élèves par classe</h2>
        <p class="recensement-sous-titre">
          Suivi nominatif des cotisations : élèves en règle (<span class="texte-ok">✔ Payé</span>), en attente (<span class="texte-attente">⏳ En attente</span>) ou non réglés (<span class="texte-erreur">✖ Non payé</span>).
        </p>
      </div>
      <button type="button" class="btn-recenser-principal" onclick="ouvrirModalRecensement()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
          <circle cx="8.5" cy="7" r="4"></circle>
          <line x1="20" y1="8" x2="20" y2="14"></line>
          <line x1="23" y1="11" x2="17" y2="11"></line>
        </svg>
        + Recenser des élèves
      </button>
    </div>

    <div class="grille-classes">
      <?php foreach ($recensementClasses as $nomClasse => $dataClasse): ?>
        <article class="carte carte-classe" id="classe-<?= e($nomClasse) ?>">
          <div class="carte-classe-tete">
            <div class="classe-titre-groupe">
              <span class="badge-classe-grand"><?= e($nomClasse) ?></span>
              <span class="classe-effectif"><?= (int) $dataClasse['total'] ?> élève<?= $dataClasse['total'] > 1 ? 's' : '' ?></span>
            </div>
            <div class="classe-pourcentage <?= $dataClasse['taux'] >= 80 ? 'taux-eleve' : ($dataClasse['taux'] >= 50 ? 'taux-moyen' : 'taux-faible') ?>">
              <?= (int) $dataClasse['taux'] ?>%
            </div>
          </div>

          <!-- Barre de progression -->
          <div class="progression-barre-fond" title="<?= (int) $dataClasse['taux'] ?>% des cotisations réglées">
            <div class="progression-barre-remplie" style="width: <?= min(100, max(0, $dataClasse['taux'])) ?>%;"></div>
          </div>

          <!-- Marques de recensement -->
          <div class="classe-marques-ligne">
            <span class="marque-stat marque-paye" title="Élèves avec cotisation payée">
              <span class="symbole-marque">✔</span>
              <strong><?= (int) $dataClasse['nb_payes'] ?></strong> payé<?= $dataClasse['nb_payes'] > 1 ? 's' : '' ?>
            </span>

            <?php if ($dataClasse['nb_attente'] > 0): ?>
            <span class="marque-stat marque-attente" title="Cotisations en cours de validation">
              <span class="symbole-marque">⏳</span>
              <strong><?= (int) $dataClasse['nb_attente'] ?></strong> en attente
            </span>
            <?php endif ?>

            <span class="marque-stat marque-non-paye" title="Élèves sans paiement validé">
              <span class="symbole-marque">✖</span>
              <strong><?= (int) $dataClasse['nb_non_payes'] ?></strong> non payé<?= $dataClasse['nb_non_payes'] > 1 ? 's' : '' ?>
            </span>
          </div>

          <!-- Actions de la carte -->
          <div class="carte-classe-actions">
            <button type="button" class="btn-toggle-classe" onclick="toggleClasse('<?= e($nomClasse) ?>')">
              <span id="txt-toggle-<?= e($nomClasse) ?>">Afficher la liste (<?= (int) $dataClasse['total'] ?>)</span>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" id="fleche-toggle-<?= e($nomClasse) ?>">
                <polyline points="6 9 12 15 18 9"></polyline>
              </svg>
            </button>
            <button type="button" class="btn-ajout-classe-mini" onclick="ouvrirModalRecensement('<?= e($nomClasse) ?>')" title="Ajouter des élèves à cette classe">
              + Ajouter
            </button>
          </div>

          <!-- Liste dépliable des élèves de la classe -->
          <div class="tiroir-eleves" id="tiroir-<?= e($nomClasse) ?>" hidden>
            <?php if ($dataClasse['eleves'] === []): ?>
              <div class="tiroir-vide">
                <p>Aucun élève recensé pour la classe de <?= e($nomClasse) ?>.</p>
                <button type="button" class="btn-action" onclick="ouvrirModalRecensement('<?= e($nomClasse) ?>')">
                  + Recenser les élèves de <?= e($nomClasse) ?>
                </button>
              </div>
            <?php else: ?>
              <div class="table-conteneur table-conteneur-mini">
                <table class="table-recensement">
                  <thead>
                    <tr>
                      <th>Élève</th>
                      <th>Marque & Statut</th>
                      <th>Parent / Contact</th>
                      <th class="col-actions">Retirer</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($dataClasse['eleves'] as $el): ?>
                      <tr>
                        <td class="col-eleve-nom">
                          <strong><?= e($el['nom']) ?></strong>
                        </td>

                        <td class="col-eleve-marque">
                          <?php if ($el['statut'] === 'paye'): ?>
                            <span class="marque-pilule marque-paye" title="Cotisation validée">
                              <span class="symbole-marque">✔</span> Payé
                            </span>
                            <?php if ($el['reference']): ?>
                              <a href="<?= e($url('/recu/' . $el['reference'])) ?>" target="_blank" class="lien-recu-mini" title="Ouvrir le reçu">
                                <code><?= e($el['reference']) ?></code> (<?= fcfa($el['montant']) ?>)
                              </a>
                            <?php endif ?>
                          <?php elseif ($el['statut'] === 'en_attente'): ?>
                            <span class="marque-pilule marque-attente" title="En cours de paiement">
                              <span class="symbole-marque">⏳</span> En attente
                            </span>
                            <?php if ($el['reference']): ?>
                              <span class="lien-recu-mini"><?= fcfa($el['montant']) ?></span>
                            <?php endif ?>
                          <?php else: ?>
                            <span class="marque-pilule marque-non-paye" title="Aucun paiement reçu pour le moment">
                              <span class="symbole-marque">✖</span> Non payé
                            </span>
                          <?php endif ?>
                        </td>

                        <td class="col-eleve-parent">
                          <?php if ($el['parent_nom']): ?>
                            <span><?= e($el['parent_nom']) ?></span>
                            <?php if ($el['parent_tel']): ?>
                              <a href="tel:+229<?= e($el['parent_tel']) ?>" class="tel-mini"><?= telephone_lisible($el['parent_tel']) ?></a>
                            <?php endif ?>
                          <?php else: ?>
                            <span class="texte-estompe">—</span>
                          <?php endif ?>
                        </td>

                        <td class="col-actions">
                          <form action="<?= e($url('/admin/eleves/' . $el['id'] . '/supprimer')) ?>" method="post" onsubmit="return confirm('Retirer définitivement cet élève du recensement ?');">
                            <?= \App\Http\Csrf::champ() ?>
                            <button type="submit" class="btn-supprimer-eleve" title="Retirer du recensement">
                              ✕
                            </button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach ?>
                  </tbody>
                </table>
              </div>
            <?php endif ?>
          </div>
        </article>
      <?php endforeach ?>
    </div>
  </section>

  <!-- Filtres et Recherche -->
  <section class="carte carte-filtres">
    <form method="get" action="<?= e($url('/admin')) ?>" class="form-filtres">
      <div class="filtres-ligne filtres-recherche">
        <div class="champ champ-recherche">
          <label for="q">Rechercher</label>
          <div class="champ-avec-icone">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icone-recherche">
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" id="q" name="q" value="<?= e($filtres['recherche']) ?>" placeholder="Référence, élève, parent, téléphone...">
          </div>
        </div>
      </div>

      <div class="filtres-ligne filtres-selects">
        <div class="champ">
          <label for="statut">Statut</label>
          <select id="statut" name="statut">
            <option value="">Tous les statuts</option>
            <option value="paye" <?= $filtres['statut'] === 'paye' ? 'selected' : '' ?>>Payé</option>
            <option value="en_attente" <?= $filtres['statut'] === 'en_attente' ? 'selected' : '' ?>>En attente</option>
            <option value="annule" <?= $filtres['statut'] === 'annule' ? 'selected' : '' ?>>Annulé</option>
            <option value="refuse" <?= $filtres['statut'] === 'refuse' ? 'selected' : '' ?>>Refusé</option>
            <option value="rembourse" <?= $filtres['statut'] === 'rembourse' ? 'selected' : '' ?>>Remboursé</option>
          </select>
        </div>

        <div class="champ">
          <label for="classe">Classe</label>
          <select id="classe" name="classe">
            <option value="">Toutes les classes</option>
            <?php foreach ($classes as $classe): ?>
              <option value="<?= e($classe) ?>" <?= $filtres['classe'] === $classe ? 'selected' : '' ?>><?= e($classe) ?></option>
            <?php endforeach ?>
          </select>
        </div>

        <div class="champ">
          <label for="contribution">Contribution</label>
          <select id="contribution" name="contribution">
            <option value="">Tous types</option>
            <?php foreach ($contributions as $c): ?>
              <option value="<?= e($c['id']) ?>" <?= $filtres['contribution'] === $c['id'] ? 'selected' : '' ?>><?= e($c['libelle']) ?></option>
            <?php endforeach ?>
            <option value="libre" <?= $filtres['contribution'] === 'libre' ? 'selected' : '' ?>>Montant libre</option>
          </select>
        </div>

        <div class="champ">
          <label for="date_debut">Du</label>
          <input type="date" id="date_debut" name="date_debut" value="<?= e($filtres['date_debut']) ?>">
        </div>

        <div class="champ">
          <label for="date_fin">Au</label>
          <input type="date" id="date_fin" name="date_fin" value="<?= e($filtres['date_fin']) ?>">
        </div>
      </div>

      <div class="filtres-actions">
        <div class="actions-gauche">
          <button type="submit" class="btn-filtrer">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
            </svg>
            Appliquer les filtres
          </button>
          <?php if (array_filter($filtres)): ?>
            <a href="<?= e($url('/admin')) ?>" class="btn-reinitialiser">Réinitialiser</a>
          <?php endif ?>
        </div>

        <div class="actions-droite">
          <a href="<?= e($urlExportCsv) ?>" class="btn-export" title="Exporter cette liste au format CSV pour Excel">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
              <polyline points="7 10 12 15 17 10"></polyline>
              <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            Exporter en CSV
          </a>
          <button type="button" class="btn-imprimer" onclick="window.print()" title="Imprimer le tableau des paiements">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="6 9 6 2 18 2 18 9"></polyline>
              <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
              <rect x="6" y="14" width="12" height="8"></rect>
            </svg>
            Imprimer
          </button>
        </div>
      </div>
    </form>
  </section>

  <!-- Tableau des paiements -->
  <section class="carte carte-tableau">
    <div class="tableau-entete">
      <h2>Liste des paiements (<?= (int) $pagination['total'] ?>)</h2>
      <span class="pagination-info">
        Page <?= (int) $pagination['page'] ?> sur <?= (int) $pagination['totalPages'] ?>
      </span>
    </div>

    <?php if ($paiements === []): ?>
      <div class="tableau-vide">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#9096AC" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="8" x2="12" y2="12"></line>
          <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <p>Aucun paiement ne correspond à ces critères de recherche.</p>
        <?php if (array_filter($filtres)): ?>
          <a href="<?= e($url('/admin')) ?>" class="btn-reinitialiser" style="display:inline-block; margin-top:8px;">Effacer les filtres</a>
        <?php endif ?>
      </div>
    <?php else: ?>
      <div class="table-conteneur">
        <table class="table-paiements">
          <thead>
            <tr>
              <th>Date & Heure</th>
              <th>Référence</th>
              <th>Élève(s)</th>
              <th>Parent / Contact</th>
              <th>Contribution</th>
              <th class="col-droite">Montant</th>
              <th class="col-centre">Statut</th>
              <th class="col-actions">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($paiements as $p): ?>
              <tr>
                <td class="col-date">
                  <span class="date-principale"><?= $p->creeLe->format('d/m/Y') ?></span>
                  <span class="date-heure"><?= $p->creeLe->format('H:i') ?></span>
                </td>

                <td class="col-ref">
                  <a href="<?= e($url('/recu/' . $p->reference)) ?>" target="_blank" class="lien-ref" title="Ouvrir le reçu officiel">
                    <code><?= e($p->reference) ?></code>
                  </a>
                </td>

                <td class="col-eleves">
                  <ul class="liste-eleves-td">
                    <?php foreach ($p->eleves as $eleve): ?>
                      <li>
                        <span class="badge-classe"><?= e($eleve['classe']) ?></span>
                        <strong class="nom-eleve"><?= e($eleve['nom']) ?></strong>
                      </li>
                    <?php endforeach ?>
                  </ul>
                </td>

                <td class="col-parent">
                  <span class="nom-parent"><?= e($p->parentPrenom . ' ' . $p->parentNom) ?></span>
                  <a href="tel:+229<?= e($p->parentTelephone) ?>" class="tel-parent" title="Appeler le parent">
                    <?= telephone_lisible($p->parentTelephone) ?>
                  </a>
                  <?php if ($p->parentEmail !== ''): ?>
                    <span class="email-parent"><?= e($p->parentEmail) ?></span>
                  <?php endif ?>
                </td>

                <td class="col-contribution">
                  <span class="libelle-contribution"><?= e($p->libelle) ?></span>
                  <span class="annee-contribution"><?= e($p->annee) ?></span>
                </td>

                <td class="col-montant col-droite">
                  <strong><?= fcfa($p->montant) ?></strong>
                </td>

                <td class="col-statut col-centre">
                  <?php
                  $classeBadge = match ($p->statut->value) {
                      'paye'       => 'badge-paye',
                      'en_attente' => 'badge-attente',
                      'refuse'     => 'badge-refuse',
                      'annule'     => 'badge-annule',
                      'rembourse'  => 'badge-rembourse',
                      default      => 'badge-inconnu',
                  };
                  ?>
                  <span class="badge-statut <?= $classeBadge ?>">
                    <span class="puce-statut"></span>
                    <?= e($p->statut->libelle()) ?>
                  </span>
                </td>

                <td class="col-actions">
                  <div class="groupe-actions">
                    <a href="<?= e($url('/recu/' . $p->reference)) ?>" target="_blank" class="btn-action btn-recu" title="Consulter et imprimer le reçu">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                      </svg>
                      Reçu
                    </a>

                    <?php if ($p->statut->value === 'en_attente'): ?>
                      <form action="<?= e($url('/admin/paiements/' . $p->reference . '/synchroniser?retour=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/admin'))) ?>" method="post" style="display:inline;">
                        <?= \App\Http\Csrf::champ() ?>
                        <button type="submit" class="btn-action btn-sync" title="Interroger FedaPay pour vérifier si ce paiement a été payé">
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="23 4 23 10 17 10"></polyline>
                            <polyline points="1 20 1 14 7 14"></polyline>
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                          </svg>
                          Actualiser
                        </button>
                      </form>
                    <?php endif ?>

                    <button type="button" class="btn-action btn-detail" onclick="ouvrirModalDetail(<?= e(json_encode([
                        'reference'      => $p->reference,
                        'statut'         => $p->statut->libelle(),
                        'statutCode'     => $p->statut->value,
                        'montant'        => fcfa($p->montant),
                        'libelle'        => $p->libelle,
                        'annee'          => $p->annee,
                        'parentNom'      => $p->parentPrenom . ' ' . $p->parentNom,
                        'parentTel'      => telephone_lisible($p->parentTelephone),
                        'parentEmail'    => $p->parentEmail ?: 'Non renseigné',
                        'eleves'         => $p->eleves,
                        'transactionId'  => $p->transactionId ?: 'Aucune',
                        'referenceFeda'  => $p->referenceFedaPay ?: 'Aucune',
                        'creeLe'         => $p->creeLe->format('d/m/Y à H:i:s'),
                        'payeLe'         => $p->payeLe ? $p->payeLe->format('d/m/Y à H:i:s') : 'Non finalisé',
                        'lienRecu'       => $url('/recu/' . $p->reference),
                    ])) ?>)" title="Voir toutes les informations techniques">
                      Infos
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($pagination['totalPages'] > 1): ?>
        <nav class="pagination-barre" aria-label="Pagination des paiements">
          <?php if ($pagination['page'] > 1): ?>
            <a href="<?= e($creerUrlFiltre(['page' => $pagination['page'] - 1])) ?>" class="page-lien">← Précédent</a>
          <?php endif ?>

          <div class="page-numeros">
            <?php for ($num = 1; $num <= $pagination['totalPages']; $num++): ?>
              <?php if ($num === 1 || $num === $pagination['totalPages'] || abs($num - $pagination['page']) <= 2): ?>
                <a href="<?= e($creerUrlFiltre(['page' => $num])) ?>" class="page-lien <?= $num === $pagination['page'] ? 'page-actif' : '' ?>">
                  <?= $num ?>
                </a>
              <?php elseif ($num === 2 && $pagination['page'] > 4): ?>
                <span class="page-separateur">…</span>
              <?php elseif ($num === $pagination['totalPages'] - 1 && $pagination['page'] < $pagination['totalPages'] - 3): ?>
                <span class="page-separateur">…</span>
              <?php endif ?>
            <?php endfor ?>
          </div>

          <?php if ($pagination['page'] < $pagination['totalPages']): ?>
            <a href="<?= e($creerUrlFiltre(['page' => $pagination['page'] + 1])) ?>" class="page-lien">Suivant →</a>
          <?php endif ?>
        </nav>
      <?php endif ?>

    <?php endif ?>
  </section>

</main>

<!-- Modale de détail du paiement -->
<dialog id="modal-detail-paiement" class="modal-dialog">
  <div class="modal-contenu">
    <div class="modal-entete">
      <div>
        <h3 id="modal-ref" class="modal-titre">Détails du paiement</h3>
        <span id="modal-statut" class="badge-statut"></span>
      </div>
      <button type="button" class="btn-fermer-modal" onclick="document.getElementById('modal-detail-paiement').close()" aria-label="Fermer la boîte de dialogue">✕</button>
    </div>

    <div class="modal-corps">
      <div class="detail-section">
        <h4>Cotisation</h4>
        <dl class="grille-detail">
          <dt>Montant :</dt><dd id="modal-montant" class="texte-gras"></dd>
          <dt>Type :</dt><dd id="modal-libelle"></dd>
          <dt>Année :</dt><dd id="modal-annee"></dd>
          <dt>Créé le :</dt><dd id="modal-cree-le"></dd>
          <dt>Payé le :</dt><dd id="modal-paye-le"></dd>
        </dl>
      </div>

      <div class="detail-section">
        <h4>Élève(s) concerné(s)</h4>
        <ul id="modal-liste-eleves" class="modal-liste-eleves"></ul>
      </div>

      <div class="detail-section">
        <h4>Parent / Responsable</h4>
        <dl class="grille-detail">
          <dt>Nom complet :</dt><dd id="modal-parent-nom"></dd>
          <dt>Téléphone :</dt><dd id="modal-parent-tel"></dd>
          <dt>Email :</dt><dd id="modal-parent-email"></dd>
        </dl>
      </div>

      <div class="detail-section">
        <h4>FedaPay & Technique</h4>
        <dl class="grille-detail">
          <dt>ID Transaction :</dt><dd><code id="modal-trans-id"></code></dd>
          <dt>Réf. FedaPay :</dt><dd><code id="modal-feda-ref"></code></dd>
        </dl>
      </div>
    </div>

    <div class="modal-pied">
      <a id="modal-lien-recu" href="#" target="_blank" class="payer" style="min-height: 44px; font-size: 15px; text-decoration: none;">
        Ouvrir le reçu imprimable
      </a>
      <button type="button" class="secondaire" style="min-height: 44px;" onclick="document.getElementById('modal-detail-paiement').close()">
        Fermer
      </button>
    </div>
  </div>
</dialog>

<!-- Modale de recensement des élèves -->
<dialog id="modal-recensement" class="modal-dialog">
  <div class="modal-contenu">
    <div class="modal-entete">
      <div>
        <h3 class="modal-titre">Recenser des élèves</h3>
        <p class="sous-titre" style="margin:0; font-size:14px; color:var(--gris);">Ajoutez des élèves au registre de l'école pour cette année scolaire.</p>
      </div>
      <button type="button" class="btn-fermer-modal" onclick="document.getElementById('modal-recensement').close()" aria-label="Fermer la boîte de dialogue">✕</button>
    </div>

    <form method="post" action="<?= e($url('/admin/eleves')) ?>">
      <?= \App\Http\Csrf::champ() ?>

      <div class="modal-corps">
        <div class="champ">
          <label for="select-classe-recensement">Classe</label>
          <select id="select-classe-recensement" name="classe" required>
            <?php foreach ($classes as $classe): ?>
              <option value="<?= e($classe) ?>">Classe de <?= e($classe) ?></option>
            <?php endforeach ?>
          </select>
        </div>

        <div class="champ">
          <label for="texte-noms-eleves">Nom(s) et prénom(s) des élèves</label>
          <textarea id="texte-noms-eleves" name="noms" rows="6" required placeholder="Exemple :&#10;KOUAME Junior&#10;MENSAH Afi&#10;TOSSOU Paul" style="font-family:inherit; font-size:15px; padding:10px 12px; border:1.5px solid var(--bordure); border-radius:10px; width:100%;"></textarea>
          <small class="indice" style="display:block; margin-top:4px;">
            Saisissez un nom par ligne. Vous pouvez copier-coller directement votre liste depuis Word, Excel ou un message.
          </small>
        </div>
      </div>

      <div class="modal-pied">
        <button type="submit" class="payer" style="min-height: 44px; font-size: 15px;">
          Enregistrer dans la classe
        </button>
        <button type="button" class="secondaire" style="min-height: 44px;" onclick="document.getElementById('modal-recensement').close()">
          Annuler
        </button>
      </div>
    </form>
  </div>
</dialog>

<script>
function toggleClasse(classe) {
  var tiroir = document.getElementById('tiroir-' + classe);
  var txt = document.getElementById('txt-toggle-' + classe);
  var fleche = document.getElementById('fleche-toggle-' + classe);
  if (!tiroir) return;

  var estMasque = tiroir.hasAttribute('hidden');
  if (estMasque) {
    tiroir.removeAttribute('hidden');
    if (fleche) fleche.style.transform = 'rotate(180deg)';
    if (txt) txt.textContent = txt.textContent.replace('Afficher', 'Masquer');
  } else {
    tiroir.setAttribute('hidden', 'true');
    if (fleche) fleche.style.transform = 'none';
    if (txt) txt.textContent = txt.textContent.replace('Masquer', 'Afficher');
  }
}

function ouvrirModalRecensement(classeParDefaut) {
  var dialog = document.getElementById('modal-recensement');
  if (!dialog) return;

  if (classeParDefaut) {
    var select = document.getElementById('select-classe-recensement');
    if (select) select.value = classeParDefaut;
  }
  document.getElementById('texte-noms-eleves').value = '';

  if (typeof dialog.showModal === 'function') {
    dialog.showModal();
  } else {
    dialog.setAttribute('open', 'true');
  }
}

function ouvrirModalDetail(p) {
  var dialog = document.getElementById('modal-detail-paiement');
  if (!dialog) return;

  document.getElementById('modal-ref').textContent = 'Paiement ' + p.reference;

  var badge = document.getElementById('modal-statut');
  badge.textContent = p.statut;
  badge.className = 'badge-statut badge-' + (p.statutCode === 'paye' ? 'paye' : (p.statutCode === 'en_attente' ? 'attente' : (p.statutCode === 'refuse' ? 'refuse' : 'annule')));

  document.getElementById('modal-montant').textContent = p.montant;
  document.getElementById('modal-libelle').textContent = p.libelle;
  document.getElementById('modal-annee').textContent = p.annee;
  document.getElementById('modal-cree-le').textContent = p.creeLe;
  document.getElementById('modal-paye-le').textContent = p.payeLe;

  document.getElementById('modal-parent-nom').textContent = p.parentNom;
  document.getElementById('modal-parent-tel').textContent = p.parentTel;
  document.getElementById('modal-parent-email').textContent = p.parentEmail;

  document.getElementById('modal-trans-id').textContent = p.transactionId;
  document.getElementById('modal-feda-ref').textContent = p.referenceFeda;

  var liste = document.getElementById('modal-liste-eleves');
  liste.innerHTML = '';
  p.eleves.forEach(function(e) {
    var li = document.createElement('li');
    li.innerHTML = '<span class="badge-classe">' + e.classe + '</span> <strong>' + e.nom + '</strong>';
    liste.appendChild(li);
  });

  document.getElementById('modal-lien-recu').href = p.lienRecu;

  if (typeof dialog.showModal === 'function') {
    dialog.showModal();
  } else {
    dialog.setAttribute('open', 'true');
  }
}
</script>
