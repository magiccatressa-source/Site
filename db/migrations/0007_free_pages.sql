-- ============================================================
-- FREE LANDING PAGES  (/free/<slug>)
-- Публичные страницы-лид-магниты с одним видео.
-- ============================================================
CREATE TABLE IF NOT EXISTS free_pages (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug             VARCHAR(80)  NOT NULL,
    eyebrow          VARCHAR(120) NOT NULL DEFAULT 'Бесплатный урок',
    heading          VARCHAR(255) NOT NULL,
    subline          VARCHAR(255) DEFAULT NULL,
    intro_text       TEXT         DEFAULT NULL,
    disclaimer       VARCHAR(255) DEFAULT NULL,
    kinescope_id     VARCHAR(100) NOT NULL DEFAULT '',
    is_vertical      TINYINT(1)   NOT NULL DEFAULT 0,
    meta_description VARCHAR(300) DEFAULT NULL,
    is_visible       TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order       INT UNSIGNED NOT NULL DEFAULT 0,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_slug (slug),
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Первая страница: /free/sheya
INSERT INTO free_pages
    (slug, eyebrow, heading, subline, intro_text, disclaimer, kinescope_id, is_vertical, meta_description, sort_order)
VALUES (
    'sheya',
    'Бесплатный урок',
    'Разгрузка шеи, плеч и лопаток',
    '18 минут • с подробными объяснениями',
    'Если постоянно хочется размять шею, плечи у ушей и между лопатками как будто кол — это для тебя. Снимем напряжение мягко и бережно.',
    'При острой боли — сначала к врачу.',
    '',
    1,
    'Бесплатный урок йоги: разгрузка шеи, плеч и лопаток. 18 минут с подробными объяснениями от Любови Лучистой.',
    10
)
ON DUPLICATE KEY UPDATE slug = slug;

-- Если урок уже заведён в каталоге — подставим его Kinescope ID автоматически.
-- Сработает только пока поле пустое, ручную правку в админке не затрёт.
UPDATE free_pages fp
JOIN lessons l ON l.title = 'Освобождаем шею, плечи от напряжения'
SET fp.kinescope_id = l.kinescope_id
WHERE fp.slug = 'sheya' AND fp.kinescope_id = '';
