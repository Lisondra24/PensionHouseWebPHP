<?php
require_once __DIR__ . '/layout.php';
$user = requireStaffOrAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $id  = (int)($_POST['id'] ?? 0);
    $res = $id > 0
        ? apiAuth("notifications/$id/read", 'PATCH')
        : apiAuth('notifications/read-all', 'PATCH');
    echo json_encode(['ok' => apiOk($res)]);
    exit;
}

$res = apiAuth('notifications');
echo json_encode(
    apiOk($res) && is_array($res['data']) ? $res['data'] : ['unread' => 0, 'items' => []]
);