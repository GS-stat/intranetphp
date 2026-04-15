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
-- 5b. Spårning av boknings-SMS (vilket datum vi senast skickade)
-- ──────────────────────────────────────────────────────────
ALTER TABLE `stat_projekt`
    ADD COLUMN IF NOT EXISTS `sms_bokning_datum` DATE NULL COMMENT 'Datum vi skickade boknings-SMS för';

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

-- ──────────────────────────────────────────────────────────
-- 8. Systemuppdateringar + läst-spårning per användare
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `stat_uppdateringar` (
    `id`      INT           NOT NULL AUTO_INCREMENT,
    `slug`    VARCHAR(100)  NOT NULL UNIQUE COMMENT 'Unikt ID för denna uppdatering',
    `titel`   VARCHAR(255)  NOT NULL,
    `innehall` TEXT         NOT NULL COMMENT 'JSON-array med punkter',
    `skapad`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stat_uppdatering_sedd` (
    `anvandare_id`   INT NOT NULL,
    `uppdatering_id` INT NOT NULL,
    `sedd_datum`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`anvandare_id`, `uppdatering_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lägg in uppdatering 2025-04 (idempotent via INSERT IGNORE)
INSERT IGNORE INTO `stat_uppdateringar` (`slug`, `titel`, `innehall`, `skapad`) VALUES (
    'april-2025-kostnader-sms-arbetsorder',
    'Nytt: Kostnader, SMS och digital arbetsorder',
    '[
        {"ikon":"💰","rubrik":"Projektkostnader","text":"Du kan nu registrera kostnader direkt på ett projekt – t.ex. reservdelar eller material. Öppna ett projekt och scrolla ner till avsnittet Kostnader. Systemet räknar automatiskt ut vinsten för projektet."},
        {"ikon":"📊","rubrik":"Allmänna utgifter","text":"Under Ekonomi → Allmänna utgifter registrerar du löpande kostnader som hyra, el, försäkringar och liknande. Återkommande utgifter räknas in automatiskt varje månad i ekonomiöversikten."},
        {"ikon":"📈","rubrik":"Ekonomiöversikt","text":"Nytt avsnitt under Ekonomi med en samlad bild av intäkter, kostnader och vinst – per månad och per projekt. Här ser du hur verksamheten mår ekonomiskt."},
        {"ikon":"📱","rubrik":"SMS – bokningsbekräftelse","text":"När du fyller i Planerat datum på ett projekt skickas ett SMS automatiskt till kunden med datum, tid och vägbeskrivning till verkstan. Inget du behöver tänka på – det sker direkt."},
        {"ikon":"🔗","rubrik":"SMS – digital arbetsorder","text":"När ett projekt markeras som Avslutad och Betald skickas ett SMS till kunden med en länk och en personlig PIN-kod. Kunden kan då öppna och läsa sin arbetsorder direkt i mobilen."},
        {"ikon":"📋","rubrik":"Digital arbetsorder för kunden","text":"Kunden ser fordon, uppdrag, åtgärd, priser och kontaktuppgifter i en snygg mobilanpassad sida. Länken är giltig i 7 dagar."},
        {"ikon":"⚠️","rubrik":"Viktigt att tänka på","text":"För att SMS ska skickas måste kundens telefonnummer vara ifyllt i formatet 07XXXXXXXX (10 siffror). Saknas numret skickas inget SMS. Kontrollera gärna befintliga projekt."}
    ]',
    '2025-04-15 00:00:00'
);

-- ==========================================================
-- Verifiera efter körning:
--   SHOW COLUMNS FROM stat_projekt;
--   SHOW CREATE TABLE stat_projekt_rader;
--   SHOW CREATE TABLE stat_projekt_kostnader;
--   SHOW CREATE TABLE stat_utgifter;
--   SHOW CREATE TABLE stat_uppdateringar;
--   SHOW CREATE TABLE stat_uppdatering_sedd;
-- ==========================================================
