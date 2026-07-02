<?php
/**
 * Blog post store. Each post is a self-contained array. `body` is trusted HTML
 * authored here (not user input), so it is printed as-is. Newest first.
 *
 * To add an article: copy a block, give it a unique `slug`, and prepend it.
 * Keep `excerpt` ~150 chars for cards + meta description.
 */

function blogPosts(): array {
    return [
        [
            'slug'     => 'ddcet-exam-pattern-explained',
            'title'    => 'DDCET Exam Pattern Explained: Marks, Subjects & Time',
            'excerpt'  => 'A clear breakdown of the DDCET exam pattern — how many questions, the subject-wise split, marking scheme and the time you get. Know exactly what you are walking into.',
            'category' => 'Exam Guide',
            'author'   => 'Abdullah Kapadia',
            'date'     => '2026-06-10',
            'read'     => 6,
            'body'     => <<<'HTML'
<p>Before you write a single mock, you need a crystal-clear picture of what the DDCET actually tests. Walking in blind is the most common — and most avoidable — mistake diploma students make.</p>

<h2>The big picture</h2>
<p>DDCET (Diploma to Degree Common Entrance Test) is a single objective paper of <strong>100 questions for 200 marks</strong>. Every question carries 2 marks, and you get <strong>150 minutes</strong> to finish. That works out to about <strong>90 seconds per question</strong> — comfortable if you've practised, brutal if you haven't.</p>

<h2>Subject-wise distribution</h2>
<p>The paper is split across six areas. The exact weightage we build our full mocks around is:</p>
<ul>
  <li><strong>Physics — 30 questions</strong> (the single largest block)</li>
  <li><strong>Mathematics — 25 questions</strong></li>
  <li><strong>English — 20 questions</strong></li>
  <li><strong>Chemistry — 15 questions</strong></li>
  <li><strong>Computers — 5 questions</strong></li>
  <li><strong>Environment — 5 questions</strong></li>
</ul>
<p>Notice that Physics and Maths alone are <strong>55% of the paper</strong>. If your fundamentals there are shaky, that's where your prep time should go first.</p>

<h2>Marking scheme</h2>
<p>Each correct answer is +2 marks. Confirm the current year's negative-marking rule on the official notification before exam day — but as a habit, treat every question as worth attempting once you've eliminated at least two options.</p>

<h2>What this means for your prep</h2>
<p>Practise full 100-question papers under the real 150-minute clock at least once a week. Speed and stamina are skills you build, not things you're born with. Our full mock follows this exact blueprint, so the real exam feels like just another practice run.</p>
HTML,
        ],
        [
            'slug'     => '8-week-ddcet-study-plan',
            'title'    => 'The 8-Week DDCET Study Plan That Actually Works',
            'excerpt'  => 'A realistic, week-by-week DDCET preparation timeline — from building fundamentals to full-length mocks and final revision. Built for students balancing diploma classes.',
            'category' => 'Study Plan',
            'author'   => 'Abdullah Kapadia',
            'date'     => '2026-06-06',
            'read'     => 8,
            'body'     => <<<'HTML'
<p>"Where do I even start?" is the question we hear most. Here is a no-nonsense 8-week plan you can start today — designed for students still attending diploma classes, not full-time aspirants.</p>

<h2>Weeks 1–2: Fundamentals</h2>
<p>Pick your two weakest subjects (for most people that's Physics and Maths) and rebuild the basics. Don't touch mocks yet. Use topic-wise tests to find your blind spots, then watch one focused video per weak topic.</p>

<h2>Weeks 3–4: Subject mastery</h2>
<p>Now go subject by subject. Aim for one subject-wise test every day, and bookmark every question you get wrong. The bookmark bank you build here becomes your revision gold mine later.</p>

<h2>Weeks 5–6: Mixed practice + speed</h2>
<p>Start the Rapid Fire mode to train decision speed under pressure, and begin attempting half-length mixed papers. This is where you stop studying subjects in isolation and start thinking like the exam does.</p>

<h2>Week 7: Full mocks</h2>
<p>Write at least three full 100-question mocks under the real 150-minute clock. After each one, spend twice as long reviewing as you spent writing. The review is where the marks come from.</p>

<h2>Week 8: Targeted revision</h2>
<p>No new topics. Re-attempt only your wrong-question bank, run through flashcards for formulas, and take one final confidence mock two days before the exam. Rest the day before — a tired brain forgets.</p>

<h2>The one rule</h2>
<p>Consistency beats intensity. A focused hour every single day will take you further than a frantic ten-hour Sunday. Keep your streak alive with the free Daily Challenge and let momentum do the heavy lifting.</p>
HTML,
        ],
        [
            'slug'     => 'how-to-use-mock-tests',
            'title'    => 'How to Actually Use Mock Tests to Boost Your Score',
            'excerpt'  => 'Most students take mocks wrong. Here is how to turn every mock test into a measurable score jump — the review process, error log, and percentile tracking that matters.',
            'category' => 'Strategy',
            'author'   => 'Taher Bootwala',
            'date'     => '2026-06-02',
            'read'     => 5,
            'body'     => <<<'HTML'
<p>Taking 50 mocks won't help if you take them the wrong way. A mock test is not the practice — it's the <em>diagnosis</em>. The practice is what you do afterwards.</p>

<h2>1. Simulate the real thing</h2>
<p>One sitting, full 150 minutes, phone in another room. If you pause, snack, and check messages mid-mock, your score is fiction. Train under exam conditions so the real exam holds no surprises.</p>

<h2>2. Review every single question — including the ones you got right</h2>
<p>Got it right by guessing? That's a future mistake waiting to happen. Mark it. The goal is to know <em>why</em> the answer is correct, not just that it is.</p>

<h2>3. Keep an error log</h2>
<p>For every wrong answer, write one line: was it a concept gap, a silly mistake, or a time crunch? After three mocks a pattern emerges — and that pattern is your personalised study plan. Our Smart Analytics and wrong-question revision do this tracking for you automatically.</p>

<h2>4. Track your percentile, not just your marks</h2>
<p>A 120/200 means nothing in isolation. A 120 that puts you in the 85th percentile means everything. Watch your rank trend across mocks — that line going up is the only proof your prep is working.</p>

<h2>5. Space them out</h2>
<p>Don't binge five mocks in a weekend. One quality mock plus a thorough review every few days lets the lessons actually sink in before the next attempt. Quality of review &gt; quantity of mocks, every time.</p>
HTML,
        ],
    ];
}

/** Find a single post by slug, or null. */
function blogPost(string $slug): ?array {
    foreach (blogPosts() as $p) {
        if ($p['slug'] === $slug) return $p;
    }
    return null;
}
