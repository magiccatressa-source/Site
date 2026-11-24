-- ============================================================
-- Миграция 0007 — мультипродуктовая архитектура
--
-- Что делает:
--   1. Создаёт таблицы products, product_topics, orders, user_access
--   2. Создаёт продукт "club" с текущими ценами клуба
--   3. Привязывает все существующие темы к продукту "club"
--   4. Переносит существующие подписки в user_access 1:1
--      (даты, заморозки, пробные доступы, заметки)
--
-- Чего НЕ делает:
--   Таблица subscriptions не изменяется и не удаляется — остаётся
--   нетронутым снимком состояния на случай откат. Удалить её можно
--   будет отдельной миграцией после того, как новая модель
--   проработает хотя бы один релиз.
--
-- ВАЖНО: применять вручную через phpMyAdmin ДО деплоя кода.
--        Перед применением снять дамп базы.
--
-- Внизу файла — закомментированные сверочные запросы. Прогнать их
-- сразу после миграции и сверить числа.
-- ============================================================

SET NAMES utf8mb4;

-- ============================================================
-- PRODUCTS — каталог продуктов
-- ============================================================
CREATE TABLE IF NOT EXISTS products (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug           VARCHAR(100) NOT NULL,
    title          VARCHAR(255) NOT NULL,
    subtitle       VARCHAR(255) DEFAULT NULL COMMENT 'Короткая строка для карточки на витрине',
    description    TEXT DEFAULT NULL,
    type           ENUM('subscription','one_time') NOT NULL DEFAULT 'one_time',
    price          DECIMAL(10,2) NOT NULL DEFAULT 0,
    price_renewal  DECIMAL(10,2) DEFAULT NULL COMMENT 'Цена продления, только для subscription. NULL = та же цена',
    duration_days  INT UNSIGNED DEFAULT NULL COMMENT 'Срок одного периода. NULL = бессрочно',
    payment_url    VARCHAR(500) DEFAULT NULL COMMENT 'Ссылка на платёжную форму',
    contact_url    VARCHAR(500) DEFAULT NULL COMMENT 'Куда писать, пока оплата не подключена',
    cover_image    VARCHAR(255) DEFAULT NULL,
    is_active      TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Показывать ли на витрине',
    sort_order     INT UNSIGNED NOT NULL DEFAULT 0,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_slug (slug),
    INDEX idx_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PRODUCT_TOPICS — какие темы входят в какой продукт
--
-- Связующая таблица, а не FK в topics: один и тот же урок может
-- входить и в клуб, и в курс (например, приветственный урок).
-- ============================================================
CREATE TABLE IF NOT EXISTS product_topics (
    product_id INT UNSIGNED NOT NULL,
    topic_id   INT UNSIGNED NOT NULL,
    PRIMARY KEY (product_id, topic_id),
    INDEX idx_topic (topic_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (topic_id)   REFERENCES topics(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ORDERS — заказы
--
-- Создаётся уже сейчас, хотя приём платежей делается позже:
-- сюда переезжает payment_status ('оплачено' / 'ожидает
-- подтверждения'), который иначе пришлось бы держать временной
-- колонкой в user_access.
--
-- product_id — ON DELETE RESTRICT: историю платежей нельзя терять
-- из-за удаления продукта. Админка должна запрещать удаление
-- продукта, по которому есть заказы.
-- ============================================================
CREATE TABLE IF NOT EXISTS orders (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED NOT NULL,
    product_id       INT UNSIGNED NOT NULL,
    amount           DECIMAL(10,2) NOT NULL DEFAULT 0,
    status           ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    payment_provider VARCHAR(50) DEFAULT NULL COMMENT 'phone | prodamus | manual | ...',
    external_id      VARCHAR(100) DEFAULT NULL COMMENT 'ID транзакции на стороне провайдера',
    notes            VARCHAR(500) DEFAULT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at          DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    INDEX idx_user_product (user_id, product_id),
    INDEX idx_status (status),
    INDEX idx_external_id (external_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- USER_ACCESS — доступы пользователей к продуктам
--
-- Заморозка хранится отдельным флагом is_frozen, а не статусом:
-- сейчас заморозка клуба НЕ отбирает доступ (админ по заявке просто
-- продлевает дату), и статус 'frozen' внутри status сломал бы это
-- поведение действующим участникам. См. has_access() в
-- includes/access.php — is_frozen там намеренно не проверяется.
--
-- is_trial — пробный доступ. Доступ выдаётся к продукту целиком,
-- а фронт прячет непробные уроки по lessons.is_trial, как и раньше.
--
-- expires_at здесь DATETIME, а в subscriptions была DATE. При
-- переносе дата превращается в КОНЕЦ того же дня (23:59:59),
-- иначе доступ обрывался бы в полночь последнего дня и каждый
-- действующий участник терял бы один день.
-- ============================================================
CREATE TABLE IF NOT EXISTS user_access (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    product_id   INT UNSIGNED NOT NULL,
    starts_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at   DATETIME DEFAULT NULL COMMENT 'NULL = бессрочный доступ',
    status       ENUM('active','expired','revoked') NOT NULL DEFAULT 'active',
    is_frozen    TINYINT(1) NOT NULL DEFAULT 0,
    frozen_at    DATE DEFAULT NULL,
    freeze_notes VARCHAR(255) DEFAULT NULL,
    is_trial     TINYINT(1) NOT NULL DEFAULT 0,
    source       ENUM('manual','payment','promo') NOT NULL DEFAULT 'manual',
    order_id     INT UNSIGNED DEFAULT NULL,
    notes        TEXT DEFAULT NULL COMMENT 'Заметка администратора',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE SET NULL,
    INDEX idx_user_product (user_id, product_id),
    INDEX idx_product_status (product_id, status),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ДАННЫЕ 1 — продукт "Клуб"
--
-- Цены оставлены как на сайте сегодня: 3500 первая оплата,
-- 3000 продление. Менять цену — в админке, не здесь.
-- duration_days = 30: столько сейчас даёт и вебхук, и кнопка
-- "нажать после оплаты".
-- ============================================================
INSERT INTO products
    (slug, title, subtitle, description, type, price, price_renewal,
     duration_days, payment_url, contact_url, is_active, sort_order)
SELECT
    'club',
    'Клуб «Лучистая йога»',
    'Живые занятия дважды в неделю и весь архив уроков',
    'Живые занятия в Zoom два раза в неделю, доступ ко всему архиву уроков, записи всех эфиров, ответы на вопросы и поддерживающее сообщество.',
    'subscription',
    3500.00,
    3000.00,
    30,
    'https://luchistaya-yoga.payform.ru',
    'https://t.me/indicatrisa',
    1,
    10
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM products WHERE slug = 'club');

-- ============================================================
-- ДАННЫЕ 2 — все существующие темы входят в клуб
-- ============================================================
INSERT IGNORE INTO product_topics (product_id, topic_id)
SELECT p.id, t.id
FROM topics t
CROSS JOIN (SELECT id FROM products WHERE slug = 'club') p;

-- ============================================================
-- ДАННЫЕ 3 — заказы из истории подписок
--
-- Нужны, чтобы сохранить состояние "ожидает подтверждения" и
-- признак "клиент уже платил" (от него зависит цена продления).
-- amount = 0: историческая сумма в subscriptions не хранилась.
-- ============================================================
INSERT INTO orders
    (user_id, product_id, amount, status, payment_provider, external_id,
     notes, created_at, paid_at)
SELECT
    s.user_id,
    p.id,
    0,
    CASE WHEN s.payment_status = 'paid' THEN 'paid' ELSE 'pending' END,
    COALESCE(s.payment_method, 'manual'),
    s.prodamus_transaction_id,
    'Перенесено из subscriptions миграцией 0007, сумма неизвестна',
    s.created_at,
    CASE WHEN s.payment_status = 'paid'
         THEN COALESCE(s.payment_date, s.created_at)
         ELSE NULL END
FROM subscriptions s
CROSS JOIN (SELECT id FROM products WHERE slug = 'club') p
WHERE s.payment_status IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM orders o WHERE o.user_id = s.user_id);

-- ============================================================
-- ДАННЫЕ 4 — доступы из подписок
--
-- Соответствие статусов:
--   active   -> active
--   trial    -> active + is_trial = 1
--   expired  -> expired
--   inactive -> revoked  (так админка помечает отключённых вручную)
-- ============================================================
INSERT INTO user_access
    (user_id, product_id, starts_at, expires_at, status,
     is_frozen, frozen_at, freeze_notes, is_trial, source, notes, created_at)
SELECT
    s.user_id,
    p.id,
    COALESCE(s.started_at, DATE(s.created_at)),
    CASE WHEN s.expires_at IS NULL
         THEN NULL
         ELSE TIMESTAMP(s.expires_at, '23:59:59') END,
    CASE
        WHEN s.status IN ('active', 'trial') THEN 'active'
        WHEN s.status = 'inactive'           THEN 'revoked'
        ELSE 'expired'
    END,
    s.is_paused,
    s.pause_started_at,
    s.pause_notes,
    CASE WHEN s.status = 'trial' THEN 1 ELSE 0 END,
    CASE WHEN s.payment_status IS NOT NULL THEN 'payment' ELSE 'manual' END,
    s.notes,
    s.created_at
FROM subscriptions s
CROSS JOIN (SELECT id FROM products WHERE slug = 'club') p
WHERE NOT EXISTS (
    SELECT 1 FROM user_access ua
    WHERE ua.user_id = s.user_id AND ua.product_id = p.id
);

-- Привязать доступы к перенесённым заказам
UPDATE user_access ua
JOIN orders o
  ON o.user_id = ua.user_id AND o.product_id = ua.product_id
SET ua.order_id = o.id
WHERE ua.order_id IS NULL;

-- ============================================================
-- СВЕРКА — раскомментировать и прогнать после миграции.
-- Все четыре запроса должны вернуть пустой результат или нули.
-- ============================================================

-- 1. Число подписок и доступов должно совпадать:
-- SELECT
--   (SELECT COUNT(*) FROM subscriptions) AS subscriptions,
--   (SELECT COUNT(*) FROM user_access)   AS accesses;

-- 2. У кого доступ есть сейчас по старой модели, но нет по новой
--    (должно быть пусто):
-- SELECT s.user_id, s.status, s.expires_at
-- FROM subscriptions s
-- WHERE s.status IN ('active','trial')
--   AND (s.expires_at IS NULL OR s.expires_at >= CURDATE())
--   AND NOT EXISTS (
--     SELECT 1 FROM user_access ua
--     WHERE ua.user_id = s.user_id AND ua.status = 'active'
--       AND (ua.expires_at IS NULL OR ua.expires_at > NOW())
--   );

-- 3. Расхождение дат окончания (должно быть пусто):
-- SELECT s.user_id, s.expires_at AS was, ua.expires_at AS now_is
-- FROM subscriptions s
-- JOIN user_access ua ON ua.user_id = s.user_id
-- WHERE NOT (s.expires_at IS NULL AND ua.expires_at IS NULL)
--   AND DATE(ua.expires_at) <> s.expires_at;

-- 4. Темы без продукта (должно быть пусто — иначе их уроки
--    станут никому не видны):
-- SELECT t.id, t.title FROM topics t
-- WHERE NOT EXISTS (SELECT 1 FROM product_topics pt WHERE pt.topic_id = t.id);
