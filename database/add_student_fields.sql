-- Run this in Supabase SQL Editor to add new columns
ALTER TABLE students ADD COLUMN IF NOT EXISTS semester INT;
ALTER TABLE students ADD COLUMN IF NOT EXISTS department VARCHAR(100);
ALTER TABLE students ADD COLUMN IF NOT EXISTS mobile VARCHAR(15);
