<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'path'     => '/',
    ]);
    session_start();
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function normalizeRole(?string $role): string
{
    return strtolower(trim((string)$role));
}

function dashboardFor(string $role): string
{
    switch (normalizeRole($role)) {
        case 'admin':
            return BASE_URL . 'admin/dashboard.php';
        case 'owner':
            return BASE_URL . 'owner/dashboard.php';   // change if owner uses the admin dashboard
        default:
            return BASE_URL . 'staff/dashboard.php';
    }
}

/**
 * Allow one role or several: requireRole('admin') or requireRole(['admin', 'owner'])
 */
function requireRole($roles): array
{
    $user = currentUser();
    if (!$user) {
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }

    $allowed = array_map('normalizeRole', (array)$roles);
    $current = normalizeRole($user['role'] ?? '');

    if (!in_array($current, $allowed, true)) {
        $target = dashboardFor($current);

        // Stop a redirect loop if the user's own dashboard is the page they're already on
        if (rtrim(parse_url($target, PHP_URL_PATH), '/') === rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/')) {
            http_response_code(403);
            exit('Access denied for role: ' . htmlspecialchars($current, ENT_QUOTES, 'UTF-8'));
        }

        header('Location: ' . $target);
        exit;
    }

    return $user;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}