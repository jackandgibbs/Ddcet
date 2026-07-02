done-- DDCET Platform - Full Supabase PostgreSQL Schema
-- Run this in Supabase SQL Editor

-- Colleges
CREATE TABLE colleges (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    city VARCHAR(100),
    created_at TIMESTAMP DEFAULT NOW()
);

-- Students
CREATE TABLE students (
    id SERIAL PRIMARY KEY,
    google_id VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    avatar_url TEXT,
    college_id INT REFERENCES colleges(id),
    onboarded BOOLEAN DEFAULT FALSE,
    xp INT DEFAULT 0,
    level VARCHAR(50) DEFAULT 'Beginner',
    streak INT DEFAULT 0,
    last_active_date DATE,
    is_banned BOOLEAN DEFAULT FALSE,
    referral_code VARCHAR(20) UNIQUE,
    referred_by INT REFERENCES students(id),
    created_at TIMESTAMP DEFAULT NOW()
);

-- Admins
CREATE TABLE admins (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_moderator BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Subscriptions
CREATE TABLE subscriptions (
    id SERIAL PRIMARY KEY,
    student_id INT REFERENCES students(id) ON DELETE CASCADE,
    plan VARCHAR(50) NOT NULL CHECK (plan IN ('basic', 'pro')),
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'expired', 'cancelled')),
    starts_at TIMESTAMP DEFAULT NOW(),
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Payments
CREATE TABLE payments (
    id SERIAL PRIMARY KEY,
    student_id INT REFERENCES students(id) ON DELETE CASCADE,
    subscription_id INT REFERENCES subscriptions(id),
    razorpay_payment_id VARCHAR(255),
    razorpay_order_id VARCHAR(255),
    amount INT NOT NULL, -- in paise
    currency VARCHAR(10) DEFAULT 'INR',
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'captured', 'failed')),
    plan VARCHAR(50),
    created_at TIMESTAMP DEFAULT NOW()
);

-- Tests
CREATE TABLE tests (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    mode VARCHAR(50) NOT NULL CHECK (mode IN ('full_mock', 'rapid_fire', 'subject_wise', 'previous_year', 'daily_challenge', 'weekly_challenge', 'revision', 'challenge_friend')),
    subject VARCHAR(100),
    duration_minutes INT NOT NULL,
    total_marks INT NOT NULL,
    total_questions INT NOT NULL,
    difficulty VARCHAR(20) DEFAULT 'medium',
    year INT, -- for previous year papers
    is_published BOOLEAN DEFAULT TRUE,
    is_free BOOLEAN DEFAULT FALSE,
    min_plan VARCHAR(50) DEFAULT 'basic',
    created_at TIMESTAMP DEFAULT NOW()
);

