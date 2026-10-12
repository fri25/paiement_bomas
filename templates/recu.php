<?php
/**
 * @var \App\Domaine\Paiement $paiement
 * @var array<string, mixed> $ecole
 * @var callable $url
 */

use App\Domaine\StatutPaiement;

$statut = $paiement->statut;
$date = ($paiement->payeLe ?? $paiement->creeLe);
$formatDate = new IntlDateFormatter('fr_FR', IntlDateFormatter::LONG, IntlDateFormatter::SHORT);
$nbEleves = count($paiement->eleves);
?>
<main class="succes" id="succes" tabindex="-1" aria-labelledby="t-succes">

<?php if ($statut === StatutPaiement::EnAttente): ?>
  <div class="alerte attente" role="status">
    <strong>Paiement en attente de confirmation.</strong>
    Validez la demande reçue sur votre téléphone. Cette page se met à jour toute seule ; ne relancez pas un nouveau paiement tout de suite.
  </div>
  <div class="actions">
    <a class="payer" href="<?= e($url('/recu/' . rawurlencode($paiement->reference))) ?>">Actualiser</a>
  </div>
  <script>setTimeout(function () { location.reload(); }, 8000);</script>

<?php elseif (in_array($statut, [StatutPaiement::Refuse, StatutPaiement::Annule], true)): ?>
  <div class="alerte erreur" role="alert">
    <strong>Le paiement n'a pas abouti<?= $statut === StatutPaiement::Annule ? ' (annulé)' : '' ?>.</strong>
    Aucun montant n'a été prélevé. Vérifiez le solde de votre compte Mobile Money, puis réessayez.
  </div>
  <div class="actions">
    <a class="payer" href="<?= e($url('/')) ?>">Réessayer le paiement</a>
  </div>

<?php else: ?>
  <p class="merci">
    <svg width="26" height="26" viewBox="0 0 26 26" aria-hidden="true"><circle cx="13" cy="13" r="12" fill="#1C7A49"/><path d="M7.5 13.5l3.6 3.6 7.4-8" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <?= $statut === StatutPaiement::Rembourse ? 'Ce paiement a été remboursé.' : 'Merci, votre paiement est confirmé.' ?>
  </p>
  <div class="feuille">
    <div class="cahier recu">
      <span class="tampon" aria-hidden="true"><?= e($statut->libelle()) ?></span>
      <h2 id="t-succes">Reçu de paiement</h2>
      <p class="sous"><?= e($ecole['nom']) ?></p>
      <dl>
        <dt>Référence</dt><dd><?= e($paiement->reference) ?></dd>
        <?php if ($paiement->referenceFedaPay): ?>
        <dt>Réf. FedaPay</dt><dd><?= e($paiement->referenceFedaPay) ?></dd>
        <?php endif ?>
        <?php if ($paiement->transactionId): ?>
        <dt>N° de transaction</dt><dd><?= e($paiement->transactionId) ?></dd>
        <?php endif ?>
        <dt>Date</dt><dd><?= e($formatDate->format($date)) ?></dd>
        <?php if ($paiement->mode->estAuGuichet()): ?>
        <dt>Mode</dt><dd><?= e($paiement->mode->libelle()) ?></dd>
        <?php endif ?>
        <dt>Payé par</dt><dd><?= e($paiement->parentNom . ' ' . $paiement->parentPrenom) ?></dd>
        <?php if ($paiement->parentTelephone !== ''): ?>
        <dt>Téléphone</dt><dd><?= e(telephone_lisible($paiement->parentTelephone)) ?></dd>
        <?php endif ?>
        <dt>Objet</dt><dd><?= e($paiement->libelle . ' ' . $paiement->annee) ?></dd>
        <?php foreach ($paiement->eleves as $i => $eleve): ?>
        <dt><?= $i === 0 ? ($nbEleves > 1 ? 'Enfants' : 'Enfant') : '' ?></dt><dd><?= e($eleve['nom'] . ' – ' . $eleve['classe']) ?></dd>
        <?php endforeach ?>
        <dt class="dt-total">Montant</dt><dd class="dd-total"><?= e(fcfa($paiement->montant)) ?></dd>
      </dl>
    </div>
  </div>
  <div class="actions">
    <button type="button" class="payer" onclick="window.print()">Imprimer le reçu</button>
    <a class="secondaire" href="<?= e($url('/')) ?>">Faire un autre paiement</a>
  </div>
  <p class="note">Conservez la référence <strong><?= e($paiement->reference) ?></strong> ou l'adresse de cette page : elles permettent à l'école de retrouver votre paiement.</p>
<?php endif ?>

</main>
