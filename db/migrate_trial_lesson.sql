-- Migration: add trial_lesson_id to settings
INSERT INTO settings (setting_key, setting_value) VALUES ('trial_lesson_id', '')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