-- Questions
CREATE TABLE questions (
    id SERIAL PRIMARY KEY,
    test_id INT REFERENCES tests(id) ON DELETE CASCADE,
    subject VARCHAR(100) NOT NULL,
    chapter VARCHAR(255),
    question_text TEXT NOT NULL,
    question_image TEXT,
    explanation TEXT,
    difficulty VARCHAR(20) DEFAULT 'medium' CHECK (difficulty IN ('easy', 'medium', 'hard')),
    marks INT DEFAULT 1,
    negative_marks NUMERIC(3,2) DEFAULT 0,
    position INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Options
CREATE TABLE options (
    id SERIAL PRIMARY KEY,
    question_id INT REFERENCES questions(id) ON DELETE CASCADE,
    option_text TEXT NOT NULL,
    option_image TEXT,
    is_correct BOOLEAN DEFAULT FALSE,
    position INT DEFAULT 0
);

-- Attempts
CREATE TABLE attempts (
    id SERIAL PRIMARY KEY,
    student_id INT REFERENCES students(id) ON DELETE CASCADE,
    test_id INT REFERENCES tests(id) ON DELETE CASCADE,
    score NUMERIC(6,2) DEFAULT 0,
    total_marks INT,
    correct_count INT DEFAULT 0,
    incorrect_count INT DEFAULT 0,
    skipped_count INT DEFAULT 0,
    time_spent_seconds INT DEFAULT 0,
    percentile NUMERIC(5,2),
    started_at TIMESTAMP DEFAULT NOW(),
    completed_at TIMESTAMP,
    status VARCHAR(20) DEFAULT 'in_progress' CHECK (status IN ('in_progress', 'completed', 'abandoned')),
    tab_switches INT DEFAULT 0,
    xp_earned INT DEFAULT 0
);

-- Attempt Answers
CREATE TABLE attempt_answers (
    id SERIAL PRIMARY KEY,
    attempt_id INT REFERENCES attempts(id) ON DELETE CASCADE,
    question_id INT REFERENCES questions(id) ON DELETE CASCADE,
    selected_option_id INT REFERENCES options(id),
    is_correct BOOLEAN,
    is_flagged BOOLEAN DEFAULT FALSE,
    time_spent_seconds INT DEFAULT 0,
    status VARCHAR(20) DEFAULT 'not_visited' CHECK (status IN ('not_visited', 'answered', 'skipped', 'flagged'))
);

-- Leaderboard (materialized/cached)
CREATE TABLE leaderboard (
    id SERIAL PRIMARY KEY,
    student_id INT REFERENCES students(id) ON DELETE CASCADE,
    scope VARCHAR(50) NOT NULL CHECK (scope IN ('global', 'college', 'subject', 'weekly', 'friends')),
    scope_value VARCHAR(100), -- college_id, subject name, week date, etc.
    score NUMERIC(8,2) DEFAULT 0,
    rank INT,
    period_start DATE,
    period_end DATE,
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Community Posts
CREATE TABLE community_posts (
    id SERIAL PRIMARY KEY,
    author_id INT, -- admin or moderator student id
    author_type VARCHAR(20) DEFAULT 'admin' CHECK (author_type IN ('admin', 'moderator')),
    title VARCHAR(255),
    body TEXT NOT NULL,
    category VARCHAR(50) CHECK (category IN ('tip', 'announcement', 'discussion', 'motivation')),
    is_visible BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Post Reactions
CREATE TABLE post_reactions (
    id SERIAL PRIMARY KEY,
    post_id INT REFERENCES community_posts(id) ON DELETE CASCADE,
    student_id INT REFERENCES students(id) ON DELETE CASCADE,
    reaction VARCHAR(20) CHECK (reaction IN ('helpful', 'great')),
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(post_id, student_id)
);

-- Post Bookmarks
CREATE TABLE post_bookmarks (
    id SERIAL PRIMARY KEY,
    post_id INT REFERENCES community_posts(id) ON DELETE CASCADE,
    student_id INT REFERENCES students(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(post_id, student_id)
);

-- Doubts
CREATE TABLE doubts (
    id SERIAL PRIMARY KEY,
    student_id INT REFERENCES students(id) ON DELETE CASCADE,
    question_id INT REFERENCES questions(id),
    title VARCHAR(255) NOT NULL,
    body TEXT,
    is_resolved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Doubt Answers
CREATE TABLE doubt_answers (
    id SERIAL PRIMARY KEY,
    doubt_id INT REFERENCES doubts(id) ON DELETE CASCADE,
    admin_id INT REFERENCES admins(id),
    body TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Bookmarked Questions
CREATE TABLE bookmarked_questions (
    id SERIAL PRIMARY KEY,
    student_id INT REFERENCES students(id) ON DELETE CASCADE,
    question_id INT REFERENCES questions(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(student_id, question_id)
);

-- Flashcards
CREATE TABLE flashcards (
    id SERIAL PRIMARY KEY,
    subject VARCHAR(100) NOT NULL,
    chapter VARCHAR(255),
    front_text TEXT NOT NULL,
    back_text TEXT NOT NULL,
    front_image TEXT,
    back_image TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Study Materials
CREATE TABLE study_materials (
    id SERIAL PRIMARY KEY,
    subject VARCHAR(100) NOT NULL,
    chapter VARCHAR(255),
    title VARCHAR(255) NOT NULL,
    type VARCHAR(50) CHECK (type IN ('notes', 'formula_sheet', 'video')),
    file_url TEXT,
    video_url TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Notifications
CREATE TABLE notifications (
    id SERIAL PRIMARY KEY,
    student_id INT REFERENCES students(id) ON DELETE CASCADE,
    title VARCHAR(255) NOT NULL,
    body TEXT,
    type VARCHAR(50),
    is_read BOOLEAN DEFAULT FALSE,
    link TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Referrals
CREATE TABLE referrals (
    id SERIAL PRIMARY KEY,
    referrer_id INT REFERENCES students(id) ON DELETE CASCADE,
    referred_id INT REFERENCES students(id) ON DELETE CASCADE,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'completed')),
    reward_given BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Badges
CREATE TABLE badges (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    criteria TEXT, -- JSON or description of criteria
    xp_reward INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Student Badges
CREATE TABLE student_badges (
    id SERIAL PRIMARY KEY,
    student_id INT REFERENCES students(id) ON DELETE CASCADE,
    badge_id INT REFERENCES badges(id) ON DELETE CASCADE,
    earned_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(student_id, badge_id)
);

-- XP Log
CREATE TABLE xp_log (
    id SERIAL PRIMARY KEY,
    student_id INT REFERENCES students(id) ON DELETE CASCADE,
    amount INT NOT NULL,
    reason VARCHAR(255),
    source_type VARCHAR(50), -- 'test', 'streak', 'badge', 'daily_login'
    source_id INT,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Friends
CREATE TABLE friends (
    id SERIAL PRIMARY KEY,
    student_id INT REFERENCES students(id) ON DELETE CASCADE,
    friend_id INT REFERENCES students(id) ON DELETE CASCADE,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'accepted', 'rejected')),
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(student_id, friend_id)
);

-- Question Difficulty Ratings (student feedback)
CREATE TABLE question_ratings (
    id SERIAL PRIMARY KEY,
    question_id INT REFERENCES questions(id) ON DELETE CASCADE,
    student_id INT REFERENCES students(id) ON DELETE CASCADE,
    rating VARCHAR(20) CHECK (rating IN ('too_easy', 'fair', 'hard')),
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(question_id, student_id)
);

-- Question Discussions (read-only after result)
CREATE TABLE question_discussions (
    id SERIAL PRIMARY KEY,
    question_id INT REFERENCES questions(id) ON DELETE CASCADE,
    student_id INT REFERENCES students(id) ON DELETE CASCADE,
    body TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Indexes
CREATE INDEX idx_students_google_id ON students(google_id);
CREATE INDEX idx_students_college ON students(college_id);
CREATE INDEX idx_subscriptions_student ON subscriptions(student_id);
CREATE INDEX idx_subscriptions_active ON subscriptions(status, expires_at);
CREATE INDEX idx_attempts_student ON attempts(student_id);
CREATE INDEX idx_attempts_test ON attempts(test_id);
CREATE INDEX idx_attempt_answers_attempt ON attempt_answers(attempt_id);
CREATE INDEX idx_questions_test ON questions(test_id);
CREATE INDEX idx_options_question ON options(question_id);
CREATE INDEX idx_leaderboard_scope ON leaderboard(scope, scope_value);
CREATE INDEX idx_notifications_student ON notifications(student_id, is_read);
CREATE INDEX idx_xp_log_student ON xp_log(student_id);
CREATE INDEX idx_community_posts_visible ON community_posts(is_visible, created_at DESC);

-- Seed default badges
INSERT INTO badges (name, description, icon, criteria, xp_reward) VALUES
('First Test', 'Complete your first test', 'target', 'attempts_count >= 1', 50),
('7-Day Streak', 'Maintain a 7-day study streak', 'fire', 'streak >= 7', 100),
('30-Day Streak', 'Maintain a 30-day study streak', 'fire', 'streak >= 30', 500),
('Top 10 College', 'Rank in top 10 of your college', 'trophy', 'college_rank <= 10', 200),
('Perfect Score', 'Score 100% on any test', 'star', 'score_percent = 100', 300),
('Speed Demon', 'Complete rapid fire with 90%+ accuracy', 'bolt', 'rapid_fire_accuracy >= 90', 150),
('Social Butterfly', 'Add 5 friends', 'users', 'friends_count >= 5', 75),
('Bookworm', 'Attempt 50 tests', 'book', 'attempts_count >= 50', 250),
('Challenger', 'Challenge 10 friends', 'swords', 'challenges_sent >= 10', 100),
('Diamond Level', 'Reach Diamond level', 'diamond', 'level = Diamond', 1000);
