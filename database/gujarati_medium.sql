-- Gujarati medium support (bilingual exam).
-- DDCET is an official bilingual (English/Gujarati) paper, so questions and
-- options get parallel Gujarati columns. The exam renders the student's chosen
-- medium and falls back to the English text wherever the Gujarati is still empty
-- — so this can ship before any content is translated.
-- Run this in the Supabase SQL Editor.

-- Parallel Gujarati text alongside the existing English columns.
ALTER TABLE questions ADD COLUMN IF NOT EXISTS question_text_gu TEXT;
ALTER TABLE questions ADD COLUMN IF NOT EXISTS explanation_gu  TEXT;
ALTER TABLE options   ADD COLUMN IF NOT EXISTS option_text_gu  TEXT;

-- The medium a sitting was taken in ('en' | 'gu'), so the result/review screen
-- can later show the same language the student actually saw.
ALTER TABLE attempts ADD COLUMN IF NOT EXISTS language VARCHAR(5) DEFAULT 'en';

-- The student's default medium, remembered across attempts.
ALTER TABLE students ADD COLUMN IF NOT EXISTS preferred_language VARCHAR(5) DEFAULT 'en';
