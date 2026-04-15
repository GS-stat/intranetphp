-- ==========================================================
-- MIGRATION: GS Motors – kompletta kolumner
-- Kör detta skript i din databas (phpMyAdmin / SSH).
-- Alla ALTER är idempotenta (IF NOT EXISTS) – säkert att köra flera gånger.
-- ==========================================================

-- ──────────────────────────────────────────────────────────
-- 5. Artikelregister (admin-hanterade produkter/tjänster)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `stat_artiklar` (
    `id`            INT           NOT NULL AUTO_INCREMENT,
    `namn`          VARCHAR(255)  NOT NULL,
    `pris`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tillat_rabatt` TINYINT(1)    NOT NULL DEFAULT 1  COMMENT '1 = rabatt tillåten',
    `pris_disabled` TINYINT(1)    NOT NULL DEFAULT 0  COMMENT '1 = priset är låst',
    `aktiv`         TINYINT(1)    NOT NULL DEFAULT 1,
    `skapad`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lägg till artikel-referens i projekt-rader (nullable FK)
ALTER TABLE `stat_projekt_rader`
    ADD COLUMN IF NOT EXISTS `artikel_id` INT NULL AFTER `projekt_id`;

-- ──────────────────────────────────────────────────────────
-- 1. Kolumner som kan saknas från original-tabellen
-- ──────────────────────────────────────────────────────────
ALTER TABLE `stat_projekt`
    ADD COLUMN IF NOT EXISTS `flagga`            TINYINT(1)   NOT NULL DEFAULT 0        COMMENT 'Se över projektet',
    ADD COLUMN IF NOT EXISTS `ansvarig_tekniker` INT          NULL                       COMMENT 'FK → stat_anvandare.id',
    ADD COLUMN IF NOT EXISTS `avslutad`          TINYINT(1)   NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `avslutadDatum`     DATETIME     NULL,
    ADD COLUMN IF NOT EXISTS `dackforvaring`     TINYINT(1)   NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `dackforvaring_id`  VARCHAR(100) NULL;

-- ──────────────────────────────────────────────────────────
-- 2. Felsökning + åtgärd (lades till i v2-migrering)
-- ──────────────────────────────────────────────────────────
ALTER TABLE `stat_projekt`
    ADD COLUMN IF NOT EXISTS `felsokning` TEXT NULL AFTER `beskrivning`,
    ADD COLUMN IF NOT EXISTS `atgard`     TEXT NULL AFTER `felsokning`;

-- ──────────────────────────────────────────────────────────
-- 3. Publika arbetsorder-tokens + SMS-spårning
-- ──────────────────────────────────────────────────────────
ALTER TABLE `stat_projekt`
    ADD COLUMN IF NOT EXISTS `publik_token`        VARCHAR(64)  NULL AFTER `flagga`,
    ADD COLUMN IF NOT EXISTS `publik_pin_hash`     VARCHAR(255) NULL AFTER `publik_token`,
    ADD COLUMN IF NOT EXISTS `publik_utgangsdatum` DATE         NULL AFTER `publik_pin_hash`,
    ADD COLUMN IF NOT EXISTS `sms_skickat`         TINYINT(1)  NOT NULL DEFAULT 0 AFTER `publik_utgangsdatum`;

-- Unikt index på token (undviker dubbletter)
ALTER TABLE `stat_projekt`
    ADD UNIQUE INDEX IF NOT EXISTS `idx_publik_token` (`publik_token`);

-- ──────────────────────────────────────────────────────────
-- 4. Projekt-rader (materialkostnad / arbete per projekt)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `stat_projekt_rader` (
    `id`          INT           NOT NULL AUTO_INCREMENT,
    `projekt_id`  INT           NOT NULL,
    `typ`         ENUM('arbete','material') NOT NULL DEFAULT 'material',
    `beskrivning` TEXT          NOT NULL,
    `pris`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `antal`       DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    `rabatt`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (`id`),
    INDEX `idx_projekt_id` (`projekt_id`),
    CONSTRAINT `fk_rader_projekt`
        FOREIGN KEY (`projekt_id`) REFERENCES `stat_projekt` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────
-- 6. Projektkostnader (interna – syns aldrig för kund)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `stat_projekt_kostnader` (
    `id`           INT           NOT NULL AUTO_INCREMENT,
    `projekt_id`   INT           NOT NULL,
    `beskrivning`  VARCHAR(500)  NOT NULL,
    `belopp`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `moms_procent` TINYINT       NOT NULL DEFAULT 25  COMMENT '0, 12 eller 25',
    `datum`        DATE          NOT NULL,
    `skapad`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_pk_projekt` (`projekt_id`),
    CONSTRAINT `fk_pk_projekt`
        FOREIGN KEY (`projekt_id`) REFERENCES `stat_projekt` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────
-- 7. Allmänna utgifter (hyra, el, mat osv – ej projektkopplade)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `stat_utgifter` (
    `id`             INT           NOT NULL AUTO_INCREMENT,
    `kategori`       VARCHAR(100)  NOT NULL DEFAULT 'Övrigt',
    `beskrivning`    VARCHAR(500)  NOT NULL,
    `belopp`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `moms_procent`   TINYINT       NOT NULL DEFAULT 25  COMMENT '0, 12 eller 25',
    `datum`          DATE          NOT NULL,
    `aterkommande`   TINYINT(1)    NOT NULL DEFAULT 0   COMMENT '1 = räknas in varje månad',
    `aktiv`          TINYINT(1)    NOT NULL DEFAULT 1,
    `skapad`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_utgift_datum` (`datum`),
    INDEX `idx_utgift_kategori` (`kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- Verifiera efter körning:
--   SHOW COLUMNS FROM stat_projekt;
--   SHOW CREATE TABLE stat_projekt_rader;
--   SHOW CREATE TABLE stat_projekt_kostnader;
--   SHOW CREATE TABLE stat_utgifter;
-- ==========================================================
