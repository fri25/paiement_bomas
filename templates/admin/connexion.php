<?php
/**
 * @var array<string, mixed> $ecole
 * @var callable $url
 * @var callable $asset
 * @var list<array{type: string, message: string}> $flashes
 * @var string $csrf
 */
?>
<main class="page-connexion">
  <div class="carte carte-connexion">
    <div class="connexion-tete">
      <div class="icone-cadenas">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
          <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
        </svg>
      </div>
      <h2>Espace Direction</h2>
      <p class="sous-titre">Saisissez le mot de passe administrateur pour accéder à la liste des paiements de l'établissement.</p>
    </div>

    <?php foreach ($flashes as $flash): ?>
      <div class="alerte <?= e($flash['type'] === 'erreur' ? 'erreur' : ($flash['type'] === 'succes' ? 'info' : 'attente')) ?>" style="display:block;">
        <?= e($flash['message']) ?>
      </div>
    <?php endforeach ?>

    <form method="post" action="<?= e($url('/admin/connexion')) ?>" class="form-login">
      <?= \App\Http\Csrf::champ() ?>

      <div class="champ">
        <label for="mot_de_passe">Mot de passe Direction</label>
        <div class="mot-de-passe-wrapper">
          <input type="password" id="mot_de_passe" name="mot_de_passe" required autofocus placeholder="Mot de passe confidentiel" autocomplete="current-password">
          <button type="button" class="btn-toggle-mdp" aria-label="Afficher ou masquer le mot de passe" onclick="
            var input = document.getElementById('mot_de_passe');
            if (input.type === 'password') {
              input.type = 'text';
              this.textContent = 'Masquer';
            } else {
              input.type = 'password';
              this.textContent = 'Afficher';
            }
          ">Afficher</button>
        </div>
      </div>

      <button type="submit" class="payer" style="margin-top: 18px;">
        Accéder au panneau
      </button>

      <div class="connexion-pied">
        <a href="<?= e($url('/')) ?>" class="lien-retour">← Retourner à l'accueil public</a>
      </div>
    </form>
  </div>
</main>
