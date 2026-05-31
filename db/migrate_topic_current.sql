-- Migration: add is_current flag to topics
ALTER TABLE topics ADD COLUMN is_current TINYINT(1) NOT NULL DEFAULT 0;
