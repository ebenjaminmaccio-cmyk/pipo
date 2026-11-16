-- =====================================================================
-- Pipo - Asistente personal con IA
-- Esquema MySQL 8 / MariaDB 10.6+  (utf8mb4)
-- Todas las tablas llevan user_id para que la app sea multiusuario
-- (hoy la usás vos solo; mañana se puede comercializar sin migrar datos).
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(120) NOT NULL,
    email           VARCHAR(190) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('admin','user') NOT NULL DEFAULT 'user',
    timezone        VARCHAR(64)  NOT NULL DEFAULT 'America/Argentina/Buenos_Aires',
    totp_secret     VARBINARY(255) NULL,            -- cifrado con sodium
    totp_enabled    TINYINT(1)   NOT NULL DEFAULT 0,
    digest_hour     TINYINT UNSIGNED NOT NULL DEFAULT 8, -- hora local del resumen diario
    webhook_token   CHAR(64)     NOT NULL,          -- para que el CRM envíe mensajes de WhatsApp
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at   DATETIME     NULL,
    UNIQUE KEY uq_webhook_token (webhook_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Intentos de login (rate limiting anti fuerza bruta)
CREATE TABLE IF NOT EXISTS login_attempts (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip          VARBINARY(16) NOT NULL,
    email       VARCHAR(190)  NOT NULL,
    success     TINYINT(1)    NOT NULL DEFAULT 0,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ip_time (ip, created_at),
    KEY idx_email_time (email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Credenciales de servicios externos (tokens OAuth de Google, contraseñas IMAP...)
-- Siempre cifradas con sodium (secretbox) usando APP_KEY.
CREATE TABLE IF NOT EXISTS integrations (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    provider    ENUM('google','imap','whatsapp_crm') NOT NULL,
    label       VARCHAR(190) NOT NULL,               -- ej: "benja@gmail.com"
    secret      VARBINARY(4096) NOT NULL,            -- JSON cifrado
    settings    JSON NULL,                           -- datos no sensibles
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_user_provider (user_id, provider),
    CONSTRAINT fk_integrations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tareas y recordatorios
CREATE TABLE IF NOT EXISTS tasks (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    title         VARCHAR(255) NOT NULL,
    notes         TEXT NULL,
    priority      ENUM('baja','media','alta') NOT NULL DEFAULT 'media',
    due_at        DATETIME NULL,       -- UTC
    remind_at     DATETIME NULL,       -- UTC
    reminded_at   DATETIME NULL,
    status        ENUM('pendiente','hecha') NOT NULL DEFAULT 'pendiente',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at  DATETIME NULL,
    KEY idx_user_status (user_id, status),
    KEY idx_remind (remind_at, reminded_at),
    CONSTRAINT fk_tasks_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agenda local (se usa siempre; si Google Calendar está conectado se sincroniza allá también)
CREATE TABLE IF NOT EXISTS events (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    title         VARCHAR(255) NOT NULL,
    description   TEXT NULL,
    location      VARCHAR(255) NULL,
    attendees     JSON NULL,
    starts_at     DATETIME NOT NULL,   -- UTC
    ends_at       DATETIME NOT NULL,   -- UTC
    google_id     VARCHAR(255) NULL,
    remind_minutes SMALLINT UNSIGNED NULL DEFAULT 30,
    reminded_at   DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user_start (user_id, starts_at),
    CONSTRAINT fk_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Caché de mails (Gmail o IMAP)
CREATE TABLE IF NOT EXISTS emails (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    integration_id  INT UNSIGNED NOT NULL,
    remote_id       VARCHAR(255) NOT NULL,
    thread_id       VARCHAR(255) NULL,
    message_id_hdr  VARCHAR(512) NULL,   -- cabecera Message-ID (para responder en hilo)
    from_name       VARCHAR(255) NULL,
    from_email      VARCHAR(255) NULL,
    to_email        TEXT NULL,
    subject         VARCHAR(998) NULL,
    snippet         VARCHAR(500) NULL,
    body_text       MEDIUMTEXT NULL,
    received_at     DATETIME NULL,       -- UTC
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    ai_summary      TEXT NULL,
    ai_priority     ENUM('baja','media','alta') NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_remote (integration_id, remote_id),
    KEY idx_user_received (user_id, received_at),
    CONSTRAINT fk_emails_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_emails_integration FOREIGN KEY (integration_id) REFERENCES integrations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Borradores de respuesta: el asistente NUNCA envía solo, deja el borrador para que lo apruebes
CREATE TABLE IF NOT EXISTS email_drafts (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    integration_id  INT UNSIGNED NOT NULL,
    email_id        BIGINT UNSIGNED NULL,   -- mail al que responde (si aplica)
    to_email        VARCHAR(998) NOT NULL,
    subject         VARCHAR(998) NOT NULL,
    body_text       MEDIUMTEXT NOT NULL,
    status          ENUM('pendiente','enviado','descartado','error') NOT NULL DEFAULT 'pendiente',
    error           TEXT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at         DATETIME NULL,
    KEY idx_user_status (user_id, status),
    CONSTRAINT fk_drafts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_drafts_integration FOREIGN KEY (integration_id) REFERENCES integrations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mensajes de WhatsApp (llegan por webhook desde el CRM o importando un chat exportado .txt)
CREATE TABLE IF NOT EXISTS whatsapp_messages (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    source      ENUM('crm','import','cloud_api') NOT NULL,
    remote_id   VARCHAR(255) NULL,
    chat        VARCHAR(255) NOT NULL,     -- nombre del chat o número
    sender      VARCHAR(255) NOT NULL,
    direction   ENUM('in','out') NOT NULL DEFAULT 'in',
    body        TEXT NOT NULL,
    sent_at     DATETIME NOT NULL,         -- UTC
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_remote (user_id, source, remote_id),
    KEY idx_user_chat_time (user_id, chat, sent_at),
    KEY idx_user_time (user_id, sent_at),
    CONSTRAINT fk_wa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conversación con el asistente
CREATE TABLE IF NOT EXISTS chat_messages (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    role        ENUM('user','assistant') NOT NULL,
    content     MEDIUMTEXT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user_time (user_id, created_at),
    CONSTRAINT fk_chat_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Memoria de largo plazo: cosas que el asistente sabe de vos ("mi socio es Juan", "no agendar antes de las 9")
CREATE TABLE IF NOT EXISTS memories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    fact        VARCHAR(1000) NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user (user_id),
    CONSTRAINT fk_mem_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Resúmenes diarios generados
CREATE TABLE IF NOT EXISTS digests (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    digest_date DATE NOT NULL,
    content     MEDIUMTEXT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_date (user_id, digest_date),
    CONSTRAINT fk_digest_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Consumo de IA (útil para controlar costos y, a futuro, para facturar)
CREATE TABLE IF NOT EXISTS ai_usage (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED NOT NULL,
    feature        VARCHAR(40) NOT NULL,
    model          VARCHAR(80) NOT NULL,
    input_tokens   INT UNSIGNED NOT NULL DEFAULT 0,
    output_tokens  INT UNSIGNED NOT NULL DEFAULT 0,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user_time (user_id, created_at),
    CONSTRAINT fk_usage_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Auditoría: qué hizo el asistente y cuándo
CREATE TABLE IF NOT EXISTS audit_log (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL,
    action      VARCHAR(80) NOT NULL,
    detail      JSON NULL,
    ip          VARBINARY(16) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user_time (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
