-- DDCET Platform — B2B "Get a Free Demo" leads
-- Run this in the Supabase SQL Editor.
--
-- A "demo lead" is an institution (college / coaching / school) that requests a
-- demo from the public institutions.php landing page. This is the top of the
-- B2B funnel and does NOT require a logged-in account. Admins triage each lead
-- through three states from admin/leads.php:
--   new        -> just submitted, nobody has contacted them yet
--   contacted  -> sales/team has reached out / demo scheduled
--   converted  -> signed up / closed (or 'closed' if not interested)
CREATE TABLE IF NOT EXISTS demo_leads (
    id              SERIAL PRIMARY KEY,
    organization    VARCHAR(200) NOT NULL,   -- college / coaching name
    contact_name    VARCHAR(120) NOT NULL,
    email           VARCHAR(160) NOT NULL,
    phone           VARCHAR(20),
    role            VARCHAR(80),             -- e.g. Principal, HOD, Coordinator
    city            VARCHAR(120),
    student_count   VARCHAR(40),             -- approx batch size (free text/range)
    message         TEXT,
    status          VARCHAR(20) DEFAULT 'new',   -- new | contacted | converted | closed
    admin_notes     TEXT,
    created_at      TIMESTAMP DEFAULT NOW(),
    updated_at      TIMESTAMP DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_demo_leads_status  ON demo_leads(status);
CREATE INDEX IF NOT EXISTS idx_demo_leads_created ON demo_leads(created_at DESC);
