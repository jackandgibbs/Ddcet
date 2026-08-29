<?php
require_once __DIR__ . '/config.php';
$user = requireAuth();
$pageTitle = 'Contribute';

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $type = $_POST['type'] ?? '';
    
    if (in_array($type, ['question', 'note', 'error'])) {
        $content = [];
        
        if ($type === 'question') {
            $content = [
                'subject' => $_POST['subject'] ?? '',
                'chapter' => $_POST['chapter'] ?? '',
                'question_text' => $_POST['question_text'] ?? '',
                'option_a' => $_POST['option_a'] ?? '',
                'option_b' => $_POST['option_b'] ?? '',
                'option_c' => $_POST['option_c'] ?? '',
                'option_d' => $_POST['option_d'] ?? '',
                'correct_option' => $_POST['correct_option'] ?? '1',
                'explanation' => $_POST['explanation'] ?? ''
            ];
            // Basic validation
            if (empty($content['question_text']) || empty($content['option_a'])) {
                $err = 'Please fill out the question and options.';
            }
        } elseif ($type === 'note') {
            $content = [
                'subject' => $_POST['note_subject'] ?? '',
                'chapter' => $_POST['note_chapter'] ?? '',
                'title' => $_POST['note_title'] ?? '',
                'link' => $_POST['note_link'] ?? ''
            ];
            if (empty($content['title']) || empty($content['link'])) {
                $err = 'Please provide a title and a valid link.';
            }
        } elseif ($type === 'error') {
            $content = [
                'test_title' => $_POST['test_title'] ?? '',
                'question_snippet' => $_POST['question_snippet'] ?? '',
                'description' => $_POST['error_description'] ?? ''
            ];
            if (empty($content['description'])) {
                $err = 'Please describe the error.';
            }
        }

        if (!$err) {
            $inserted = supabaseRest('contributions', 'POST', [
                'student_id' => $user['id'],
                'type' => $type,
                'content' => $content,
                'status' => 'pending'
            ]);

            if ($inserted !== null) {
                $msg = 'Thank you! Your contribution has been submitted for review. You will earn XP once it is approved!';
            } else {
                $err = 'Failed to submit contribution. Please try again later.';
            }
        }
    } else {
        $err = 'Invalid contribution type.';
    }
}

include __DIR__ . '/includes/header.php';
?>
<div class="page-header" style="text-align: center; margin-bottom: 40px;">
    <h1 style="font-size: 32px; letter-spacing: -1px; margin-bottom: 12px; font-weight: 800;">
        Contribute & Earn XP
    </h1>
    <p style="color: var(--text-muted); font-size: 16px; max-width: 600px; margin: 0 auto; line-height: 1.5;">
        Help the community grow by submitting challenging questions, sharing your best short notes, or catching typos in our test series. 
        Approved contributions earn XP and a spot on our <a href="contributors.php" class="accent" style="text-decoration: underline; font-weight: 600;">Hall of Fame</a>!
    </p>
</div>

<?php if ($msg): ?>
<div class="card" style="border-color: var(--green); color: var(--green); text-align: center; margin-bottom: 24px; padding: 16px; font-weight: 500;">
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>
<?php if ($err): ?>
<div class="card" style="border-color: var(--red); color: var(--red); text-align: center; margin-bottom: 24px; padding: 16px; font-weight: 500;">
    <?= htmlspecialchars($err) ?>
</div>
<?php endif; ?>

