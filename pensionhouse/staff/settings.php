<?php
require_once __DIR__ . '/../views.php';
$user = requireStaffOrAdmin();
if ($user['role'] !== 'staff') {
    header('Location: ' . BASE_URL . 'admin/settings.php');
    exit;
}
require_once __DIR__ . '/../settings_page.php';
settingsPage($user);