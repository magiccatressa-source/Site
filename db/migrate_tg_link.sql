-- Добавить поля для привязки Telegram-аккаунта
ALTER TABLE users
    ADD COLUMN telegram_chat_id    BIGINT DEFAULT NULL AFTER tg_url,
    ADD COLUMN telegram_link_token VARCHAR(64) DEFAULT NULL AFTER telegram_chat_id;
