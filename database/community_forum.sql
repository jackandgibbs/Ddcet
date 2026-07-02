-- ============================================================================
-- Community → student forum.
--
-- The original community_posts table was admin-only (author_id + author_type,
-- category limited to admin labels), but the student-facing page tried to post
-- student_id — which didn't exist — so student posts silently failed. This
-- migration makes community_posts student-authored while keeping existing admin
-- posts working, and adds a comments table for replies.
--
-- Convention after this migration:
--   * student post -> student_id set, author_type = 'student'
--   * admin post   -> student_id NULL (admin/community.php inserts as before)
-- post_reactions / post_bookmarks already FK community_posts(id) — reused as-is.
--
-- Run once in the Supabase SQL Editor (after schema.sql).
-- ============================================================================

-- Author + ordering columns.
ALTER TABLE community_posts ADD COLUMN IF NOT EXISTS student_id INT REFERENCES students(id) ON DELETE CASCADE;
ALTER TABLE community_posts ADD COLUMN IF NOT EXISTS is_pinned BOOLEAN DEFAULT FALSE;

-- Relax the CHECK constraints so student content is allowed. Postgres needs a
-- drop + re-add (constraint names follow the default <table>_<col>_check pattern).
ALTER TABLE community_posts DROP CONSTRAINT IF EXISTS community_posts_category_check;
ALTER TABLE community_posts ADD CONSTRAINT community_posts_category_check
    CHECK (category IN ('discussion','study_tip','doubt','motivation','resource','tip','announcement'));

ALTER TABLE community_posts DROP CONSTRAINT IF EXISTS community_posts_author_type_check;
ALTER TABLE community_posts ADD CONSTRAINT community_posts_author_type_check
    CHECK (author_type IN ('admin','moderator','student'));

-- Faster feed ordering (pinned first, then newest).
CREATE INDEX IF NOT EXISTS idx_community_posts_feed ON community_posts(is_visible, is_pinned, created_at DESC);

-- Threaded replies on a post. parent_id nests a reply under another comment
-- (Reddit-style); NULL = a top-level comment on the post. Deleting a comment
-- cascades to its replies.
CREATE TABLE IF NOT EXISTS community_comments (
    id SERIAL PRIMARY KEY,
    post_id INT REFERENCES community_posts(id) ON DELETE CASCADE,
    student_id INT REFERENCES students(id) ON DELETE CASCADE,
    parent_id INT REFERENCES community_comments(id) ON DELETE CASCADE,
    body TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);
-- If the table already existed without parent_id, add it.
ALTER TABLE community_comments ADD COLUMN IF NOT EXISTS parent_id INT REFERENCES community_comments(id) ON DELETE CASCADE;
CREATE INDEX IF NOT EXISTS idx_community_comments_post ON community_comments(post_id, created_at);
CREATE INDEX IF NOT EXISTS idx_community_comments_parent ON community_comments(parent_id);
