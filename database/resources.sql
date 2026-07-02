-- Resources table
CREATE TABLE resources (
    id SERIAL PRIMARY KEY,
    category VARCHAR(50) NOT NULL, -- physics, chemistry, maths, english, websites
    topic VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    url TEXT NOT NULL,
    source_label VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    position INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_resources_category ON resources(category, is_active);

-- Seed all links

-- PHYSICS
INSERT INTO resources (category, topic, title, url, source_label, position) VALUES
('physics', 'Units & Measurement', 'Khan Academy', 'https://www.khanacademy.org/science/in-in-class11th-physics', 'Khan Academy', 1),
('physics', 'Units & Measurement', 'Physics Wallah', 'https://www.youtube.com/playlist?list=PLF_7kfnwLFCEbuqbwU9hyghx5vHpaBPRo', 'YouTube', 2),
('physics', 'Units & Measurement', 'Dron Study (Hindi, underrated)', 'https://www.youtube.com/@DRONStudy', 'YouTube', 3),
('physics', 'Classical Mechanics — Newton''s Laws, Motion, Work & Energy', 'Khan Academy', 'https://www.khanacademy.org/science/in-in-class11th-physics/in-in-class11th-physics-laws-of-motion', 'Khan Academy', 4),
('physics', 'Classical Mechanics — Newton''s Laws, Motion, Work & Energy', 'Physics Wallah', 'https://www.youtube.com/@PhysicsWallah', 'YouTube', 5),
('physics', 'Classical Mechanics — Newton''s Laws, Motion, Work & Energy', 'MathonGo (crisp, underrated)', 'https://www.youtube.com/@MathonGo', 'YouTube', 6),
('physics', 'Classical Mechanics — Newton''s Laws, Motion, Work & Energy', 'LearnoHub (Hindi + English)', 'https://www.youtube.com/@LearnoHub', 'YouTube', 7),
('physics', 'Electric Current — Ohm''s Law, Capacitors, Resistors', 'Khan Academy (Class 10)', 'https://www.khanacademy.org/science/in-in-class10th-physics/in-in-electricity', 'Khan Academy', 8),
('physics', 'Electric Current — Ohm''s Law, Capacitors, Resistors', 'Khan Academy (Class 12 detailed)', 'https://www.khanacademy.org/science/in-in-class12th-physics', 'Khan Academy', 9),
('physics', 'Electric Current — Ohm''s Law, Capacitors, Resistors', 'Magnet Brains (Hindi, very clear)', 'https://www.youtube.com/@MagnetBrainsOfficial', 'YouTube', 10),
('physics', 'Heat & Thermometry', 'Khan Academy', 'https://www.khanacademy.org/science/ap-chemistry-beta/x2eef969c74e0d802:thermodynamics', 'Khan Academy', 11),
('physics', 'Heat & Thermometry', 'Physics Wallah', 'https://www.youtube.com/playlist?list=PLF_7kfnwLFCEbuqbwU9hyghx5vHpaBPRo', 'YouTube', 12),
('physics', 'Heat & Thermometry', 'Doubtnut (Q&A style)', 'https://www.youtube.com/@DoubtnutOfficial', 'YouTube', 13),
('physics', 'Wave Motion, Optics & Acoustics', 'Khan Academy Waves', 'https://www.khanacademy.org/science/in-in-class11th-physics/in-in-11th-physics-waves', 'Khan Academy', 14),
('physics', 'Wave Motion, Optics & Acoustics', 'Khan Academy Optics', 'https://www.khanacademy.org/science/in-in-class12th-physics/in-in-wave-optics', 'Khan Academy', 15),
('physics', 'Wave Motion, Optics & Acoustics', 'Motion Education (underrated, exam-focused)', 'https://www.youtube.com/@MotionEducation', 'YouTube', 16),

-- CHEMISTRY
('chemistry', 'Chemical Reactions & Equations', 'Khan Academy', 'https://www.khanacademy.org/science/in-in-class10th-chemistry/in-in-chemical-reactions-and-equations', 'Khan Academy', 17),
('chemistry', 'Chemical Reactions & Equations', 'Science with Sudhanshu (underrated)', 'https://www.youtube.com/@ScienceWithSudhanshu', 'YouTube', 18),
('chemistry', 'Chemical Reactions & Equations', 'Magnet Brains', 'https://www.youtube.com/@MagnetBrainsOfficial', 'YouTube', 19),
('chemistry', 'Acids, Bases & Salts', 'Khan Academy', 'https://www.khanacademy.org/science/in-in-class10th-chemistry/in-in-acids-bases-and-salts', 'Khan Academy', 20),
('chemistry', 'Acids, Bases & Salts', 'LearnoHub', 'https://www.youtube.com/@LearnoHub', 'YouTube', 21),
('chemistry', 'Acids, Bases & Salts', 'Dron Study (Hindi)', 'https://www.youtube.com/@DRONStudy', 'YouTube', 22),
('chemistry', 'Metals & Non-Metals', 'Khan Academy', 'https://www.khanacademy.org/science/in-in-class10th-chemistry/in-in-metals-and-non-metals', 'Khan Academy', 23),
('chemistry', 'Metals & Non-Metals', 'Doubtnut', 'https://www.youtube.com/@DoubtnutOfficial', 'YouTube', 24),
('chemistry', 'Computer Practice — Basics, HTML, MS Office', 'GCFLearn MS Office', 'https://edu.gcfglobal.org/en/topics/office/', 'GCFLearn', 25),
('chemistry', 'Computer Practice — Basics, HTML, MS Office', 'GCFLearn Computer Basics', 'https://edu.gcfglobal.org/en/computerbasics/', 'GCFLearn', 26),
('chemistry', 'Computer Practice — Basics, HTML, MS Office', 'freeCodeCamp HTML (YouTube)', 'https://www.youtube.com/watch?v=pQN-pnXPaVg', 'YouTube', 27),
('chemistry', 'Computer Practice — Basics, HTML, MS Office', 'Learn Code Online (Hindi, underrated)', 'https://www.youtube.com/@LearnCodeOnline', 'YouTube', 28),
('chemistry', 'Environmental Sciences', 'Khan Academy Ecology', 'https://www.khanacademy.org/science/ap-biology/ecology-ap', 'Khan Academy', 29),
('chemistry', 'Environmental Sciences', 'CrashCourse Ecology', 'https://www.youtube.com/watch?v=1kUE0BZtTRc', 'YouTube', 30),
('chemistry', 'Environmental Sciences', 'StudyIQ (Indian exams, underrated)', 'https://www.youtube.com/@StudyIQ', 'YouTube', 31),

-- MATHS
('maths', 'Determinants & Matrices', 'Khan Academy Matrices', 'https://www.khanacademy.org/math/precalculus/x9e81a4f98389efdf:matrices', 'Khan Academy', 32),
('maths', 'Determinants & Matrices', 'Khan Academy Determinants', 'https://www.khanacademy.org/math/linear-algebra/matrix-transformations', 'Khan Academy', 33),
('maths', 'Determinants & Matrices', 'Neeb Kaahleen (Hindi, underrated)', 'https://www.youtube.com/@NeebKaaleen', 'YouTube', 34),
('maths', 'Trigonometry', 'Khan Academy', 'https://www.khanacademy.org/math/trigonometry', 'Khan Academy', 35),
('maths', 'Trigonometry', 'MathonGo (tricks & shortcuts)', 'https://www.youtube.com/@MathonGo', 'YouTube', 36),
('maths', 'Trigonometry', 'Tutorials Point (short videos)', 'https://www.youtube.com/@TutorialsPoint', 'YouTube', 37),
('maths', 'Vectors', 'Khan Academy', 'https://www.khanacademy.org/math/linear-algebra/vectors-and-spaces', 'Khan Academy', 38),
('maths', 'Vectors', '3Blue1Brown (best visual explanation)', 'https://www.youtube.com/@3blue1brown', 'YouTube', 39),
('maths', 'Coordinate Geometry', 'Khan Academy', 'https://www.khanacademy.org/math/geometry/hs-geo-analytic-geometry', 'Khan Academy', 40),
('maths', 'Coordinate Geometry', 'AglaSem (exam pattern)', 'https://www.youtube.com/@AglaSem', 'YouTube', 41),
('maths', 'Functions & Limits', 'Khan Academy', 'https://www.khanacademy.org/math/calculus-1/cs1-limits-and-continuity', 'Khan Academy', 42),
('maths', 'Functions & Limits', 'Prof. David Joyce (underrated, simple)', 'https://www.youtube.com/@ProfDavidJJoyce', 'YouTube', 43),
('maths', 'Differentiation & Applications', 'Khan Academy', 'https://www.khanacademy.org/math/differential-calculus', 'Khan Academy', 44),
('maths', 'Differentiation & Applications', 'Organic Chemistry Tutor (very detailed)', 'https://www.youtube.com/@OrganicChemistryTutor', 'YouTube', 45),
('maths', 'Integration', 'Khan Academy', 'https://www.khanacademy.org/math/integral-calculus', 'Khan Academy', 46),
('maths', 'Integration', 'Organic Chemistry Tutor', 'https://www.youtube.com/@OrganicChemistryTutor', 'YouTube', 47),
('maths', 'Integration', 'ProfRobBob (step by step, underrated)', 'https://www.youtube.com/@ProfRobBob', 'YouTube', 48),
('maths', 'Logarithm', 'Khan Academy', 'https://www.khanacademy.org/math/algebra2/x2ec2f6f830c9fb89:logs', 'Khan Academy', 49),
('maths', 'Logarithm', 'Tutorials Point', 'https://www.youtube.com/@TutorialsPoint', 'YouTube', 50),
('maths', 'Statistics — Mean, Median, Mode', 'Khan Academy', 'https://www.khanacademy.org/math/statistics-probability/summarizing-quantitative-data', 'Khan Academy', 51),
('maths', 'Statistics — Mean, Median, Mode', 'CrashCourse Statistics (visual, fun)', 'https://www.youtube.com/@CrashCourse', 'YouTube', 52),

-- ENGLISH & SOFT SKILLS
('english', 'Comprehension — Unseen Passages', 'BBC Learning English', 'https://www.bbc.co.uk/learningenglish', 'BBC', 53),
('english', 'Comprehension — Unseen Passages', 'British Council Reading', 'https://learnenglish.britishcouncil.org/skills/reading', 'British Council', 54),
('english', 'Comprehension — Unseen Passages', 'English with Lucy (underrated)', 'https://www.youtube.com/@EnglishwithLucy', 'YouTube', 55),
('english', 'Theory of Communication', 'UW Business English playlist', 'https://www.youtube.com/playlist?list=PLxbzwyOnbv4fl5u6GJ97TqJfDsU0hfXq4', 'YouTube', 56),
('english', 'Theory of Communication', 'Communication Coach Alex Lyon (underrated)', 'https://www.youtube.com/@CommunicationCoach', 'YouTube', 57),
('english', 'Business Letter & Email Writing', 'Business letter phrases', 'https://www.youtube.com/watch?v=bCAe-NxNcps', 'YouTube', 58),
('english', 'Business Letter & Email Writing', 'Business Writing playlist', 'https://www.youtube.com/playlist?list=PL7x45KHuu46l1lMErNTx6gkTRMt48oRLV', 'YouTube', 59),
('english', 'Business Letter & Email Writing', 'BBC English at Work', 'https://www.bbc.co.uk/learningenglish/english/features/english-at-work', 'BBC', 60),
('english', 'Grammar — Parts of Speech, Tenses, Corrections', 'Khan Academy Grammar', 'https://www.khanacademy.org/humanities/grammar', 'Khan Academy', 61),
('english', 'Grammar — Parts of Speech, Tenses, Corrections', 'BBC Grammar', 'https://www.bbc.co.uk/learningenglish/english/features/lower-intermediate', 'BBC', 62),
('english', 'Grammar — Parts of Speech, Tenses, Corrections', 'engVid (underrated, multiple teachers)', 'https://www.youtube.com/@EngVidEnglish', 'YouTube', 63),

-- WEBSITES
('websites', 'Top Free Websites', 'Khan Academy (Maths + Science + Grammar)', 'https://www.khanacademy.org', 'Khan Academy', 64),
('websites', 'Top Free Websites', 'NCERT Free Textbook PDFs (Class 10, 11, 12)', 'https://ncert.nic.in/textbook.php', 'NCERT', 65),
('websites', 'Top Free Websites', 'GTU Video Lectures (Diploma subjects)', 'https://lectures.gtu.ac.in', 'GTU', 66),
('websites', 'Top Free Websites', 'GCFLearn (MS Office, Computer basics)', 'https://edu.gcfglobal.org', 'GCFLearn', 67),
('websites', 'Top Free Websites', 'British Council (Reading, Grammar, Writing)', 'https://learnenglish.britishcouncil.org', 'British Council', 68),
('websites', 'Top Free Websites', 'BBC Learning English (Communication, Grammar)', 'https://www.bbc.co.uk/learningenglish', 'BBC', 69),
('websites', 'Top Free Websites', 'freeCodeCamp (HTML, coding basics)', 'https://www.freecodecamp.org', 'freeCodeCamp', 70);
