-- Migration: add lesson_views table for cumulative watch statistics
-- Run once via phpMyAdmin or SSH mysql

CREATE TABLE IF NOT EXISTS lesson_views (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    lesson_id     INT UNSIGNED NOT NULL,
    watch_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    viewed_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    INDEX idx_user_id  (user_id),
    INDEX idx_lesson_id (lesson_id),
    INDEX idx_viewed_at (viewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
