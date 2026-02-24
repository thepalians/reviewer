-- Add telegram fields to users table
ALTER TABLE users 
  ADD COLUMN telegram_chat_id VARCHAR(50) DEFAULT NULL,
  ADD COLUMN telegram_connected_at DATETIME DEFAULT NULL,
  ADD INDEX idx_telegram_chat_id (telegram_chat_id);
