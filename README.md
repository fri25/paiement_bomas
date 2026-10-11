# Contribution scolaire — paiement en ligne

Les parents paient la contribution scolaire de leurs enfants par Mobile Money ou carte bancaire, via [FedaPay](https://docs.fedapay.com).
Chaque paiement est enregistré en base. Un reçu consultable et imprimable est délivré une fois que FedaPay a confirmé le paiement.

PHP 8.2+, MySQL/MariaDB, aucune dépendance en production.

## Démarrage (XAMPP)

```bash
cp .env.example .env                       # puis adaptez les valeurs
mysql -u root < database/schema.sql        # crée la base contribution_scolaire
composer install                           # facultatif en prod : sert aux tests
```

Ouvrez ensuite http://localhost/paiement.

`FEDAPAY_SECRET_KEY` est obligatoire : utilisez une clé `sk_sandbox_…` pour tester, `sk_live_…` en production. Sans clé, le formulaire s'affiche mais tout paiement est refusé (erreur 503, consignée dans `storage/logs/app.log`).

Pour développer sans Apache : `composer serve` (avec `APP_URL=http://localhost:8000` dans `.env`).

## Configuration

| Fichier | Contenu |
|---|---|
| `.env` | Tout ce qui dépend du serveur : URL, base de données, clés FedaPay. Ne jamais le versionner. |
| `config/ecole.php` | Le métier : nom de l'école, année, classes, montants des contributions. À mettre à jour à chaque rentrée. |

## Parcours d'un paiement

```
Navigateur                     Serveur (PHP)                         FedaPay
──────────                     ─────────────                         ───────
formulaire ── POST /paiements ─▶ valide, RECALCULE le montant,
                                 enregistre (en_attente) ────────────▶ crée la transaction
           ◀── { redirection } ── lien de paiement ◀───────────────── token + url
page FedaPay ───────────────────────────────────────────────────────▶ le parent paie
           ◀─────────────── GET /paiements/retour?id=… ◀──────────── redirection
                                 relit la transaction chez FedaPay,
                                 met à jour le statut
           ◀── 303 /recu/{ref} ─
                                 POST /webhooks/fedapay ◀──────────── notification signée
                                 (même mise à jour, même si le parent a fermé son navigateur)
```

Principes de sécurité :

- Le **montant** vient toujours de `config/ecole.php`. Tout montant envoyé par le navigateur est ignoré.
- Le **statut** est toujours relu chez FedaPay avec la clé secrète. Les paramètres d'URL (`?status=approved`) et le contenu du webhook ne servent qu'à savoir quelle transaction relire.
- Le montant de la transaction FedaPay est comparé au montant attendu avant de valider un paiement.
- Un paiement payé ne peut plus redevenir en attente ni refusé. Seul un remboursement reste possible (`StatutPaiement::peutPasserA`).
- Les requêtes sont protégées par un jeton CSRF. Le webhook est vérifié par signature HMAC avec une tolérance de 5 min.
- La référence du reçu (`CS-XXXX-XXXX-XXXX`) est aléatoire et ne se devine pas.

## Structure

```
public/                 Seul dossier exposé au web
  index.php             Point d'entrée unique
  assets/css, js        Interface (le JS ne fait que de l'affichage et de la validation de confort)
src/
  Application.php       Assemble les dépendances, déclare les routes, gère les erreurs
  Http/                 Request, Response, Router, Csrf
  Controller/           Page (formulaire), Paiement (création, retour, reçu), Webhook
  Domaine/              Règles métier pures : Tarification, ValidateurDemande, StatutPaiement, Paiement
  Service/              ServicePaiement : orchestre base de données et FedaPay
  Infrastructure/       PDO (DepotPaiements), client API FedaPay, signature webhook
  Support/              Env, Logger, Vue, helpers des gabarits
templates/              Gabarits PHP : layout, formulaire, reçu, erreur
config/                 app.php (technique, lit le .env), ecole.php (métier)
database/schema.sql     Tables paiements et paiement_eleves
storage/logs/app.log    Journal (une ligne JSON par événement)
tests/                  PHPUnit : composer test
docs/                   Maquette d'origine (fichier HTML unique), pour référence
```

## Routes

| Méthode | Chemin | Rôle |
|---|---|---|
| GET | `/` | Formulaire |
| POST | `/paiements` | Crée le paiement (JSON) et renvoie le lien FedaPay |
| GET | `/paiements/retour` | `callback_url` FedaPay |
| GET | `/recu/{reference}` | Reçu (se met à jour tant que le paiement est en attente) |
| POST | `/webhooks/fedapay` | Webhook FedaPay |
| GET | `/admin` | Tableau de bord et liste complète des paiements (Directeur) |
| GET | `/admin/connexion` | Formulaire de connexion Direction |
| POST | `/admin/connexion` | Authentification Direction |
| POST | `/admin/deconnexion` | Déconnexion Direction |
| GET | `/admin/export` | Export CSV de la liste (filtrée ou complète) pour Excel |
| POST | `/admin/paiements/{reference}/synchroniser` | Actualisation manuelle du statut FedaPay |
| POST | `/admin/eleves` | Recensement d'élèves par classe (unitaire ou liste groupée) |
| POST | `/admin/eleves/{id}/supprimer` | Retrait d'un élève du recensement |

## Mise en production

1. Pointez le `DocumentRoot` du virtual host sur `public/`. En sous-dossier, le `.htaccess` racine s'en charge, mais c'est moins strict.
2. HTTPS obligatoire. Dans `.env` : `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`.
3. Clé `sk_live_…` et `FEDAPAY_ENVIRONMENT=live`.
4. Dans le tableau de bord FedaPay, créez un webhook vers `{APP_URL}/webhooks/fedapay` et copiez son secret dans `FEDAPAY_WEBHOOK_SECRET`.
5. Créez un utilisateur MySQL dédié, limité à `SELECT, INSERT, UPDATE` sur la base, plutôt que `root`.
6. `storage/logs/` doit être accessible en écriture par le serveur web.
