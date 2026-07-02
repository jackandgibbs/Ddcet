<?php
require_once __DIR__ . '/../config.php';
session_start();
$_SESSION = [];
$_SESSION['show_logout_success'] = true;
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_write_close();
session_start();
?>
<!DOCTYPE html>
<html>
<head>
<script>
window.location.replace('<?= BASE_PATH ?>auth/login.php');
history.pushState(null, null, location.href);
window.onpopstate = function() {
    history.go(1);
};
</script>
</head>
</html>
<?php exit;
