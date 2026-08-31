-- Add AI request tracking for Basic users
ALTER TABLE students ADD COLUMN IF NOT EXISTS ai_requests INT DEFAULT 0;
ALTER TABLE students ADD COLUMN IF NOT EXISTS ai_reset_date DATE;
