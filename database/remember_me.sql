-- Adds a token column to the students table to support "Remember Me" login sessions.
ALTER TABLE students ADD COLUMN IF NOT EXISTS remember_token VARCHAR(128);
