/*
 * Formulaire de contribution : saisie, récapitulatif en direct, validation de confort,
 * puis envoi au serveur (POST /paiements) qui recalcule le montant, enregistre le paiement
 * et renvoie le lien de la page de paiement FedaPay.
 * Les montants affichés ici sont indicatifs : seul le serveur fait foi.
 */
(function () {
  "use strict";

  const CONFIG = JSON.parse(document.getElementById("config-ecole").textContent);
  const CSRF = document.querySelector('meta[name="csrf-token"]').content;

  const $ = (s, el = document) => el.querySelector(s);
  const fmt = n => new Intl.NumberFormat("fr-FR").format(n) + " FCFA";
  const esc = s => String(s).replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  const cadenas = '<svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true"><rect x="3" y="8" width="12" height="8.5" rx="2" fill="currentColor"/><path d="M5.8 8V5.8a3.2 3.2 0 0 1 6.4 0V8" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>';

  const etat = {
    eleves: [{ nom: "", classe: "" }],
    contribution: CONFIG.contributions.length ? CONFIG.contributions[0].id : "libre",
    occupe: false
  };

  /* ---------- Enfants ---------- */
  function rendreEleves() {
    $("#liste-eleves").innerHTML = etat.eleves.map((e, i) => `
      <div class="eleve" role="group" aria-labelledby="e-titre-${i}">
        <div class="eleve-tete">
          <span id="e-titre-${i}">Enfant ${i + 1}</span>
          ${i > 0 ? `<button type="button" class="retirer" data-retirer="${i}" aria-label="Retirer l'enfant ${i + 1}">Retirer</button>` : ""}
        </div>
        <div class="eleve-champs">
          <div class="champ">
            <label for="e-nom-${i}">Nom et prénoms</label>
            <input id="e-nom-${i}" data-champ="nom" data-i="${i}" data-cle="eleves.${i}.nom" value="${esc(e.nom)}" maxlength="120" autocomplete="off" placeholder="Ex. HOUNSOU Awa">
            <span class="erreur">Indiquez le nom de l'enfant.</span>
          </div>
          <div class="champ">
            <label for="e-classe-${i}">Classe</label>
            <select id="e-classe-${i}" data-champ="classe" data-i="${i}" data-cle="eleves.${i}.classe">
              <option value="">Choisir</option>
              ${CONFIG.classes.map(c => `<option value="${esc(c)}" ${c === e.classe ? "selected" : ""}>${esc(c)}</option>`).join("")}
            </select>
            <span class="erreur">Choisissez la classe.</span>
          </div>
        </div>
      </div>`).join("");
    $("#ajouter-eleve").hidden = etat.eleves.length >= CONFIG.maxEleves;
  }

  function majEleve(ev) {
    const el = ev.target;
    if (!el.dataset.champ) return;
    etat.eleves[+el.dataset.i][el.dataset.champ] = el.value;
    rendreRecap();
  }
  $("#liste-eleves").addEventListener("input", majEleve);
  $("#liste-eleves").addEventListener("change", majEleve);
  $("#liste-eleves").addEventListener("click", ev => {
    const b = ev.target.closest("[data-retirer]");
    if (!b) return;
    etat.eleves.splice(+b.dataset.retirer, 1);
    rendreEleves();
    rendreRecap();
    $("#ajouter-eleve").focus();
  });
  $("#ajouter-eleve").addEventListener("click", () => {
    if (etat.eleves.length >= CONFIG.maxEleves) return;
    etat.eleves.push({ nom: "", classe: "" });
    rendreEleves();
    rendreRecap();
    $("#e-nom-" + (etat.eleves.length - 1)).focus();
  });

  /* ---------- Choix de la contribution ---------- */
  function prixOption(o) {
    if (o.montant == null) return "<small>Vous choisissez</small>";
    if (typeof o.montant === "object") {
      const m = Object.values(o.montant), min = Math.min(...m), max = Math.max(...m);
      return (min === max ? fmt(min) : "De " + fmt(min) + " à " + fmt(max)) + "<small>par enfant, selon la classe</small>";
    }
    return fmt(o.montant) + (o.parEleve ? "<small>par enfant</small>" : "");
  }

  function rendreOptions() {
    const opts = CONFIG.contributions.slice();
    if (CONFIG.montantLibre.actif) opts.push({ id: "libre", libelle: CONFIG.montantLibre.libelle, detail: CONFIG.montantLibre.detail, montant: null });
    $("#options").innerHTML = opts.map(o => `
      <label class="option ${o.id === etat.contribution ? "choisie" : ""}">
        <input type="radio" name="contribution" value="${esc(o.id)}" ${o.id === etat.contribution ? "checked" : ""}>
        <span><span class="opt-titre">${esc(o.libelle)}</span><small>${esc(o.detail || "")}</small></span>
        <span class="prix">${prixOption(o)}</span>
      </label>`).join("");
    $("#champ-libre").hidden = etat.contribution !== "libre";
  }

  $("#options").addEventListener("change", ev => {
    if (ev.target.name !== "contribution") return;
    etat.contribution = ev.target.value;
    document.querySelectorAll(".option").forEach(l => l.classList.toggle("choisie", l.contains(ev.target)));
    $("#champ-libre").hidden = etat.contribution !== "libre";
    if (etat.contribution === "libre") $("#montant-libre").focus();
    else $("#champ-libre").classList.remove("invalide");
    majAlerteValidation();
    rendreRecap();
  });
  $("#montant-libre").addEventListener("input", rendreRecap);

  /* ---------- Calculs (affichage seulement) ---------- */
  const contributionChoisie = () => CONFIG.contributions.find(c => c.id === etat.contribution) || null;
  function montantLibre() {
    const v = parseInt(($("#montant-libre").value || "").replace(/\D/g, ""), 10);
    return isNaN(v) ? 0 : v;
  }
  // Montant d'un enfant : tarif de sa classe (0 tant qu'elle n'est pas choisie) ou tarif unique.
  function montantEleve(c, e) {
    return typeof c.montant === "object" ? (c.montant[e.classe] || 0) : c.montant;
  }
  const parEleve = c => c.parEleve || typeof c.montant === "object";
  function total() {
    const c = contributionChoisie();
    if (c) return parEleve(c) ? etat.eleves.reduce((s, e) => s + montantEleve(c, e), 0) : c.montant;
    return montantLibre();
  }

  /* ---------- Récapitulatif ---------- */
  function rendreRecap() {
    const c = contributionChoisie();
    const lignes = etat.eleves.map((e, i) => {
      const nom = e.nom.trim();
      return `<li>
        <span class="qui ${nom ? "" : "vide"}">${esc(nom || "Enfant " + (i + 1))}</span>
        <span class="detail">
          <em class="classe">${e.classe ? "Classe : " + esc(e.classe) : "Classe à choisir"}</em>
          <span class="combien">${c && parEleve(c) ? (montantEleve(c, e) ? esc(fmt(montantEleve(c, e))) : "…") : ""}</span>
        </span>
      </li>`;
    });
    const ligneMontant = (libelle, montant) =>
      `<li><span class="detail"><em class="classe">${esc(libelle)}</em><span class="combien">${montant}</span></span></li>`;
    if (c && !parEleve(c)) lignes.push(ligneMontant(c.libelle, esc(fmt(c.montant))));
    if (!c) lignes.push(ligneMontant(CONFIG.montantLibre.libelle, montantLibre() ? esc(fmt(montantLibre())) : "…"));
    $("#lignes").innerHTML = lignes.join("");
    $("#recap-contrib").textContent = (c ? c.libelle : CONFIG.montantLibre.libelle) + " " + CONFIG.annee;
    $("#total").textContent = fmt(total());
    majBouton();
  }

  function majBouton() {
    const b = $("#payer");
    if (etat.occupe) {
      b.disabled = true;
      b.textContent = "Redirection vers le paiement…";
      return;
    }
    b.disabled = false;
    const t = total();
    b.innerHTML = cadenas + "<span>" + (t > 0 ? "Payer " + esc(fmt(t)) : "Payer") + "</span>";
  }

  /* ---------- Alertes ---------- */
  function afficherAlerte(type, texte, source) {
    const a = $("#alerte");
    a.className = "alerte " + type;
    a.textContent = texte;
    a.dataset.source = source || "";
    a.hidden = false;
  }
  const cacherAlerte = () => { $("#alerte").hidden = true; };
  // Retire le message "champs à corriger" dès que tout est corrigé
  function majAlerteValidation() {
    if ($("#alerte").dataset.source === "validation" && !document.querySelector(".invalide")) cacherAlerte();
  }

  /* ---------- Validation (confort : le serveur revalide tout) ---------- */
  function marquer(el, ok, message) {
    const champ = el.closest(".champ");
    champ.classList.toggle("invalide", !ok);
    if (message) champ.querySelector(".erreur").textContent = message;
    if (ok) el.removeAttribute("aria-invalid"); else el.setAttribute("aria-invalid", "true");
    return ok;
  }
  function telNormalise() {
    let d = $("#p-tel").value.replace(/\D/g, "");
    if (d.length === 13 && d.startsWith("229")) d = d.slice(3);
    return d;
  }
  function signalerInvalides(invalides) {
    afficherAlerte("erreur", invalides.length === 1
      ? "Un champ est à corriger avant de payer."
      : invalides.length + " champs sont à corriger avant de payer.", "validation");
    invalides[0].focus();
  }
  function valider() {
    const invalides = [];
    etat.eleves.forEach((e, i) => {
      const n = $("#e-nom-" + i), c = $("#e-classe-" + i);
      if (!marquer(n, e.nom.trim().length >= 2)) invalides.push(n);
      if (!marquer(c, !!e.classe)) invalides.push(c);
    });
    if (!contributionChoisie()) {
      const m = $("#montant-libre"), v = montantLibre(), min = CONFIG.montantLibre.minimum;
      $("#err-libre").textContent = v ? "Le montant minimum est de " + fmt(min) + "." : "Indiquez le montant à payer.";
      if (!marquer(m, v >= min)) invalides.push(m);
    }
    const nom = $("#p-nom"), pre = $("#p-prenom"), tel = $("#p-tel"), mail = $("#p-email");
    if (!marquer(nom, nom.value.trim().length >= 2)) invalides.push(nom);
    if (!marquer(pre, pre.value.trim().length >= 2)) invalides.push(pre);
    if (!marquer(tel, /^01\d{8}$/.test(telNormalise()))) invalides.push(tel);
    const m = mail.value.trim();
    if (!marquer(mail, !m || /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(m))) invalides.push(mail);

    if (invalides.length) {
      signalerInvalides(invalides);
      return false;
    }
    cacherAlerte();
    return true;
  }

  // Affiche les erreurs renvoyées par le serveur (clés du type "parent.telephone", "eleves.0.nom")
  function afficherErreursServeur(erreurs) {
    const invalides = [];
    Object.keys(erreurs || {}).forEach(cle => {
      const el = document.querySelector(`[data-cle="${CSS.escape(cle)}"]`);
      if (el) { marquer(el, false, erreurs[cle]); invalides.push(el); }
    });
    return invalides;
  }

  $("#formulaire").addEventListener("input", ev => {
    const ch = ev.target.closest(".champ");
    if (ch) { ch.classList.remove("invalide"); ev.target.removeAttribute("aria-invalid"); }
    majAlerteValidation();
  });
  $("#formulaire").addEventListener("change", ev => {
    const ch = ev.target.closest(".champ");
    if (ch) { ch.classList.remove("invalide"); ev.target.removeAttribute("aria-invalid"); }
    majAlerteValidation();
  });
  $("#p-tel").addEventListener("blur", () => {
    const d = telNormalise();
    if (/^\d{10}$/.test(d)) $("#p-tel").value = d.replace(/(\d{2})(?=\d)/g, "$1 ");
  });

  /* ---------- Envoi au serveur ---------- */
  $("#formulaire").addEventListener("submit", ev => { ev.preventDefault(); payer(); });

  async function payer() {
    if (etat.occupe || !valider()) return;

    const donnees = {
      eleves: etat.eleves.map(e => ({ nom: e.nom.trim(), classe: e.classe })),
      contribution: etat.contribution,
      montant_libre: etat.contribution === "libre" ? montantLibre() : null,
      parent: {
        nom: $("#p-nom").value.trim(),
        prenom: $("#p-prenom").value.trim(),
        telephone: telNormalise(),
        email: $("#p-email").value.trim()
      }
    };

    etat.occupe = true; majBouton(); cacherAlerte();

    let reponse, corps;
    try {
      reponse = await fetch($("#formulaire").action, {
        method: "POST",
        headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-Token": CSRF },
        credentials: "same-origin",
        body: JSON.stringify(donnees)
      });
      corps = await reponse.json().catch(() => ({}));
    } catch (err) {
      etat.occupe = false; majBouton();
      afficherAlerte("erreur", "Connexion impossible. Vérifiez votre connexion internet, puis réessayez.");
      return;
    }

    if (reponse.ok && corps.redirection) {
      window.location.assign(corps.redirection);   // page de paiement FedaPay
      return;
    }

    etat.occupe = false; majBouton();
    if (reponse.status === 422) {
      const invalides = afficherErreursServeur(corps.erreurs);
      if (invalides.length) return signalerInvalides(invalides);
    }
    afficherAlerte("erreur", corps.message || "Le paiement n'a pas pu démarrer. Réessayez dans un instant.");
  }

  // Retour arrière depuis FedaPay : le navigateur peut restaurer la page figée en "Redirection…"
  window.addEventListener("pageshow", ev => {
    if (ev.persisted) { etat.occupe = false; majBouton(); }
  });

  /* ---------- Démarrage ---------- */
  rendreEleves();
  rendreOptions();
  rendreRecap();
})();
