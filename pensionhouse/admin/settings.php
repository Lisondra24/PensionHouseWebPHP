<?php
require_once __DIR__ . '/../views.php';
$user = requireStaffOrAdmin();
if ($user['role'] !== 'admin') {
    header('Location: ' . BASE_URL . 'staff/settings.php');
    exit;
}
require_once __DIR__ . '/../settings_page.php';
settingsPage($user);