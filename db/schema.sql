-- ============================================================
-- Yoga Club LK — Database Schema
-- Encoding: UTF-8, Engine: InnoDB
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+03:00';

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                 VARCHAR(120) NOT NULL,
    last_name            VARCHAR(120) DEFAULT NULL,
    email                VARCHAR(255) NOT NULL UNIQUE,
    password_hash        VARCHAR(255) NOT NULL,
    phone                VARCHAR(30) DEFAULT NULL,
    vk_url               VARCHAR(255) DEFAULT NULL,
    tg_url               VARCHAR(255) DEFAULT NULL,
    role                 ENUM('user','admin') NOT NULL DEFAULT 'user',
    email_verified       TINYINT(1) NOT NULL DEFAULT 0,
    email_verify_token   VARCHAR(64) DEFAULT NULL,
    email_verify_expires DATETIME DEFAULT NULL,
    consent_pd           TINYINT(1) NOT NULL DEFAULT 0,
    consent_offer        TINYINT(1) NOT NULL DEFAULT 0,
    consent_at           DATETIME DEFAULT NULL,
    consent_ip           VARCHAR(45) DEFAULT NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SUBSCRIPTIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS subscriptions (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED NOT NULL,
    status           ENUM('active','inactive','expired','trial') NOT NULL DEFAULT 'inactive',
    is_paused        TINYINT(1) NOT NULL DEFAULT 0,
    payment_status   ENUM('paid','pending') DEFAULT NULL,
    lessons_total    TINYINT UNSIGNED NOT NULL DEFAULT 9,
    started_at       DATE DEFAULT NULL,
    expires_at       DATE DEFAULT NULL,
    pause_started_at DATE DEFAULT NULL,
    pause_notes      VARCHAR(255) DEFAULT NULL,
    notes            TEXT DEFAULT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TOPICS
-- ============================================================
CREATE TABLE IF NOT EXISTS topics (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    sort_order  INT UNSIGNED NOT NULL DEFAULT 0,
    is_visible  TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- LESSONS
-- ============================================================
CREATE TABLE IF NOT EXISTS lessons (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    topic_id      INT UNSIGNED NOT NULL,
    title         VARCHAR(255) NOT NULL,
    description   TEXT DEFAULT NULL,
    kinescope_id  VARCHAR(100) NOT NULL,
    duration_min  SMALLINT UNSIGNED DEFAULT NULL,
    sort_order    INT UNSIGNED NOT NULL DEFAULT 0,
    is_visible    TINYINT(1) NOT NULL DEFAULT 1,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE,
    INDEX idx_topic_id (topic_id),
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- LESSON PROGRESS
-- ============================================================
CREATE TABLE IF NOT EXISTS lesson_progress (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED NOT NULL,
    lesson_id      INT UNSIGNED NOT NULL,
    watch_seconds  INT UNSIGNED NOT NULL DEFAULT 0,
    completed      TINYINT(1) NOT NULL DEFAULT 0,
    watched_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    UNIQUE KEY uq_user_lesson (user_id, lesson_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- LESSON FAVORITES
-- ============================================================
CREATE TABLE IF NOT EXISTS lesson_favorites (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    lesson_id  INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    UNIQUE KEY uq_user_lesson (user_id, lesson_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SETTINGS
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
    setting_key   VARCHAR(100) PRIMARY KEY,
    setting_value TEXT DEFAULT NULL,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by    INT UNSIGNED DEFAULT NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key, setting_value) VALUES
('zoom_link',                ''),
('telegram_chat_link',       ''),
('schedule_text',            'Понедельник и четверг, 19:00 МСК'),
('welcome_kinescope_id',     ''),
('welcome_text',             'Добро пожаловать в клуб! Посмотрите это вводное видео, чтобы познакомиться с форматом занятий.'),
('subscription_lessons_count', '9')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- ============================================================
-- PASSWORD RESETS
-- ============================================================
CREATE TABLE IF NOT EXISTS password_resets (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    token_hash  VARCHAR(64) NOT NULL,
    expires_at  DATETIME NOT NULL,
    used        TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token_hash (token_hash),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ADMIN LOG
-- ============================================================
CREATE TABLE IF NOT EXISTS admin_log (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id    INT UNSIGNED NOT NULL,
    action      VARCHAR(100) NOT NULL,
    target_type VARCHAR(50) DEFAULT NULL,
    target_id   INT UNSIGNED DEFAULT NULL,
    details     JSON DEFAULT NULL,
    ip          VARCHAR(45) DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_admin_id (admin_id),
    INDEX idx_created_at (created_at),
    INDEX idx_target (target_type, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
