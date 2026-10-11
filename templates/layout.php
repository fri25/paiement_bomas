<?php
/**
 * @var array<string, mixed> $ecole
 * @var callable $url
 * @var callable $asset
 * @var string $contenu
 */
$titre = $titre ?? 'Contribution scolaire';
$chapo = $chapo ?? null;
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titre) ?> – <?= e($ecole['nom']) ?></title>
<meta name="description" content="Payez en ligne la contribution scolaire de vos enfants, par Mobile Money ou carte bancaire.">
<?php if (isset($csrf)): ?>
<meta name="csrf-token" content="<?= e($csrf) ?>">
<?php endif ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:wght@400;700&family=Kalam:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e($asset('css/app.css')) ?>">
</head>
<body>

<header class="entete <?= !empty($estAdmin) ? 'entete-admin' : '' ?>">
  <div class="entete-barre">
    <a class="ecole" href="<?= e($url('/')) ?>">
      <svg width="30" height="30" viewBox="0 0 30 30" aria-hidden="true">
        <rect x="2" y="4" width="26" height="22" rx="3" fill="#0F172A"/>
        <rect x="5" y="7" width="20" height="16" rx="1.5" fill="#1E40AF"/>
        <path d="M9 18c2-4 4-5 6-2s4 1 6-3" fill="none" stroke="#FFFFFF" stroke-width="1.6" stroke-linecap="round"/>
      </svg>
      <span><?= e($ecole['nom']) ?></span>
    </a>

    <?php if (!empty($estAdmin)): ?>
    <div class="nav-admin">
      <span class="badge-direction">Espace Direction</span>
      <a href="<?= e($url('/')) ?>" class="lien-nav" target="_blank" title="Ouvrir le formulaire public dans un nouvel onglet">Formulaire public ↗</a>
      <form action="<?= e($url('/admin/deconnexion')) ?>" method="post" class="form-deconnexion">
        <?= \App\Http\Csrf::champ() ?>
        <button type="submit" class="btn-deconnexion" title="Se déconnecter du panneau">Déconnexion</button>
      </form>
    </div>
    <?php endif ?>
  </div>

  <h1><?= !empty($estAdmin) ? 'Direction · Liste des paiements' : 'Contribution scolaire ' . e($ecole['annee']) ?></h1>
  <?php if ($chapo): ?>
  <p class="chapo"><?= e($chapo) ?></p>
  <?php endif ?>
</header>

<?= $contenu ?>

<footer class="pied">
  <?php if ($ecole['telephone'] !== ''): ?>
  <span>Une question sur la contribution ? Appelez l'école au <?= e($ecole['telephone']) ?>.</span>
  <?php endif ?>
  <?php if (empty($estAdmin)): ?>
  <span class="pied-admin"> · <a href="<?= e($url('/admin')) ?>">Espace Direction</a></span>
  <?php else: ?>
  <span class="pied-admin"> · Connecté en tant que Direction</span>
  <?php endif ?>
</footer>

</body>
</html>
