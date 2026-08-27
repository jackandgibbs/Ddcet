-- Missing performance indexes
CREATE INDEX IF NOT EXISTS idx_attempts_student_mode_status ON attempts(student_id, mode, status);
CREATE INDEX IF NOT EXISTS idx_attempts_student_mode_started ON attempts(student_id, mode, started_at);
CREATE INDEX IF NOT EXISTS idx_attempt_answers_attempt_question ON attempt_answers(attempt_id, question_id);
CREATE INDEX IF NOT EXISTS idx_questions_testid_subject ON questions(test_id, subject);
CREATE INDEX IF NOT EXISTS idx_challenges_status_players ON challenges(status, challenger_id, opponent_id);
CREATE INDEX IF NOT EXISTS idx_payments_order_student ON payments(razorpay_order_id, student_id);
CREATE INDEX IF NOT EXISTS idx_friends_pair ON friends(student_id, friend_id, status);

-- Prevent duplicate in-progress attempts
CREATE UNIQUE INDEX IF NOT EXISTS idx_unique_inprogress_attempt
ON attempts(student_id, COALESCE(test_id, 0), mode)
WHERE status = 'in_progress';
