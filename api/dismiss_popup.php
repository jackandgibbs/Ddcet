<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

requireAuth();
requireCsrf();

if (isset($_SESSION['show_subscription_popup'])) {
    $_SESSION['show_subscription_popup'] = false;
}
echo json_encode(['success' => true]);
