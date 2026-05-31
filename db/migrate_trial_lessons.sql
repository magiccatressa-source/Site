-- Migration: add is_trial flag to lessons
ALTER TABLE lessons ADD COLUMN is_trial TINYINT(1) NOT NULL DEFAULT 0;
CREATE INDEX idx_is_trial ON lessons (is_trial);
