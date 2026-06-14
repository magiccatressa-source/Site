-- Миграция для добавления поддержки Prodamus

-- Добавляем новые колонки к таблице subscriptions
ALTER TABLE subscriptions
  ADD COLUMN payment_method ENUM('phone', 'prodamus', 'manual') DEFAULT 'phone' AFTER payment_status,
  ADD COLUMN prodamus_order_id VARCHAR(100) DEFAULT NULL AFTER payment_method,
  ADD COLUMN prodamus_transaction_id VARCHAR(100) DEFAULT NULL AFTER prodamus_order_id,
  ADD COLUMN payment_date DATETIME DEFAULT NULL AFTER prodamus_transaction_id;

-- Добавляем индексы для быстрого поиска по Prodamus транзакциям
CREATE INDEX idx_prodamus_order_id ON subscriptions(prodamus_order_id);
CREATE INDEX idx_prodamus_transaction_id ON subscriptions(prodamus_transaction_id);

-- Добавляем в settings Prodamus API ключи и IDs
INSERT INTO settings (setting_key, setting_value) VALUES
  ('prodamus_merchant_id', ''),
  ('prodamus_api_key', ''),
  ('prodamus_secret_key', ''),
  ('subscription_price', '990'),
  ('subscription_duration_days', '30')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
