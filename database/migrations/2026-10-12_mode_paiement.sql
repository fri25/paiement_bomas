-- Paiements enregistrés par la Direction : en espèces (parents sans téléphone portable)
-- ou déjà réglés avant la mise en service de la plateforme.
-- À exécuter une fois sur une base existante : mysql -u root contribution_scolaire < database/migrations/2026-10-12_mode_paiement.sql
ALTER TABLE paiements
    ADD COLUMN mode_paiement ENUM('en_ligne', 'especes', 'anterieur') NOT NULL DEFAULT 'en_ligne' AFTER statut;
