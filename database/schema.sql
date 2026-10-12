-- Schéma de la base (MySQL 8 / MariaDB 10.4+).
-- Installation : mysql -u root < database/schema.sql



CREATE TABLE IF NOT EXISTS paiements (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reference               VARCHAR(20)  NOT NULL COMMENT 'Référence publique (reçu)',
    contribution            VARCHAR(40)  NOT NULL COMMENT 'id dans config/ecole.php, ou "libre"',
    libelle                 VARCHAR(120) NOT NULL,
    annee                   VARCHAR(20)  NOT NULL,
    montant                 INT UNSIGNED NOT NULL COMMENT 'FCFA, calculé par le serveur',
    statut                  ENUM('en_attente', 'paye', 'refuse', 'annule', 'rembourse') NOT NULL DEFAULT 'en_attente',
    mode_paiement           ENUM('en_ligne', 'especes', 'anterieur') NOT NULL DEFAULT 'en_ligne',
    parent_nom              VARCHAR(80)  NOT NULL,
    parent_prenom           VARCHAR(80)  NOT NULL,
    parent_telephone        CHAR(10)     NOT NULL COMMENT 'Vide si le parent paie au guichet sans téléphone',
    parent_email            VARCHAR(160) NOT NULL DEFAULT '',
    fedapay_transaction_id  VARCHAR(40)  NULL,
    fedapay_reference       VARCHAR(60)  NULL,
    cree_le                 DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    maj_le                  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    paye_le                 DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_paiements_reference (reference),
    UNIQUE KEY uq_paiements_transaction (fedapay_transaction_id),
    KEY idx_paiements_statut (statut, cree_le),
    KEY idx_paiements_telephone (parent_telephone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS paiement_eleves (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    paiement_id  INT UNSIGNED NOT NULL,
    nom          VARCHAR(120) NOT NULL,
    classe       VARCHAR(20)  NOT NULL,
    PRIMARY KEY (id),
    KEY idx_eleves_classe (classe),
    CONSTRAINT fk_eleves_paiement FOREIGN KEY (paiement_id) REFERENCES paiements (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS eleves (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom          VARCHAR(120) NOT NULL,
    classe       VARCHAR(20)  NOT NULL,
    annee        VARCHAR(20)  NOT NULL,
    cree_le      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_eleves_recensement_classe (classe),
    KEY idx_eleves_recensement_annee (annee)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

