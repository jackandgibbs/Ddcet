<?php
require_once __DIR__ . '/../config.php';
// BUG-003 fix: properly destroy the session. The old code cleared $_SESSION
// but never called session_destroy(), so the session cookie stayed valid and
// the back-button could restore the authenticated state.

// 1. Delete the session cookie so the browser stops sending it.
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

// 2. Clear all session data and destroy the server-side session file.
$_SESSION = [];
session_destroy();

// 3. Start a fresh session just for the "logged out" flash message.
session_start();
session_regenerate_id(true);
$_SESSION['show_logout_success'] = true;
session_write_close();
?>
<!DOCTYPE html>
<html>
<head>
<script>
// Replace current history entry so back-button can't return to an auth'd page.
window.location.replace('<?= BASE_PATH ?>auth/login.php');
</script>
</head>
</html>
<?php exit;

