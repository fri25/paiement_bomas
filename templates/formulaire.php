<?php
/**
 * @var array<string, mixed> $configFront
 * @var callable $url
 * @var callable $asset
 */
?>
<main class="page">
  <form class="carte" id="formulaire" action="<?= e($url('/paiements')) ?>" novalidate>

    <section class="section" aria-labelledby="t-eleves">
      <h2 id="t-eleves"><span class="num" aria-hidden="true">1</span>Vos enfants inscrits à l'école</h2>
      <p class="aide">Ajoutez chaque enfant pour qui vous payez.</p>
      <div id="liste-eleves"></div>
      <button type="button" class="ajouter" id="ajouter-eleve">
        <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 2v12M2 8h12" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
        Ajouter un autre enfant
      </button>
    </section>

    <section class="section" aria-labelledby="t-contrib">
      <h2 id="t-contrib"><span class="num" aria-hidden="true">2</span>Ce que vous payez</h2>
      <div class="options" id="options" role="radiogroup" aria-labelledby="t-contrib"></div>
      <div class="champ" id="champ-libre" hidden>
        <label for="montant-libre">Montant à payer (FCFA)</label>
        <input id="montant-libre" data-cle="montant_libre" inputmode="numeric" autocomplete="off" placeholder="Ex. 10 000">
        <span class="erreur" id="err-libre">Indiquez le montant à payer.</span>
      </div>
    </section>

    <section class="section" aria-labelledby="t-parent">
      <h2 id="t-parent"><span class="num" aria-hidden="true">3</span>Parent ou tuteur</h2>
      <p class="aide">Ces informations figurent sur le reçu et permettent à l'école de vous joindre.</p>
      <div class="duo">
        <div class="champ">
          <label for="p-nom">Nom</label>
          <input id="p-nom" data-cle="parent.nom" autocomplete="family-name" maxlength="80" placeholder="Ex. HOUNSOU">
          <span class="erreur">Indiquez votre nom.</span>
        </div>
        <div class="champ">
          <label for="p-prenom">Prénoms</label>
          <input id="p-prenom" data-cle="parent.prenom" autocomplete="given-name" maxlength="80" placeholder="Ex. Marcelline">
          <span class="erreur">Indiquez vos prénoms.</span>
        </div>
      </div>
      <div class="champ">
        <label for="p-tel">Téléphone</label>
        <div class="tel"><span aria-hidden="true">+229</span><input id="p-tel" data-cle="parent.telephone" type="tel" inputmode="numeric" autocomplete="tel-national" placeholder="01 97 00 00 00" aria-describedby="p-tel-indice"></div>
        <span class="indice" id="p-tel-indice">10 chiffres, commençant par 01</span>
        <span class="erreur">Entrez un numéro à 10 chiffres commençant par 01.</span>
      </div>
      <div class="champ">
        <label for="p-email">E-mail <span class="facultatif">(facultatif)</span></label>
        <input id="p-email" data-cle="parent.email" type="email" autocomplete="email" maxlength="160" placeholder="nom@exemple.com">
        <span class="erreur">Cette adresse e-mail n'est pas valide.</span>
      </div>
    </section>
  </form>

  <aside class="recap feuille" aria-labelledby="t-recap">
    <div class="cahier">
      <h2 id="t-recap">Récapitulatif</h2>
      <p class="sous" id="recap-contrib"></p>
      <ul class="lignes manuscrit" id="lignes"></ul>
      <p class="total" aria-live="polite"><span>Total</span><strong id="total">0 FCFA</strong></p>
    </div>
    <div class="recap-pied">
      <div class="alerte" id="alerte" role="alert" hidden></div>
      <button type="submit" form="formulaire" class="payer" id="payer">Payer</button>
      <p class="note">Vous êtes redirigé vers la page sécurisée FedaPay : vous y choisissez Mobile Money ou carte bancaire, puis vous validez sur votre téléphone.</p>
    </div>
  </aside>
</main>

<script type="application/json" id="config-ecole"><?= json_encode($configFront, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e($asset('js/app.js')) ?>" defer></script>
