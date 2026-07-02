<?php
session_start();
if (isset($_SESSION['show_subscription_popup'])) {
    $_SESSION['show_subscription_popup'] = false;
}
echo json_encode(['success' => true]);
