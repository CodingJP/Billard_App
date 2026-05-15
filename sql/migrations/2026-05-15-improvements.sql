-- ============================================================
-- Migration: 2026-05-15 – App-Erweiterungen
-- ============================================================
-- Hinweis: Dieses Skript darf nur einmalig ausgeführt werden.
-- Die ALTER TABLE Statements schlagen fehl, wenn Spalten bereits existieren.

-- Rate-Limiting für Login / Registrierung
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_hash      VARCHAR(64)  NOT NULL,
    attempted_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_attempts_ip (ip_hash, attempted_at)
) ENGINE=InnoDB;

-- Web-Push-Abonnements
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    endpoint   TEXT         NOT NULL,
    p256dh     VARCHAR(255) NOT NULL,
    auth_key   VARCHAR(64)  NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_push_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    UNIQUE KEY uq_push_endpoint (user_id, endpoint(191))
) ENGINE=InnoDB;

-- Wettkampf: Beschreibung und Beitritts-Modus (idempotent für MySQL 8.0)
SET @col_desc = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'competitions' AND COLUMN_NAME = 'description');
SET @sql_desc = IF(@col_desc = 0,
    'ALTER TABLE competitions ADD COLUMN description TEXT DEFAULT NULL AFTER name',
    'SELECT 1');
PREPARE stmt FROM @sql_desc; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_join = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'competitions' AND COLUMN_NAME = 'join_mode');
SET @sql_join = IF(@col_join = 0,
    "ALTER TABLE competitions ADD COLUMN join_mode ENUM('open','invite') NOT NULL DEFAULT 'open' AFTER status",
    'SELECT 1');
PREPARE stmt FROM @sql_join; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Wettkampf-Einladungen (für invite-only Wettkaempfe)
CREATE TABLE IF NOT EXISTS competition_invitations (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    competition_id INT UNSIGNED NOT NULL,
    user_id        INT UNSIGNED NOT NULL,
    invited_by     INT UNSIGNED NOT NULL,
    status         ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ci (competition_id, user_id),
    CONSTRAINT fk_ci_competition FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE CASCADE,
    CONSTRAINT fk_ci_user        FOREIGN KEY (user_id)        REFERENCES users        (id) ON DELETE CASCADE,
    CONSTRAINT fk_ci_invited_by  FOREIGN KEY (invited_by)     REFERENCES users        (id) ON DELETE CASCADE
) ENGINE=InnoDB;
