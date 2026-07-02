-- ============================================================================
-- Institution / B2B layer
--
-- Lets a coaching institute or college run its OWN private batch on the
-- platform: their own question bank, their own assignments, and analytics
-- scoped to their own students — with a hard guarantee that one institution
-- can never see another's content or students, and that private content never
-- leaks into the public practice pool.
--
-- Run this whole file once in the Supabase SQL Editor.
--
-- Scoping model (important):
--   * The public practice pool is every question with test_id IS NULL.
--   * Institution questions are ALWAYS attached to an institution-owned test
--     (test_id set), so the existing pool queries (test_id=is.null) exclude
--     them automatically — zero changes needed to the practice engine.
--   * org_id on tests/questions is belt-and-suspenders: it lets the portal
--     filter "my org's content" in one query and lets public listings exclude
--     org content explicitly (tests.php / mocks.php add org_id=is.null).
-- ============================================================================

-- An organization = one institute/college tenant.
CREATE TABLE IF NOT EXISTS organizations (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    -- Students join a batch by entering this code (see join-org.php).
    join_code VARCHAR(12) UNIQUE NOT NULL,
    -- The flagged student account that owns/administers this org.
    owner_id INT REFERENCES students(id) ON DELETE SET NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW()
);

-- students gains two columns:
--   is_institution = TRUE  -> this account can open the institution portal
--   org_id                 -> which org this account belongs to. Set for BOTH
--                             the owner and every member student in the batch.
ALTER TABLE students ADD COLUMN IF NOT EXISTS is_institution BOOLEAN DEFAULT FALSE;
ALTER TABLE students ADD COLUMN IF NOT EXISTS org_id INT REFERENCES organizations(id) ON DELETE SET NULL;

-- tests / questions gain an owning org. NULL = public platform content
-- (the default for every existing row, so this migration is backward safe).
ALTER TABLE tests     ADD COLUMN IF NOT EXISTS org_id INT REFERENCES organizations(id) ON DELETE CASCADE;
ALTER TABLE questions ADD COLUMN IF NOT EXISTS org_id INT REFERENCES organizations(id) ON DELETE CASCADE;

CREATE INDEX IF NOT EXISTS idx_students_org   ON students(org_id);
CREATE INDEX IF NOT EXISTS idx_tests_org      ON tests(org_id);
CREATE INDEX IF NOT EXISTS idx_questions_org  ON questions(org_id);
CREATE INDEX IF NOT EXISTS idx_orgs_join_code ON organizations(join_code);