<div style="max-width: 700px; margin: 0 auto;">
    <div class="card" style="padding: 30px;">
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
            
            <div class="form-group">
                <label style="font-size: 15px; font-weight: 600; margin-bottom: 12px;">What would you like to contribute?</label>
                <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                    <label class="btn btn-secondary" style="flex: 1; text-align: center; cursor: pointer;">
                        <input type="radio" name="type" value="question" checked onchange="toggleForms()" style="display: none;">
                         Question
                    </label>
                    <label class="btn btn-secondary" style="flex: 1; text-align: center; cursor: pointer;">
                        <input type="radio" name="type" value="note" onchange="toggleForms()" style="display: none;">
                         Study Notes
                    </label>
                    <label class="btn btn-secondary" style="flex: 1; text-align: center; cursor: pointer;">
                        <input type="radio" name="type" value="error" onchange="toggleForms()" style="display: none;">
                         Report Error
                    </label>
                </div>
            </div>

            <div id="form-question" style="margin-top: 30px;">
                <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 1; min-width: 200px;">
                        <label>Subject</label>
                        <select name="subject" class="form-control">
                            <option value="Physics">Physics</option>
                            <option value="Chemistry">Chemistry</option>
                            <option value="Mathematics">Mathematics</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1; min-width: 200px;">
                        <label>Chapter (Optional)</label>
                        <input type="text" name="chapter" class="form-control" placeholder="e.g. Kinematics">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Question Text</label>
                    <textarea name="question_text" class="form-control" rows="3" placeholder="Type the question here..." required></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group"><label>Option A</label><input type="text" name="option_a" class="form-control" required></div>
                    <div class="form-group"><label>Option B</label><input type="text" name="option_b" class="form-control" required></div>
                    <div class="form-group"><label>Option C</label><input type="text" name="option_c" class="form-control" required></div>
                    <div class="form-group"><label>Option D</label><input type="text" name="option_d" class="form-control" required></div>
                </div>

                <div class="form-group">
                    <label>Correct Option</label>
                    <select name="correct_option" class="form-control">
                        <option value="1">Option A</option>
                        <option value="2">Option B</option>
                        <option value="3">Option C</option>
                        <option value="4">Option D</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Explanation (Optional)</label>
                    <textarea name="explanation" class="form-control" rows="2" placeholder="Explain the correct answer..."></textarea>
                </div>
            </div>

            <div id="form-note" style="display: none; margin-top: 30px;">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="note_title" class="form-control" placeholder="e.g. Complete Organic Chemistry Cheat Sheet">
                </div>
                <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 1; min-width: 200px;">
                        <label>Subject</label>
                        <select name="note_subject" class="form-control">
                            <option value="Physics">Physics</option>
                            <option value="Chemistry">Chemistry</option>
                            <option value="Mathematics">Mathematics</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1; min-width: 200px;">
                        <label>Chapter (Optional)</label>
                        <input type="text" name="note_chapter" class="form-control" placeholder="e.g. Amines">
                    </div>
                </div>
                <div class="form-group">
                    <label>Link to Notes (Google Drive, Notion, etc.)</label>
                    <input type="url" name="note_link" class="form-control" placeholder="https://...">
                    <small style="color: var(--text-muted); display: block; margin-top: 4px;">Make sure the link is set to "Anyone with the link can view".</small>
                </div>
            </div>

            <div id="form-error" style="display: none; margin-top: 30px;">
                <div class="form-group">
                    <label>Test Name (Optional)</label>
                    <input type="text" name="test_title" class="form-control" placeholder="e.g. Mock Test 4">
                </div>
                <div class="form-group">
                    <label>Question Snippet (To help us identify it)</label>
                    <input type="text" name="question_snippet" class="form-control" placeholder="e.g. 'A block of mass 2kg...'">
                </div>
                <div class="form-group">
                    <label>Description of Error</label>
                    <textarea name="error_description" class="form-control" rows="4" placeholder="e.g. The correct answer should be C because..."></textarea>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 16px; padding: 14px; font-size: 16px; font-weight: 700;">Submit Contribution</button>
        </form>
    </div>
</div>

<script>
function toggleForms() {
    const type = document.querySelector('input[name="type"]:checked').value;
    document.getElementById('form-question').style.display = type === 'question' ? 'block' : 'none';
    document.getElementById('form-note').style.display = type === 'note' ? 'block' : 'none';
    document.getElementById('form-error').style.display = type === 'error' ? 'block' : 'none';
    
    // Update active styles on radio buttons
    document.querySelectorAll('input[name="type"]').forEach(radio => {
        if (radio.checked) {
            radio.parentElement.classList.remove('btn-secondary');
            radio.parentElement.classList.add('btn-primary');
        } else {
            radio.parentElement.classList.remove('btn-primary');
            radio.parentElement.classList.add('btn-secondary');
        }
    });
    
    // Reset required attributes dynamically based on visible form
    const inputs = document.querySelectorAll('input, textarea, select');
    inputs.forEach(input => {
        // Skip radio buttons and CSRF
        if (input.type === 'radio' || input.type === 'hidden') return;
        
        // Remove required from everything inside the form sections
        if (input.closest('#form-question') || input.closest('#form-note') || input.closest('#form-error')) {
            input.removeAttribute('required');
        }
    });
    
    if (type === 'question') {
        document.querySelector('textarea[name="question_text"]').setAttribute('required', 'true');
        document.querySelector('input[name="option_a"]').setAttribute('required', 'true');
        document.querySelector('input[name="option_b"]').setAttribute('required', 'true');
        document.querySelector('input[name="option_c"]').setAttribute('required', 'true');
        document.querySelector('input[name="option_d"]').setAttribute('required', 'true');
    } else if (type === 'note') {
        document.querySelector('input[name="note_title"]').setAttribute('required', 'true');
        document.querySelector('input[name="note_link"]').setAttribute('required', 'true');
    } else if (type === 'error') {
        document.querySelector('textarea[name="error_description"]').setAttribute('required', 'true');
    }
}

// Init styles
toggleForms();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
