-- Добавить поля эфира в таблицу уроков
ALTER TABLE lessons
    ADD COLUMN is_live   TINYINT(1) NOT NULL DEFAULT 0 AFTER is_trial,
    ADD COLUMN live_date DATE DEFAULT NULL AFTER is_live;
