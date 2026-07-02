-- DDCET Platform — Contact / Support Query system
-- Run this in the Supabase SQL Editor.
--
-- A "query" is a support/contact message a logged-in student submits from the
-- Contact Us page. Admins triage it through three states:
--   new          -> just submitted, nobody has looked at it yet
--   in_progress  -> an admin has picked it up / is working on it
--   completed    -> resolved (optionally with a reply the student can read)
--
-- The student sees the status + reply of their OWN queries on contact.php;
-- admins manage every query from admin/queries.php.
CREATE TABLE IF NOT EXISTS queries (
    id          SERIAL PRIMARY KEY,
    student_id  INT REFERENCES students(id) ON DELETE CASCADE,
    name        VARCHAR(120),
    email       VARCHAR(160),
    subject     VARCHAR(200) NOT NULL,
    message     TEXT NOT NULL,
    status      VARCHAR(20) DEFAULT 'new',   -- new | in_progress | completed
    admin_reply TEXT,
    created_at  TIMESTAMP DEFAULT NOW(),
    updated_at  TIMESTAMP DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_queries_student ON queries(student_id);
CREATE INDEX IF NOT EXISTS idx_queries_status  ON queries(status);
CREATE INDEX IF NOT EXISTS idx_queries_created ON queries(created_at DESC);
