<?php
/**
 * @var int $statut
 * @var string $message
 * @var callable $url
 */
?>
<main class="succes">
  <div class="alerte erreur" role="alert">
    <strong><?= $statut === 404 ? 'Page introuvable.' : 'Oups.' ?></strong>
    <?= e($message) ?>
  </div>
  <div class="actions">
    <a class="payer" href="<?= e($url('/')) ?>">Retour au formulaire</a>
  </div>
</main>
