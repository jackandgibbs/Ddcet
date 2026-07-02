-- ============================================================================
-- Email reminders support columns.
--
-- The reminder cron (cron/send_reminders.php) uses these to avoid re-sending:
--   tests.reminder_sent_at         — set when a scheduled mock's "starts soon"
--                                     blast has gone out (one per mock).
--   students.last_reminder_sent_at — throttles inactivity nudges per user.
--   students.email_opt_out         — user unsubscribed from non-transactional
--                                     mail (welcome/receipt still send; they're
--                                     transactional). Set via unsubscribe.php.
--
-- Run once in the Supabase SQL Editor.
-- ============================================================================

ALTER TABLE tests    ADD COLUMN IF NOT EXISTS reminder_sent_at      TIMESTAMP;
ALTER TABLE students ADD COLUMN IF NOT EXISTS last_reminder_sent_at TIMESTAMP;
ALTER TABLE students ADD COLUMN IF NOT EXISTS email_opt_out         BOOLEAN DEFAULT FALSE;
