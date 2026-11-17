<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/api.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function peso($n): string { return '₱' . number_format((float)$n, 2); }
function fmtDate(?string $s): string { return $s ? date('M d, Y h:i A', strtotime($s)) : '-'; }
function fmtShort(?string $s): string { return $s ? date('M d, h:i A', strtotime($s)) : '-'; }
function fmtDay(?string $s): string { return $s ? date('M d', strtotime($s)) : '-'; }
function flash(string $type, string $msg): void { $_SESSION['flash'] = ['type' => $type, 'msg' => $msg]; }
function apiOk(array $res): bool { return $res['status'] >= 200 && $res['status'] < 300; }

function apiMessage(array $res, string $default): string
{
    return $res['data']['message'] ?? $res['data']['title'] ?? $default;
}

function requireStaffOrAdmin(): array
{
    $user = currentUser();
    if (!$user || !in_array($user['role'], ['staff', 'admin'], true)) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
    return $user;
}

// Calls the API with the logged-in user's token. Expired token -> back to login.
function apiAuth(string $path, string $method = 'GET', ?array $data = null): array
{
    $user = currentUser();
    $res = callApi($path, $method, $data, $user['token'] ?? null);

    if ($res['status'] === 401) {
        $_SESSION = [];
        session_destroy();
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
    return $res;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrfToken()) . '">';
}

function checkCsrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        exit('Session expired. Go back and try again.');
    }
}

function badge(string $status): string
{
    $cls = [
        'CONFIRMED' => 'p-ok', 'APPROVED' => 'p-ok', 'COMPLETED' => 'p-ok',
        'CHECKED_IN' => 'p-info',
        'PENDING' => 'p-warn', 'REJECTED' => 'p-warn',
        'CANCELLED' => 'p-off',
    ];
    return '<span class="pill ' . ($cls[$status] ?? 'p-off') . '">'
         . e(ucfirst(strtolower(str_replace('_', ' ', $status)))) . '</span>';
}

function avatar(string $name, int $size = 34): string
{
    $colors = ['#c0392b', '#8e44ad', '#2c7a7b', '#b7791f', '#2b6cb0'];
    $c = $colors[crc32($name) % count($colors)];
    return '<span class="av" style="background:' . $c . ';width:' . $size . 'px;height:' . $size . 'px">'
         . e(strtoupper(mb_substr(trim($name) ?: '?', 0, 1))) . '</span>';
}

// Five stars, filled in yellow up to the rounded rating.
function stars(float $rating, string $size = '1em'): string
{
    $full = (int)round($rating);
    $out = '<span class="stars" role="img" aria-label="' . $full . ' out of 5 stars" style="font-size:' . e($size) . '">';
    for ($i = 1; $i <= 5; $i++) {
        $out .= '<i class="' . ($i <= $full ? 'on' : '') . '">★</i>';
    }
    return $out . '</span>';
}

function searchIcon(): string
{
    return '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6b7f7a" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>';
}

// Icons (inline SVG, drawn with currentColor so they follow the text color).
function navIcon(string $key, int $size = 18): string
{
    static $paths = [
        'dashboard.php' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/>',
        'bookings.php'  => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'rooms.php'     => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'guests.php'    => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'payments.php'  => '<rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
        'walkin.php'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>',
        'reviews.php'   => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        'settings.php'  => '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>',
        'logout'        => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'menu'          => '<line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>',
        'plus'          => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'check'         => '<polyline points="20 6 9 17 4 12"/>',
        'x'             => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'edit'          => '<path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/>',
        'trash'         => '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/>',
        'tool'          => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
        'eye'           => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'inbox'         => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        'search'        => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'clock'         => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'power'         => '<path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/>',
        'login'         => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>',
        'copy'          => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
    ];
    $paths['walkins.php'] = $paths['walkin.php'];

    return '<svg class="ico" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" '
         . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$key] ?? '') . '</svg>';
}

// ---------- Shared UI pieces ----------

function emptyBlock(string $icon, string $title, string $text = ''): string
{
    return '<div class="empty"><span class="ei">' . navIcon($icon, 24) . '</span><b>' . e($title) . '</b>'
         . ($text !== '' ? '<p>' . e($text) . '</p>' : '') . '</div>';
}

function emptyRow(int $cols, string $icon, string $title, string $text = ''): string
{
    return '<tr class="empty-row"><td colspan="' . $cols . '">' . emptyBlock($icon, $title, $text) . '</td></tr>';
}

// Shown by the live search/filter when nothing matches.
function noMatchRow(int $cols): string
{
    return '<tr class="empty-row nomatch" hidden><td colspan="' . $cols . '">'
         . emptyBlock('search', 'No matches', 'Try a different search or filter.') . '</td></tr>';
}

function noMatchBlock(): string
{
    return '<div class="nomatch" hidden>' . emptyBlock('search', 'No matches', 'Try a different search or filter.') . '</div>';
}

function statCard(string $label, $value, string $icon, string $tone = 'g'): string
{
    return '<div class="stat2"><span class="si ' . e($tone) . '">' . navIcon($icon, 20) . '</span>'
         . '<div><small>' . e($label) . '</small><b>' . e((string)$value) . '</b></div></div>';
}

// Server-side filter tabs (links). $counts = [key => number] shows a small count badge.
function tabsHtml(array $tabs, string $cur, array $counts = [], string $param = 'status'): string
{
    $out = '<nav class="tabs" aria-label="Filter">';
    foreach ($tabs as $key => $label) {
        $key = (string)$key;
        $n = isset($counts[$key]) ? '<em>' . (int)$counts[$key] . '</em>' : '';
        $out .= '<a class="' . ($cur === $key ? 'on' : '') . '" href="?' . e($param) . '=' . e(urlencode($key)) . '">'
              . e($label) . $n . '</a>';
    }
    return $out . '</nav>';
}

// Browser-side filter chips. $target is a CSS selector of the container that holds [data-text] items.
function chipsHtml(array $chips, string $target, array $counts = []): string
{
    $out = '<div class="tabs" role="group" aria-label="Filter">';
    $first = true;
    foreach ($chips as $key => $label) {
        $n = isset($counts[$key]) ? '<em>' . (int)$counts[$key] . '</em>' : '';
        $out .= '<a href="#" class="' . ($first ? 'on' : '') . '" data-chip="' . e($key) . '" data-chip-for="' . e($target) . '">'
              . e($label) . $n . '</a>';
        $first = false;
    }
    return $out . '</div>';
}

// Put on a <form> to ask for confirmation in a dialog before it submits.
function confirmAttr(string $msg, string $title = 'Please confirm', string $ok = 'Confirm', string $tone = ''): string
{
    return ' data-confirm="' . e($msg) . '" data-confirm-title="' . e($title)
         . '" data-confirm-ok="' . e($ok) . '" data-confirm-tone="' . e($tone) . '"';
}

function modalStart(string $id, string $title, string $size = ''): void
{
    echo '<dialog class="modal ' . e($size) . '" id="' . e($id) . '" aria-labelledby="' . e($id) . '-t">'
       . '<div class="m-head"><b id="' . e($id) . '-t">' . e($title) . '</b>'
       . '<button type="button" class="x" data-close aria-label="Close">' . navIcon('x', 18) . '</button></div>'
       . '<div class="m-body">';
}

function modalEnd(): void
{
    echo '</div></dialog>';
}

// Prefer the role's own folder (admin/ or staff/); fall back to manage/ until a page is moved.
function navUrl(string $role, string $page): ?string
{
    if (file_exists(__DIR__ . "/$role/$page"))  return BASE_URL . "$role/$page";
    if (file_exists(__DIR__ . "/manage/$page")) return BASE_URL . "manage/$page";
    return null;
}

function newBookingBtn(string $role): string
{
    if ($role !== 'staff') return '';   // only staff can add walk-ins

    $u = navUrl('staff', 'walkin.php');
    return $u ? '<a class="btn coral big" href="' . e($u) . '">+ Walk-in</a>' : '';
}

// Tiny line chart. $v = list of numbers.
function spark(array $v, string $color): string
{
    $w = 90; $h = 28; $n = count($v);
    $max = max($v) ?: 1;
    $pts = [];
    foreach (array_values($v) as $i => $y) {
        $pts[] = round($i * $w / max($n - 1, 1), 1) . ',' . round($h - 2 - ($y / $max) * ($h - 6), 1);
    }
    return '<svg class="spark" viewBox="0 0 90 28" preserveAspectRatio="none"><polyline points="' . implode(' ', $pts)
         . '" fill="none" stroke="' . $color . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

// Counts (or sums) rows per day for the given list of Y-m-d keys.
function perDay(array $rows, callable $dateOf, array $days, ?callable $valOf = null): array
{
    $out = array_fill_keys($days, 0);
    foreach ($rows as $r) {
        $d = substr((string)$dateOf($r), 0, 10);
        if (isset($out[$d])) $out[$d] += $valOf ? $valOf($r) : 1;
    }
    return array_values($out);
}

/**
 * $opt['bg']      = 'cream' for the cream Overview background (default white)
 * $opt['action']  = raw HTML shown at the top right (e.g. the New Booking button)
 * $opt['subHtml'] = raw HTML for the line under the title (use only with trusted/escaped content)
 */
function pageStart(string $title, array $user, string $sub = '', array $opt = []): void
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    $role = $user['role'];
    $isAdmin = $role === 'admin';
    $cur  = basename($_SERVER['SCRIPT_NAME']);
    if ($cur === 'soon.php') {
        $cur = preg_replace('/[^a-z]/', '', strtolower($_GET['page'] ?? '')) . '.php';
    }

    // Sidebar menu, grouped under small section labels
    $groups = [
    'Operations' => [
        'dashboard.php' => 'Overview',
        'bookings.php'  => 'Bookings',
    ],
    'Property' => [
        'rooms.php'  => 'Rooms',
        'guests.php' => 'Guests',
    ],
];
if (!$isAdmin) {
    // Walk-in sits between Bookings and Payments, for staff only
    $groups['Operations'] = [
        'dashboard.php' => 'Overview',
        'bookings.php'  => 'Bookings',
        'walkin.php'    => 'Walk-in',
        'payments.php'  => 'Payments',
    ];
} else {
    $groups['Operations']['payments.php'] = 'Payments';
    $groups['Property']['reviews.php'] = 'Reviews';
}
$groups['System'] = ['settings.php' => 'Settings'];
    if ($isAdmin) {
        $groups['Property']['reviews.php'] = 'Reviews';
    }
    $groups['System'] = ['settings.php' => 'Settings'];

    $menu = [];
    foreach ($groups as $label => $pages) {
        foreach ($pages as $page => $text) {
            $url = match ($page) {
                'dashboard.php' => dashboardFor($role),
                default         => navUrl($role, $page),
            };
            if ($url) $menu[$label][] = ['page' => $page, 'text' => $text, 'url' => $url];
        }
    }

    $cream = ($opt['bg'] ?? '') === 'cream';
    $img = e(BASE_URL) . 'assets/img/building.jpg';
    $roleLabel = $isAdmin ? 'Administrator' : 'Staff Member';
    $panelLabel = $isAdmin ? 'Admin Panel' : 'Staff Panel';
    $initial = e(strtoupper(mb_substr(trim($user['fullName']) ?: '?', 0, 1)));

    // Where each notification type should take the user
    $notifUrls = [
        'BOOKING' => navUrl($role, 'bookings.php') ?? '#',
        'PAYMENT' => navUrl($role, 'payments.php') ?? '#',
        'REVIEW'  => navUrl($role, 'reviews.php') ?? '#',
    ];
    $jf = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> - Pension House</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="icon" type="image/x-icon" href="<?= e(BASE_URL) ?>assets/favicon.ico">
  <script src="<?= e(BASE_URL) ?>assets/dark-mode.js?v=<?= filemtime(__DIR__ . '/assets/dark-mode.js') ?>" data-scope="admin"></script>
</head>
  <style>
    *{box-sizing:border-box}
    [hidden]{display:none!important}
    :root{--g:#123f3a;--cream:#faf6e8;--line:#cfc8b0;--coral:#e8583f;--ink:#0f3d3a;--soft:#5f7772;--edge:#8fa39e}
    body{margin:0;font-family:Poppins,system-ui,sans-serif;background:#fff;color:var(--ink);font-size:14px}
    .app{display:flex;min-height:100vh}

    /* ---------- Sidebar ---------- */
    aside{width:252px;flex-shrink:0;background:var(--g);color:#fff;padding:1.1rem .9rem 1rem;display:flex;flex-direction:column;position:sticky;top:0;height:100vh;height:100dvh;overflow-y:auto}
    .brand{display:flex;align-items:center;gap:.75rem;padding:.2rem .35rem 1.1rem;margin-bottom:.4rem;border-bottom:1px solid rgba(255,255,255,.14)}
    .brand .pic{width:42px;height:42px;border-radius:12px;background-size:cover;background-position:center;background-color:#2c5750;flex-shrink:0;box-shadow:0 0 0 2px rgba(255,255,255,.18)}
    .brand b{display:block;font-weight:600;font-size:.9rem;line-height:1.2}
    .brand small{display:block;font-size:.65rem;color:rgba(255,255,255,.55);letter-spacing:.04em;margin-top:2px}

    .side-nav{flex:1}
    .nav-label{margin:1.15rem 0 .4rem;padding:0 .8rem;font-size:.62rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.42)}
    .side-nav a{position:relative;display:flex;align-items:center;gap:.75rem;padding:.62rem .8rem;margin-bottom:2px;border-radius:10px;color:rgba(255,255,255,.76);text-decoration:none;font-size:.82rem;font-weight:500;transition:background .15s,color .15s}
    .side-nav a .ico{flex-shrink:0;opacity:.85}
    .side-nav a:hover{background:rgba(255,255,255,.08);color:#fff}
    .side-nav a:hover .ico{opacity:1}
    .side-nav a.on{background:rgba(255,255,255,.14);color:#fff}
    .side-nav a.on .ico{opacity:1;color:#fff}
    .side-nav a.on::before{content:"";position:absolute;left:-.9rem;top:20%;bottom:20%;width:4px;border-radius:0 4px 4px 0;background:var(--coral)}
    .side-nav a:focus-visible,.side-out:focus-visible,.hamb:focus-visible{outline:2px solid var(--coral);outline-offset:2px}

    .side-user{margin-top:1rem;padding:.7rem;border-radius:14px;background:rgba(255,255,255,.07);display:flex;align-items:center;gap:.7rem}
    .side-user .who2{flex:1;min-width:0;line-height:1.25}
    .side-user .who2 b{display:block;font-size:.8rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .side-user .who2 small{display:block;font-size:.65rem;color:rgba(255,255,255,.58)}
    .side-out{width:34px;height:34px;flex-shrink:0;border-radius:10px;display:grid;place-items:center;color:rgba(255,255,255,.75);transition:background .15s,color .15s}
    .side-out:hover{background:rgba(255,255,255,.14);color:#fff}
    .av{border-radius:50%;display:inline-grid;place-items:center;color:#fff;font-weight:600;font-size:.8rem;flex-shrink:0}

    .mbar{display:none}
    .scrim{display:none}

    /* ---------- Main ---------- */
    main{flex:1;min-width:0;padding:1.5rem 2rem;background:<?= $cream ? 'var(--cream)' : '#fff' ?>}
    .top{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:1.25rem}
    h1{font-size:1.45rem;margin:0;font-weight:600}
    .sub{color:var(--soft);font-size:.75rem;margin:.15rem 0 0}
    .btn.big{padding:.6rem 1.3rem;font-size:.8rem}

    /* Top right: action button + notification bell */
    .top-right{display:flex;align-items:center;gap:.75rem;margin-left:auto}
    .bell-wrap{position:relative}
    .bell{position:relative;width:40px;height:40px;border-radius:50%;border:1px solid var(--line);background:#fff;color:var(--g);display:grid;place-items:center;cursor:pointer;padding:0}
    .bell:hover{background:#eef3f1}
    .bell:focus-visible{outline:2px solid var(--coral);outline-offset:2px}
    .bell-count{position:absolute;top:-4px;right:-4px;min-width:18px;height:18px;padding:0 4px;border-radius:999px;background:var(--coral);color:#fff;font-size:.62rem;font-weight:600;display:grid;place-items:center;border:2px solid #fff}
    .bell-pop{position:absolute;right:0;top:calc(100% + 8px);width:340px;max-width:calc(100vw - 2rem);background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:0 10px 30px rgba(15,61,58,.18);z-index:50;overflow:hidden}
    .bell-head{display:flex;justify-content:space-between;align-items:center;padding:.7rem 1rem;border-bottom:1px solid #ece7d6;font-size:.8rem}
    .bell-head button{border:0;background:none;color:var(--coral);font:inherit;font-size:.72rem;cursor:pointer;padding:0}
    .bell-list{max-height:360px;overflow-y:auto}
    .bell-item{display:block;padding:.65rem 1rem;border-bottom:1px solid #f1eddc;text-decoration:none;color:var(--ink);position:relative}
    .bell-item:last-child{border-bottom:0}
    .bell-item:hover{background:#fbfaf3}
    .bell-item b{display:block;font-size:.78rem;font-weight:600}
    .bell-item span{display:block;font-size:.72rem;color:var(--soft);margin-top:1px}
    .bell-item small{display:block;font-size:.65rem;color:#8a9b97;margin-top:2px}
    .bell-item.unread{background:#fff7f4;padding-left:1.4rem}
    .bell-item.unread::before{content:"";position:absolute;left:.6rem;top:1.05rem;width:7px;height:7px;border-radius:50%;background:var(--coral)}
    .pad{padding:.9rem 1rem;margin:0}

    .stars{display:inline-block;letter-spacing:1px;white-space:nowrap;line-height:1}
    .stars i{font-style:normal;color:#d6d6d6}
    .stars i.on{color:#f5b301}

    /* ---------- Cards, panels, stats ---------- */
    .card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:1rem 1.25rem;margin-bottom:1rem}
    .panel{background:#fff;border:1px solid var(--edge);border-radius:18px;padding:1rem 1.25rem;margin-bottom:1.25rem;box-shadow:0 2px 0 rgba(0,0,0,.06)}
    .panel h3{margin:0 0 .6rem;font-size:.85rem;font-weight:600;display:flex;justify-content:space-between;align-items:center}
    .panel h3 a{font-size:.72rem;font-weight:500;color:var(--coral);text-decoration:none}
    .panel table{border:0;border-radius:0}

    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:1rem;margin-bottom:1.25rem}
    .stat,.kpi{background:#fff;border:1px solid #e3ddc6;border-radius:20px;padding:.9rem 1.1rem;font-size:.72rem;font-weight:500;color:var(--soft);box-shadow:0 4px 14px rgba(15,61,58,.08)}
    .stat b,.kpi b{display:block;font-size:1.7rem;margin-top:.25rem;color:var(--ink);font-weight:600}
    .kpi{display:flex;flex-direction:column;min-height:118px}
    .kpi .spark{width:100%;height:30px;margin-top:auto}
    .bar{margin-top:auto;height:6px;border-radius:9px;background:#e6ebe9;overflow:hidden}
    .bar i{display:block;height:100%;background:var(--g);border-radius:9px}

    .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:1.25rem}
    .stat2{display:flex;align-items:center;gap:.9rem;background:#fff;border:1px solid #e3ddc6;border-radius:18px;padding:.85rem 1rem;box-shadow:0 4px 14px rgba(15,61,58,.07)}
    .stat2 small{display:block;font-size:.7rem;color:var(--soft);font-weight:500}
    .stat2 b{display:block;font-size:1.35rem;font-weight:600;line-height:1.2}
    .si{width:42px;height:42px;border-radius:12px;display:grid;place-items:center;flex-shrink:0}
    .si.g{background:#e3efec;color:#123f3a}.si.coral{background:#fde4de;color:#d14a32}
    .si.amber{background:#fdf0cf;color:#a16207}.si.blue{background:#e0ebf8;color:#2b6cb0}

    /* ---------- Tables ---------- */
    table{width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden;font-size:.8rem}
    th,td{text-align:left;padding:.65rem .85rem;border-bottom:1px solid #ece7d6;vertical-align:middle}
    th{background:#f7f4e8;font-weight:600;font-size:.64rem;letter-spacing:.05em;text-transform:uppercase;color:var(--soft);white-space:nowrap}
    tr:last-child td{border-bottom:0}
    table tr:hover td{background:#fbfaf3}
    .empty-row:hover td{background:#fff!important}
    .scroll{overflow-x:auto}
    .tright{text-align:right}

    /* ---------- Buttons ---------- */
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:.4rem;border:0;border-radius:999px;padding:.45rem 1rem;background:var(--g);color:#fff;cursor:pointer;font:inherit;font-size:.75rem;font-weight:500;text-decoration:none;white-space:nowrap;transition:filter .15s,background .15s}
    .btn:hover{filter:brightness(1.14)}
    .btn:disabled{opacity:.55;cursor:not-allowed;filter:none}
    .btn .ico{width:15px;height:15px}
    .btn.sm{padding:.32rem .75rem;font-size:.7rem}
    .btn.coral{background:var(--coral)}.btn.green{background:#1f7a4d}.btn.red{background:#b91c1c}.btn.gray{background:#6b7280}
    .btn.ghost{background:#fff;color:var(--g);border:1px solid var(--line)}
    .btn.ghost:hover{background:#eef3f1;filter:none}
    .btn.done{background:#1f7a4d;color:#fff;border-color:#1f7a4d}
    .btn.wide{width:100%;padding:.75rem;font-size:.85rem}
    .inl{display:inline;margin:0}
    .acts{display:flex;gap:.4rem;flex-wrap:wrap;align-items:center}
    .acts form{margin:0}

    /* ---------- Forms ---------- */
    input,select,textarea{padding:.55rem .75rem;border:1px solid var(--line);border-radius:10px;font:inherit;background:#fff;color:var(--ink)}
    input:focus,select:focus,textarea:focus,.btn:focus-visible{outline:2px solid var(--coral);outline-offset:2px}
    label{display:block;font-size:.75rem;font-weight:500;margin:.5rem 0 .25rem}
    .fgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.2rem .9rem}
    .fgrid input,.fgrid select,.fgrid textarea,.full{width:100%}
    .row{display:flex;gap:.75rem;flex-wrap:wrap;align-items:end}
    .search{position:relative;display:inline-block}
    .search svg{position:absolute;left:.75rem;top:50%;transform:translateY(-50%)}
    .search input{padding-left:2.2rem;border-radius:12px;border-color:#6b7f7a;width:280px;max-width:100%;font-size:.78rem}
    .toolbar{display:flex;gap:.75rem;flex-wrap:wrap;align-items:center;justify-content:space-between;margin-bottom:1rem}

    .ok,.err{padding:.6rem .9rem;border-radius:10px;margin-bottom:1rem;font-size:.85rem}
    .ok{background:#d8f5d0;color:#166534}.err{background:#fee2e2;color:#991b1b}
    .note{display:flex;gap:.6rem;align-items:flex-start;padding:.7rem .9rem;border-radius:12px;background:#fdf6e3;border:1px solid #efe0ae;color:#6b5311;font-size:.78rem;margin-bottom:1rem}

    .tabs{display:flex;flex-wrap:wrap;gap:.25rem;margin:0}
    .tabs a{display:inline-flex;align-items:center;padding:.38rem 1rem;border-radius:999px;color:var(--ink);text-decoration:none;font-size:.75rem;font-weight:500}
    .tabs a:hover{background:#eef3f1}
    .tabs a.on{background:var(--g);color:#fff}
    .tabs a em{font-style:normal;font-size:.64rem;margin-left:.4rem;padding:1px 7px;border-radius:999px;background:#e5ece9}
    .tabs a.on em{background:rgba(255,255,255,.22)}

    .muted{color:#6b7f7a;font-size:.76rem}
    .pill{display:inline-block;padding:2px 10px;border-radius:999px;font-size:.66rem;font-weight:500;border:1px solid;white-space:nowrap}
    .pill::before{content:"";display:inline-block;width:6px;height:6px;border-radius:50%;background:currentColor;margin-right:6px;vertical-align:1px}
    .p-ok{background:#d8f5d0;color:#166534;border-color:#86c98a}
    .p-warn{background:#fecaca;color:#b91c1c;border-color:#f08a8a}
    .p-info{background:#fde6c8;color:#b45309;border-color:#f0b15c}
    .p-off{background:#e5e7eb;color:#4b5563;border-color:#c4c8cf}
    .who{display:flex;align-items:center;gap:.6rem}
    .ref{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-weight:600;font-size:.78rem}

    /* ---------- Empty states ---------- */
    .empty{text-align:center;padding:2.2rem 1rem;color:var(--soft)}
    .empty .ei{width:52px;height:52px;border-radius:50%;background:#eef3f1;color:var(--g);display:grid;place-items:center;margin:0 auto .7rem}
    .empty b{display:block;color:var(--ink);font-size:.9rem;font-weight:600}
    .empty p{margin:.25rem 0 0;font-size:.76rem}

    /* ---------- Modals ---------- */
    dialog.modal{border:0;border-radius:18px;padding:0;width:min(720px,94vw);max-height:90vh;background:#fff;color:var(--ink);box-shadow:0 24px 60px rgba(10,30,28,.35)}
    dialog.modal[open]{display:flex;flex-direction:column}
    dialog.modal.sm{width:min(420px,94vw)}
    dialog.modal.lg{width:min(860px,95vw)}
    dialog.modal::backdrop{background:rgba(10,30,28,.55);backdrop-filter:blur(2px)}
    .m-head{display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:1rem 1.25rem;border-bottom:1px solid #ece7d6}
    .m-head b{font-size:.95rem;font-weight:600}
    .m-head .x{width:32px;height:32px;border:0;border-radius:9px;background:#f1eddc;color:var(--ink);display:grid;place-items:center;cursor:pointer;padding:0}
    .m-head .x:hover{background:#e7e1c9}
    .m-body{padding:1.1rem 1.25rem 1.25rem;overflow-y:auto}
    .m-actions{display:flex;justify-content:flex-end;gap:.5rem;flex-wrap:wrap;margin-top:1rem;padding-top:.9rem;border-top:1px solid #ece7d6}
    .mtabs{display:flex;gap:.3rem;padding:.25rem;background:#f1eddc;border-radius:999px;width:max-content;max-width:100%;overflow-x:auto;margin-bottom:1rem}
    .mtabs button{border:0;background:none;padding:.38rem .95rem;border-radius:999px;font:inherit;font-size:.74rem;font-weight:500;cursor:pointer;color:var(--ink);white-space:nowrap}
    .mtabs button.on{background:var(--g);color:#fff}
    .kv{display:grid;grid-template-columns:150px 1fr;gap:.6rem 1rem;margin:0;font-size:.82rem}
    .kv dt{color:var(--soft)}
    .kv dd{margin:0;font-weight:500;word-break:break-word}
    .rate{display:flex;gap:.6rem;align-items:end;flex-wrap:wrap;margin-bottom:.6rem}
    .rate form{display:flex;gap:.5rem;align-items:end;margin:0;flex-wrap:wrap}

    /* ---------- Toasts ---------- */
    .toasts{position:fixed;top:1rem;right:1rem;z-index:200;display:flex;flex-direction:column;gap:.5rem;width:min(380px,calc(100vw - 2rem));pointer-events:none}
    .toast{pointer-events:auto;display:flex;align-items:flex-start;gap:.7rem;padding:.75rem .9rem;border-radius:12px;background:#fff;border:1px solid var(--line);border-left:4px solid #1f7a4d;box-shadow:0 10px 30px rgba(15,61,58,.2);font-size:.82rem;animation:tin .25s ease}
    .toast.t-err{border-left-color:#dc2626}
    .toast .ti{margin-top:1px;color:#1f7a4d}.toast.t-err .ti{color:#dc2626}
    .toast .tx{flex:1;line-height:1.45}
    .toast .tc{border:0;background:none;cursor:pointer;color:#8a9b97;padding:2px;border-radius:6px;display:grid;place-items:center}
    .toast .tc:hover{background:#f1eddc}
    @keyframes tin{from{opacity:0;transform:translateY(-8px)}}

    /* ---------- Room cards ---------- */
    .rgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:1.1rem}
    .rcard{background:#fff;border:1px solid var(--line);border-radius:18px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 4px 14px rgba(15,61,58,.07);transition:box-shadow .15s,transform .15s}
    .rcard:hover{box-shadow:0 10px 26px rgba(15,61,58,.14);transform:translateY(-2px)}
    .rcard.dim .rimg{filter:grayscale(1);opacity:.75}
    .rimg{position:relative;height:130px;background:linear-gradient(160deg,#1e6b62,#0d2f2b);display:grid;place-items:center;color:rgba(255,255,255,.92)}
    .rimg img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
    .rimg .rno{font-size:2.3rem;font-weight:600;letter-spacing:.02em}
    .rimg .pill{position:absolute;top:.7rem;left:.7rem;box-shadow:0 2px 8px rgba(0,0,0,.18)}
    .rbody{padding:.9rem 1rem 1rem;display:flex;flex-direction:column;gap:.65rem;flex:1}
    .rtop{display:flex;justify-content:space-between;gap:.5rem;align-items:flex-start}
    .rtop h3{margin:0;font-size:.95rem;font-weight:600}
    .rprice{text-align:right;font-weight:600;font-size:.95rem;line-height:1.1;white-space:nowrap}
    .rprice small{display:block;font-weight:400;color:var(--soft);font-size:.62rem;margin-bottom:2px}
    .rrates{display:flex;flex-wrap:wrap;gap:.35rem}
    .rrates span{font-size:.68rem;padding:.2rem .55rem;border-radius:999px;background:#f1eddc}
    .rnow{font-size:.74rem;padding:.55rem .7rem;border-radius:10px;background:#fff7f4;border:1px solid #f3d3c9;line-height:1.5}
    .racts{display:flex;gap:.4rem;flex-wrap:wrap;margin-top:auto}
    .racts form{margin:0}

    /* Sidebar polish */
    aside{background:linear-gradient(180deg,#154a43 0%,#0d312d 100%)}
    .nav-label{margin-top:1.4rem}
    .side-nav a{padding:.68rem .85rem;border-radius:11px}
    .side-nav a.on{background:rgba(255,255,255,.13);box-shadow:inset 0 0 0 1px rgba(255,255,255,.08);font-weight:600}
    .side-nav a.on .ico{color:#ffb9aa}
    .side-user{background:rgba(0,0,0,.18)}

    /* ---------- Tablet / phone: sidebar becomes a slide-in drawer ---------- */
    @media(max-width:900px){
      .app{flex-direction:column}
      .mbar{display:flex;align-items:center;gap:.75rem;position:sticky;top:0;z-index:60;background:var(--g);color:#fff;padding:.6rem 1rem}
      .mbar b{font-weight:600;font-size:.9rem}
      .hamb{width:40px;height:40px;border:0;border-radius:10px;background:rgba(255,255,255,.1);color:#fff;display:grid;place-items:center;cursor:pointer;padding:0}
      .hamb:hover{background:rgba(255,255,255,.18)}
      aside{position:fixed;left:0;top:0;bottom:0;height:auto;width:270px;max-width:84vw;z-index:100;transform:translateX(-100%);transition:transform .22s ease}
      body.nav-open aside{transform:translateX(0);box-shadow:8px 0 30px rgba(0,0,0,.3)}
      .scrim{display:block;position:fixed;inset:0;z-index:90;background:rgba(10,30,28,.5);opacity:0;pointer-events:none;transition:opacity .22s ease}
      body.nav-open .scrim{opacity:1;pointer-events:auto}
      body.nav-open{overflow:hidden}
      main{padding:1rem}
      .kv{grid-template-columns:1fr}
    }
    @media(prefers-reduced-motion:reduce){
      aside,.scrim,.toast,.rcard{transition:none;animation:none}
    }
  </style>
</head>
<body>
<div class="app">
<header class="mbar">
  <button type="button" class="hamb" id="hamb" aria-label="Open menu" aria-controls="sidebar" aria-expanded="false"><?= navIcon('menu') ?></button>
  <b>Pension House</b>
</header>
<div class="scrim" id="scrim"></div>
<aside id="sidebar" aria-label="Sidebar">
  <div class="brand">
        <img class="pic" src="<?= e(BASE_URL) ?>assets/logo.svg" alt="" width="42" height="42">
    <div><b>Pension House</b><small><?= e($panelLabel) ?></small></div>
  </div>

  <nav class="side-nav" aria-label="Main menu">
    <?php foreach ($menu as $label => $links): ?>
      <div class="nav-label"><?= e($label) ?></div>
      <?php foreach ($links as $l): $on = $cur === $l['page']; ?>
        <a href="<?= e($l['url']) ?>" class="<?= $on ? 'on' : '' ?>"<?= $on ? ' aria-current="page"' : '' ?>>
          <?= navIcon($l['page']) ?><span><?= e($l['text']) ?></span>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>

  <div class="side-user">
    <span class="av" style="background:var(--coral);width:38px;height:38px"><?= $initial ?></span>
    <div class="who2"><b><?= e($user['fullName']) ?></b><small><?= e($roleLabel) ?></small></div>
    <a class="side-out" href="<?= e(BASE_URL) ?>logout.php" title="Log out" aria-label="Log out"><?= navIcon('logout') ?></a>
  </div>
</aside>
<main>
  <div class="toasts" aria-live="polite">
  <?php if ($flash): $okFlash = $flash['type'] === 'ok'; ?>
    <div class="toast <?= $okFlash ? 't-ok' : 't-err' ?>" role="status">
      <span class="ti"><?= navIcon($okFlash ? 'check' : 'x', 16) ?></span>
      <span class="tx"><?= e($flash['msg']) ?></span>
      <button type="button" class="tc" aria-label="Dismiss"><?= navIcon('x', 14) ?></button>
    </div>
  <?php endif; ?>
  </div>

  <dialog class="modal sm" id="phConfirm" aria-labelledby="phConfirmT">
    <div class="m-head"><b id="phConfirmT" data-c="title">Please confirm</b></div>
    <div class="m-body">
      <p data-c="msg" style="margin:0;font-size:.85rem;line-height:1.6"></p>
      <div class="m-actions">
        <button type="button" class="btn ghost" data-close>Cancel</button>
        <button type="button" class="btn" data-c="ok">Confirm</button>
      </div>
    </div>
  </dialog>

  <div class="top">
    <div>
      <h1><?= e($title) ?></h1>
      <?php if (!empty($opt['subHtml'])): ?>
        <p class="sub"><?= $opt['subHtml'] ?></p>
      <?php elseif ($sub !== ''): ?>
        <p class="sub"><?= e($sub) ?></p>
      <?php endif; ?>
    </div>
    <div class="top-right">
      <?= $opt['action'] ?? '' ?>
      <div class="bell-wrap">
        <button type="button" class="bell" id="bellBtn" aria-label="Notifications" aria-haspopup="true" aria-expanded="false">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          <span class="bell-count" id="bellCount" hidden>0</span>
        </button>
        <div class="bell-pop" id="bellPop" hidden>
          <div class="bell-head"><b>Notifications</b><button type="button" id="bellAll">Mark all as read</button></div>
          <div class="bell-list" id="bellList"><p class="muted pad">Loading...</p></div>
        </div>
      </div>
    </div>
  </div>
  <script>
  // Mobile sidebar drawer
  (function () {
    var hamb = document.getElementById('hamb');
    var scrim = document.getElementById('scrim');
    var side = document.getElementById('sidebar');
    if (!hamb || !scrim || !side) return;

    function setOpen(open) {
      document.body.classList.toggle('nav-open', open);
      hamb.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    hamb.addEventListener('click', function () { setOpen(!document.body.classList.contains('nav-open')); });
    scrim.addEventListener('click', function () { setOpen(false); });
    side.addEventListener('click', function (e) { if (e.target.closest('a')) setOpen(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });
    window.addEventListener('resize', function () { if (window.innerWidth > 900) setOpen(false); });
  })();

  // Shared UI behaviour: dialogs, confirm, tabs, copy, live filter, toasts
  (function () {
    // ----- clicks -----
    document.addEventListener('click', function (e) {
      var t = e.target;
      var o = t.closest('[data-open]');
      if (o) {
        var d = document.querySelector(o.getAttribute('data-open'));
        if (d && d.showModal) d.showModal();
        return;
      }
      var c = t.closest('[data-close]');
      if (c) { var dc = c.closest('dialog'); if (dc) dc.close(); return; }
      if (t.tagName === 'DIALOG') { t.close(); return; }

      var tb = t.closest('.mtabs button');
      if (tb) {
        var dlg = tb.closest('dialog');
        dlg.querySelectorAll('.mtabs button').forEach(function (x) { x.classList.toggle('on', x === tb); });
        dlg.querySelectorAll('.pane').forEach(function (p) { p.hidden = p.getAttribute('data-pane') !== tb.getAttribute('data-pane'); });
        return;
      }

      var cp = t.closest('[data-copy]');
      if (cp) {
        var v = cp.getAttribute('data-copy');
        var done = function () { cp.classList.add('done'); setTimeout(function () { cp.classList.remove('done'); }, 1200); };
        if (navigator.clipboard) navigator.clipboard.writeText(v).then(done);
        return;
      }

      var tc = t.closest('.toast .tc');
      if (tc) tc.parentNode.remove();
    });

    // ----- confirm dialog for forms that carry data-confirm -----
    var cd = document.getElementById('phConfirm');
    var pending = null;
    document.addEventListener('submit', function (e) {
      var f = e.target;
      if (!(f instanceof HTMLFormElement)) return;
      if (f.dataset.confirm && f.dataset.okd !== '1') {
        e.preventDefault();
        if (!f.checkValidity()) { f.reportValidity(); return; }
        pending = f;
        cd.querySelector('[data-c=title]').textContent = f.dataset.confirmTitle || 'Please confirm';
        cd.querySelector('[data-c=msg]').textContent = f.dataset.confirm;
        var ok = cd.querySelector('[data-c=ok]');
        ok.textContent = f.dataset.confirmOk || 'Confirm';
        ok.className = 'btn ' + (f.dataset.confirmTone || '');
        cd.showModal();
        return;
      }
      // stop double clicks on slow connections
      setTimeout(function () {
        f.querySelectorAll('button:not([type=button])').forEach(function (b) { b.disabled = true; });
      }, 0);
    });
    cd.querySelector('[data-c=ok]').addEventListener('click', function () {
      if (!pending) return;
      var f = pending; pending = null;
      f.dataset.okd = '1';
      cd.close();
      f.submit();
    });
    window.addEventListener('pageshow', function () {
      document.querySelectorAll('form button:disabled').forEach(function (b) { b.disabled = false; });
    });

    // ----- live search + filter chips on any container holding [data-text] items -----
    document.addEventListener('DOMContentLoaded', function () {
      var st = {};
      function apply(sel) {
        var box = document.querySelector(sel);
        if (!box) return;
        var s = st[sel] || { q: '', c: 'ALL' }, n = 0;
        box.querySelectorAll('[data-text]').forEach(function (r) {
          var ok = (!s.q || r.getAttribute('data-text').indexOf(s.q) > -1) &&
                   (s.c === 'ALL' || r.getAttribute('data-status') === s.c);
          r.hidden = !ok;
          if (ok) n++;
        });
        var m = box.querySelector('.nomatch');
        if (m) m.hidden = n > 0;
      }
      document.querySelectorAll('input[data-filter]').forEach(function (i) {
        var sel = i.getAttribute('data-filter');
        st[sel] = st[sel] || { q: '', c: 'ALL' };
        i.addEventListener('input', function () { st[sel].q = i.value.trim().toLowerCase(); apply(sel); });
      });
      document.querySelectorAll('[data-chip]').forEach(function (a) {
        a.addEventListener('click', function (e) {
          e.preventDefault();
          var sel = a.getAttribute('data-chip-for');
          st[sel] = st[sel] || { q: '', c: 'ALL' };
          st[sel].c = a.getAttribute('data-chip');
          a.parentNode.querySelectorAll('[data-chip]').forEach(function (x) { x.classList.toggle('on', x === a); });
          apply(sel);
        });
      });
      document.querySelectorAll('.toast').forEach(function (el) { setTimeout(function () { el.remove(); }, 6000); });
    });
  })();

  // Notification bell
  (function () {
    var URLS = <?= json_encode($notifUrls, $jf) ?>;
    var CSRF = <?= json_encode(csrfToken(), $jf) ?>;
    var EP   = <?= json_encode(BASE_URL . 'notifications.php', $jf) ?>;

    var btn  = document.getElementById('bellBtn');
    var pop  = document.getElementById('bellPop');
    var list = document.getElementById('bellList');
    var cnt  = document.getElementById('bellCount');
    var all  = document.getElementById('bellAll');
    if (!btn) return;

    function ago(s) {
      var sec = (Date.now() - new Date(s).getTime()) / 1000;
      if (isNaN(sec) || sec < 60) return 'Just now';
      if (sec < 3600)  return Math.floor(sec / 60) + ' min ago';
      if (sec < 86400) return Math.floor(sec / 3600) + ' hr ago';
      return Math.floor(sec / 86400) + ' d ago';
    }

    function post(params) {
      params.csrf = CSRF;
      return fetch(EP, { method: 'POST', body: new URLSearchParams(params), credentials: 'same-origin', keepalive: true });
    }

    function render(d) {
      var items = d.items || [], n = d.unread || 0;
      cnt.textContent = n > 99 ? '99+' : n;
      cnt.hidden = n === 0;
      list.textContent = '';
      if (!items.length) {
        var p = document.createElement('p');
        p.className = 'muted pad';
        p.textContent = 'No notifications yet.';
        list.appendChild(p);
        return;
      }
      items.forEach(function (it) {
        var a = document.createElement('a');
        a.className = 'bell-item' + (it.isRead ? '' : ' unread');
        a.href = URLS[it.type] || '#';
        var t = document.createElement('b');    t.textContent = it.title;
        var m = document.createElement('span'); m.textContent = it.message;
        var s = document.createElement('small'); s.textContent = ago(it.createdAt);
        a.appendChild(t); a.appendChild(m); a.appendChild(s);
        a.addEventListener('click', function () { if (!it.isRead) post({ id: it.id }); });
        list.appendChild(a);
      });
    }

    function load() {
      fetch(EP, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) { if (d) render(d); })
        .catch(function () {});
    }

    function closePop() { pop.hidden = true; btn.setAttribute('aria-expanded', 'false'); }

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var opening = pop.hidden;
      pop.hidden = !opening;
      btn.setAttribute('aria-expanded', opening ? 'true' : 'false');
      if (opening) load();
    });
    document.addEventListener('click', function (e) { if (!pop.contains(e.target)) closePop(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closePop(); });
    all.addEventListener('click', function () { post({}).then(load); });

    load();
    setInterval(function () { if (!document.hidden) load(); }, 30000);
  })();
  </script>
<?php
}

function pageEnd(): void
{
    echo "</main>\n</div>\n</body>\n</html>";
}

// =====================================================================
// Dashboard helpers
// =====================================================================

function dashBuckets(string $range, int $back = 0): array
{
    $keys = [];
    if ($range === '12m') {
        $base = strtotime(date('Y-m-01'));
        for ($i = 11; $i >= 0; $i--) {
            $keys[] = date('Y-m', strtotime('-' . ($i + 12 * $back) . ' month', $base));
        }
        return ['keys' => $keys, 'len' => 7];
    }
    $n = $range === '30d' ? 30 : 7;
    for ($i = $n - 1; $i >= 0; $i--) {
        $keys[] = date('Y-m-d', strtotime('-' . ($i + $n * $back) . ' day'));
    }
    return ['keys' => $keys, 'len' => 10];
}

// Counts (or sums) rows per bucket; returns a plain list.
function dashSeries(array $rows, callable $dateOf, array $b, ?callable $valOf = null): array
{
    $out = array_fill_keys($b['keys'], 0);
    foreach ($rows as $r) {
        $k = substr((string)$dateOf($r), 0, $b['len']);
        if (isset($out[$k])) $out[$k] += $valOf ? $valOf($r) : 1;
    }
    return array_values($out);
}

function dashDelta(float $cur, float $prev, bool $goodUp = true): string
{
    if ($prev <= 0) return '<span class="dd flat">No prior data</span>';
    $pct = ($cur - $prev) / $prev * 100;
    if (abs($pct) < 0.05) return '<span class="dd flat">0% change</span>';
    $up = $pct > 0;
    return '<span class="dd ' . ($up === $goodUp ? 'good' : 'bad') . '">'
         . ($up ? '▲' : '▼') . ' ' . number_format(abs($pct), 1) . '%</span>';
}

function dashAgo(?string $s): string
{
    $t = $s ? strtotime($s) : false;
    if (!$t) return '';
    $d = time() - $t;
    if ($d < 60)     return 'Just now';
    if ($d < 3600)   return floor($d / 60) . ' min ago';
    if ($d < 86400)  return floor($d / 3600) . ' hr ago';
    if ($d < 604800) return floor($d / 86400) . ' d ago';
    return date('M j', $t);
}

// Whole pesos, so big totals stay short on a compact card.
function dashPeso($n): string
{
    return '₱' . number_format((float)$n);
}

// Compact KPI card: icon on the left, label / value / change on the right.
function dashKpi(string $label, string $value, string $icon, string $tone, string $foot): string
{
    return '<div class="dk"><span class="si ' . e($tone) . '">' . navIcon($icon, 18) . '</span>'
         . '<div class="dk-b"><span class="dk-l">' . e($label) . '</span>'
         . '<div class="dk-v">' . e($value) . '</div><div class="dk-f">' . $foot . '</div></div></div>';
}

function dashDonut(array $parts, int $total): string
{
    $svg = '<svg viewBox="0 0 42 42" class="donut" role="img" aria-label="Booking status breakdown">'
         . '<circle cx="21" cy="21" r="15.9155" fill="none" stroke="#eef1f0" stroke-width="5"/>';
    $acc = 0;
    foreach ($parts as $p) {
        if ($p['n'] <= 0 || $total <= 0) continue;
        $pct = $p['n'] / $total * 100;
        $svg .= '<circle cx="21" cy="21" r="15.9155" fill="none" stroke="' . $p['color'] . '" stroke-width="5" stroke-dasharray="'
              . round($pct, 3) . ' ' . round(100 - $pct, 3) . '" stroke-dashoffset="' . round(25 - $acc, 3) . '"><title>'
              . e($p['label']) . ': ' . $p['n'] . '</title></circle>';
        $acc += $pct;
    }
    return $svg . '<text x="21" y="22.2" text-anchor="middle" class="dn">' . $total . '</text>'
         . '<text x="21" y="27" text-anchor="middle" class="dl2">bookings</text></svg>';
}

// =====================================================================
// Dashboard page (used by admin/dashboard.php and staff/dashboard.php)
// =====================================================================

function renderDashboard(array $user): void
{
    $list = function (string $path): array {
        $r = apiAuth($path);
        return is_array($r['data']) ? array_values(array_filter($r['data'], 'is_array')) : [];
    };
    $bookings = $list('bookings');
    $payments = $list('payments');
    $rooms    = array_filter($list('rooms?includeInactive=true'), fn($r) => $r['isActive'] ?? true);
    $role     = $user['role'];
    $isAdmin  = $role === 'admin';
    $jf       = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

    // ----- Range: weekly / monthly / yearly -----
    $range = in_array($_GET['range'] ?? '', ['7d', '30d', '12m'], true) ? $_GET['range'] : '7d';
    $rangeTitle = ['7d' => 'last 7 days', '30d' => 'last 30 days', '12m' => 'last 12 months'][$range];
    $cur  = dashBuckets($range, 0);
    $prev = dashBuckets($range, 1);

    $labels = []; $tips = [];
    foreach ($cur['keys'] as $k) {
        if ($range === '12m')    { $t = strtotime($k . '-01'); $labels[] = date('M', $t);   $tips[] = date('F Y', $t); }
        elseif ($range === '7d') { $t = strtotime($k);         $labels[] = date('D', $t);   $tips[] = date('D, M j', $t); }
        else                     { $t = strtotime($k);         $labels[] = date('M j', $t); $tips[] = date('M j, Y', $t); }
    }

    // ----- Numbers (all from the API) -----
    $created = fn($r) => $r['createdAt'] ?? '';
    $amount  = fn($p) => (float)($p['amount'] ?? 0);
    $byStatus = function (array $sts) use ($bookings) {
        return array_values(array_filter($bookings, fn($b) => in_array($b['status'] ?? '', $sts, true)));
    };
    $pendingB  = $byStatus(['PENDING']);
    $confirmed = $byStatus(['CONFIRMED']);
    $checkedIn = $byStatus(['CHECKED_IN']);
    $completed = $byStatus(['COMPLETED']);
    $cancelled = $byStatus(['CANCELLED']);
    $active    = array_merge($confirmed, $checkedIn);

    $approved   = array_values(array_filter($payments, fn($p) => ($p['status'] ?? '') === 'APPROVED'));
    $revenue    = array_sum(array_map($amount, $approved));
    $pendingPay = count(array_filter($payments, fn($p) => ($p['status'] ?? '') === 'PENDING'));

    $today    = date('Y-m-d');
    $todayN   = count(array_filter($bookings, fn($b) => substr($b['checkIn'] ?? '', 0, 10) === $today && ($b['status'] ?? '') !== 'CANCELLED'));
    $inToday  = count(array_filter($confirmed, fn($b) => substr($b['checkIn'] ?? '', 0, 10) === $today));
    $outToday = count(array_filter($checkedIn, fn($b) => substr($b['checkOut'] ?? '', 0, 10) === $today));
    $overdue  = count(array_filter($checkedIn, fn($b) => !empty($b['checkOut']) && strtotime($b['checkOut']) < time()));

    $occupied = count(array_filter($rooms, fn($r) => ($r['status'] ?? '') === 'OCCUPIED'));
    $occPct   = count($rooms) ? round($occupied / count($rooms) * 100, 1) : 0;

    // ----- Series for the chart and the % changes -----
    $sBook = dashSeries($bookings, $created, $cur);   $pBook = dashSeries($bookings, $created, $prev);
    $sDone = dashSeries($completed, $created, $cur);  $pDone = dashSeries($completed, $created, $prev);
    $sCanc = dashSeries($cancelled, $created, $cur);  $pCanc = dashSeries($cancelled, $created, $prev);
    $sRev  = dashSeries($approved, $created, $cur, $amount);
    $pRev  = dashSeries($approved, $created, $prev, $amount);

    $chartData = [
        'labels'   => $labels,
        'tips'     => $tips,
        'bookings' => $sBook,
        'revenue'  => $isAdmin ? array_map(fn($v) => round($v, 2), $sRev) : [],
    ];

    // ----- KPI cards (5 in one row) -----
    $in = fn($arr) => '<span>' . array_sum($arr) . ' in period</span>';
    $kpis = [];
    if ($isAdmin) {
        $kpis[] = dashKpi('Total bookings', (string)count($bookings), 'bookings.php', 'g',
            dashDelta(array_sum($sBook), array_sum($pBook)) . $in($sBook));
        $kpis[] = dashKpi('Active bookings', (string)count($active), 'clock', 'amber',
            '<span>' . count($checkedIn) . ' checked in · ' . count($confirmed) . ' confirmed</span>');
        $kpis[] = dashKpi('Completed', (string)count($completed), 'check', 'g',
            dashDelta(array_sum($sDone), array_sum($pDone)) . $in($sDone));
        $kpis[] = dashKpi('Cancelled', (string)count($cancelled), 'x', 'coral',
            dashDelta(array_sum($sCanc), array_sum($pCanc), false) . $in($sCanc));
        $kpis[] = dashKpi('Total revenue', dashPeso($revenue), 'payments.php', 'blue',
            dashDelta(array_sum($sRev), array_sum($pRev)) . '<span>' . e(dashPeso(array_sum($sRev))) . ' in period</span>');
    } else {
        $kpis[] = dashKpi('Arrivals today', (string)$inToday, 'login', 'g',
            '<span>' . $todayN . ' scheduled check-in' . ($todayN === 1 ? '' : 's') . '</span>');
        $kpis[] = dashKpi('Departures today', (string)$outToday, 'power', 'coral',
            '<span>' . $overdue . ' past check-out time</span>');
        $kpis[] = dashKpi('Active bookings', (string)count($active), 'clock', 'amber',
            '<span>' . count($checkedIn) . ' checked in · ' . count($confirmed) . ' confirmed</span>');
        $kpis[] = dashKpi('Payments to review', (string)$pendingPay, 'inbox', 'coral',
            '<span>Waiting for review</span>');
        $kpis[] = dashKpi('Occupancy', $occPct . '%', 'rooms.php', 'blue',
            '<span>' . $occupied . ' of ' . count($rooms) . ' rooms</span>');
    }

    // ----- Status breakdown -----
    $parts = [
        ['label' => 'Pending',    'n' => count($pendingB),  'color' => '#e0a526'],
        ['label' => 'Confirmed',  'n' => count($confirmed), 'color' => '#2b6cb0'],
        ['label' => 'Checked in', 'n' => count($checkedIn), 'color' => '#1f9d8a'],
        ['label' => 'Completed',  'n' => count($completed), 'color' => '#123f3a'],
        ['label' => 'Cancelled',  'n' => count($cancelled), 'color' => '#d9534f'],
    ];
    $totalB = (int)array_sum(array_column($parts, 'n'));

    // ----- Recent bookings (newest first; small screens hide the extra rows) -----
    usort($bookings, fn($a, $b) => strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? ''));
    $recent = array_slice($bookings, 0, 6);

    // ----- Pending actions -----
    $bookUrl = navUrl($role, 'bookings.php');
    $payUrl  = navUrl($role, 'payments.php');
    $actions = [
        ['Payments to review', $pendingPay,      $payUrl,  'payments.php', 'amber'],
        ['Pending bookings',   count($pendingB), $bookUrl, 'bookings.php', 'blue'],
        ['Arrivals today',     $inToday,         $bookUrl, 'login',        'g'],
        ['Departures today',   $outToday,        $bookUrl, 'power',        'coral'],
    ];
    $allClear = array_sum(array_column($actions, 1)) === 0;

    // ----- Header: title, date, walk-in button, bell (from pageStart) and profile -----
    $chip = '<span class="pchip">' . avatar((string)$user['fullName'], 30)
          . '<span><b>' . e($user['fullName']) . '</b><small>' . ($isAdmin ? 'Administrator' : 'Staff Member') . '</small></span></span>';

    pageStart('Dashboard', $user,
        date('l, j F Y') . ' · ' . $todayN . ' check-in' . ($todayN === 1 ? '' : 's') . ' scheduled today',
        ['action' => newBookingBtn($role) . $chip]);
    ?>
    <style>
      main{background:#f3f6f5;padding:1rem 1.25rem}
      .top{margin-bottom:.7rem;align-items:center}
      h1{font-size:1.3rem}
      .sub{margin-top:0}
      .top .btn.big{padding:.5rem 1.1rem;font-size:.75rem}
      .top-right .bell-wrap{order:2}
      .bell{width:36px;height:36px}
      .pchip{order:3;display:flex;align-items:center;gap:.55rem;padding:.2rem .85rem .2rem .2rem;border:1px solid #e3e9e7;border-radius:999px;background:#fff}
      .pchip b{display:block;font-size:.74rem;font-weight:600;line-height:1.2}
      .pchip small{display:block;font-size:.62rem;color:var(--soft);line-height:1.2}

      .dash{--card:#fff;--bd:#e3e9e7;display:grid;gap:.65rem}

      /* toolbar: alert (or note) + range tabs */
      .d-tool{display:flex;justify-content:space-between;align-items:center;gap:.75rem;flex-wrap:wrap}
      .d-note{margin:0;font-size:.72rem;color:var(--soft)}
      .d-alert{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;padding:.25rem .5rem .25rem .35rem;border-radius:999px;background:#fff7ed;border:1px solid #f5d9b0;color:#7a4a0a;font-size:.72rem}
      .d-alert .ic{width:24px;height:24px;border-radius:50%;background:#fde9c8;display:grid;place-items:center;flex-shrink:0}
      .d-alert .ic .ico{width:14px;height:14px}
      .d-tool .tabs{background:#fff;border:1px solid var(--bd);border-radius:999px;padding:.15rem}
      .d-tool .tabs a{padding:.28rem .85rem;font-size:.72rem}

      /* KPI cards */
      .d-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:.65rem}
      .dk{display:flex;align-items:center;gap:.65rem;min-width:0;background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:.6rem .8rem;box-shadow:0 1px 2px rgba(15,61,58,.05)}
      .dk .si{width:34px;height:34px;border-radius:10px}
      .dk-b{min-width:0;flex:1}
      .dk-l{display:block;font-size:.68rem;font-weight:500;color:var(--soft);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
      .dk-v{font-size:clamp(1.05rem,1.45vw,1.3rem);font-weight:600;line-height:1.15;letter-spacing:-.01em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
      .dk-f{font-size:.62rem;color:var(--soft);display:flex;gap:.1rem .4rem;align-items:center;margin-top:1px;white-space:nowrap;overflow:hidden}
      .dd{font-weight:600;padding:0 6px;border-radius:999px;font-size:.6rem;flex-shrink:0}
      .dd.good{background:#dcf5e3;color:#166534}
      .dd.bad{background:#fde2e2;color:#b91c1c}
      .dd.flat{background:#eef1f0;color:#5f7772;font-weight:500}

      /* rows of panels */
      .d-r2,.d-r3{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(0,1fr);gap:.65rem;min-height:0}
      .dp{display:flex;flex-direction:column;min-width:0;min-height:0;overflow:hidden;background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:.7rem .9rem;box-shadow:0 1px 2px rgba(15,61,58,.05)}
      .dp-h{display:flex;justify-content:space-between;align-items:center;gap:.6rem;margin-bottom:.45rem;flex:none}
      .dp-h h3{margin:0;font-size:.82rem;font-weight:600}
      .dp-h p{margin:.1rem 0 0;font-size:.64rem;color:var(--soft)}
      .dp-h a{font-size:.7rem;font-weight:500;color:var(--coral);text-decoration:none;white-space:nowrap}
      .dp-b{flex:1;min-height:0;position:relative}
      .cbox{min-height:230px}
      .cbox svg{position:absolute;inset:0;width:100%;height:100%;display:block}
      .ax{font-size:10px;fill:#6b7f7a;font-family:Poppins,system-ui,sans-serif}
      .nodata{font-size:11px;fill:#8a9b97;font-family:Poppins,system-ui,sans-serif}

            /* trend chart: interaction */
      .cbox{cursor:crosshair;touch-action:pan-y}
      .cbox .hl{stroke:#9db3ae;stroke-width:1;stroke-dasharray:4 4;opacity:0;transition:opacity .15s}
      .cbox .hd{opacity:0;transition:opacity .15s}
      .cbox.hover .hl,.cbox.hover .hd{opacity:1}
      .cbox .ln.draw{stroke-dasharray:1;stroke-dashoffset:1;animation:lndraw .9s ease forwards}
      .cbox .ar.draw{opacity:0;animation:arfade .9s .25s ease forwards}
      @keyframes lndraw{to{stroke-dashoffset:0}}
      @keyframes arfade{to{opacity:1}}
      .tt{position:absolute;left:0;top:0;z-index:5;pointer-events:none;min-width:150px;padding:.55rem .75rem;border-radius:12px;background:#0f3d3a;color:#fff;box-shadow:0 10px 26px rgba(15,61,58,.28);font-size:.7rem;line-height:1.35;opacity:0;transform:translateY(4px);transition:opacity .12s,transform .12s}
      .tt.on{opacity:1;transform:none}
      .tt .tt-d{display:block;color:rgba(255,255,255,.65);font-size:.64rem;margin-bottom:.25rem}
      .tt .tt-v{display:flex;align-items:center;gap:.45rem;font-size:.95rem;font-weight:600}
      .tt .tt-v i{width:8px;height:8px;border-radius:50%;flex-shrink:0}
      .tt .tt-v small{font-size:.64rem;font-weight:400;color:rgba(255,255,255,.65)}
      .tt .tt-c{display:block;margin-top:.3rem;font-size:.64rem;font-weight:500}
      .tt .tt-c.up{color:#8ff0b0}.tt .tt-c.dn{color:#ffb4ad}.tt .tt-c.eq{color:rgba(255,255,255,.6)}
      @media(prefers-reduced-motion:reduce){.cbox .ln.draw,.cbox .ar.draw{animation:none;stroke-dashoffset:0;opacity:1}}

      .seg{display:flex;gap:2px;padding:2px;border-radius:999px;background:#eef3f1}
      .seg button{border:0;background:none;padding:.22rem .75rem;border-radius:999px;font:inherit;font-size:.68rem;font-weight:500;color:var(--soft);cursor:pointer}
      .seg button.on{background:#123f3a;color:#fff}

      /* status panel */
      .st-w{display:flex;align-items:stretch;gap:.8rem;min-height:150px}
      .st-d{position:relative;flex:0 0 46%}
      .donut{position:absolute;inset:0;width:100%;height:100%}
      .donut .dn{font-size:7px;font-weight:600;fill:#0f3d3a;font-family:Poppins,system-ui,sans-serif}
      .donut .dl2{font-size:2.6px;fill:#5f7772;font-family:Poppins,system-ui,sans-serif}
      .legend{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;justify-content:center;gap:.35rem;font-size:.7rem;flex:1;min-width:0}
      .legend li{display:flex;align-items:center;gap:.4rem}
      .legend i{width:8px;height:8px;border-radius:3px;flex-shrink:0}
      .legend b{margin-left:auto;font-weight:600}
      .legend small{color:var(--soft);font-weight:400;margin-left:.3rem}
      .occ2{display:flex;justify-content:space-between;font-size:.68rem;color:var(--soft);margin-top:.5rem;flex:none}
      .occ2 b{color:var(--ink);font-weight:600}
      .obar{height:7px;border-radius:9px;background:#eaf0ee;overflow:hidden;margin-top:.25rem;flex:none}
      .obar i{display:block;height:100%;border-radius:9px;background:linear-gradient(90deg,#123f3a,#2f7f72)}

      /* recent bookings table */
      .dp .scroll{flex:1;min-height:0;overflow:auto}
      .dp table{border:0;border-radius:0;font-size:.72rem}
      .dp th{background:#f5f8f7;padding:.35rem .6rem;font-size:.58rem}
      .dp td{padding:.3rem .6rem}
      .dp table tr:hover td{background:#f8faf9}
      .dp .empty{padding:.8rem .5rem}
      .dp .empty .ei{width:36px;height:36px;margin-bottom:.4rem}
      .dp .empty b{font-size:.8rem}
      .dp .empty p{font-size:.68rem}

      /* pending actions */
      .d-list{overflow:auto}
      .d-act{display:flex;align-items:center;gap:.6rem;padding:.3rem;border-radius:10px;text-decoration:none;color:var(--ink);transition:background .15s}
      .d-act:hover{background:#f3f6f5}
      .d-act .si{width:30px;height:30px;border-radius:9px}
      .d-act .t{flex:1;font-size:.74rem;font-weight:500}
      .d-act b{min-width:26px;text-align:center;padding:1px 8px;border-radius:999px;background:#eef3f1;font-size:.7rem}
      .d-act b.hot{background:var(--coral);color:#fff}

      /* small screens: stack and let the page scroll */
      @media(max-width:1099px){
        .d-r2,.d-r3{grid-template-columns:1fr}
        .pchip small{display:none}
      }
      @media(max-width:560px){.pchip span span{display:none}.pchip{padding:.2rem}}

      /* desktop / laptop: everything fits in one viewport */
      @media(min-width:1100px) and (min-height:620px){
        main{height:100vh;height:100dvh;overflow:hidden;display:flex;flex-direction:column}
        .top{flex:none}
        .dash{flex:1;min-height:0;grid-template-rows:auto auto minmax(0,1.15fr) minmax(0,1fr)}
        .d-kpis{grid-template-columns:repeat(5,minmax(0,1fr))}
        .cbox,.st-w{min-height:0}
      }
      /* shorter screens: show fewer table rows and tighten rows */
      @media(min-width:1100px) and (min-height:620px) and (max-height:899px){.dash tbody tr:nth-child(n+6){display:none}}
      @media(min-width:1100px) and (min-height:620px) and (max-height:760px){
        .dash tbody tr:nth-child(n+5){display:none}
        .d-act{padding:.2rem .3rem}
        .dp{padding:.55rem .8rem}
      }
      @media(min-width:1100px) and (min-height:620px) and (max-height:680px){.dash tbody tr:nth-child(n+4){display:none}}
    </style>

    <div class="dash">

      <div class="d-tool">
        <?php if ($pendingPay || $overdue): ?>
          <div class="d-alert" role="status">
            <span class="ic"><?= navIcon('clock', 14) ?></span>
            <span>
              <?php if ($pendingPay): ?><b><?= $pendingPay ?> payment<?= $pendingPay === 1 ? '' : 's' ?></b> waiting for review<?php endif; ?>
              <?php if ($pendingPay && $overdue): ?> · <?php endif; ?>
              <?php if ($overdue): ?><b><?= $overdue ?> guest<?= $overdue === 1 ? '' : 's' ?></b> past check-out time<?php endif; ?>
            </span>
            <?php if ($payUrl && $pendingPay): ?><a class="btn sm coral" href="<?= e($payUrl) ?>">Review</a><?php endif; ?>
          </div>
        <?php else: ?>
          <p class="d-note">Comparing the <?= e($rangeTitle) ?> with the period before it</p>
        <?php endif; ?>
        <?= tabsHtml(['7d' => 'Weekly', '30d' => 'Monthly', '12m' => 'Yearly'], $range, [], 'range') ?>
      </div>

      <div class="d-kpis"><?= implode('', $kpis) ?></div>

      <div class="d-r2">
        <div class="dp">
          <div class="dp-h">
            <div><h3><?= $isAdmin ? 'Booking &amp; revenue trend' : 'Booking trend' ?></h3><p>Created in the <?= e($rangeTitle) ?></p></div>
            <?php if ($isAdmin): ?>
              <div class="seg" role="group" aria-label="Chart data">
                <button type="button" class="on" data-mode="bookings">Bookings</button>
                <button type="button" data-mode="revenue">Revenue</button>
              </div>
            <?php endif; ?>
          </div>
          <div class="dp-b cbox" id="trendBox"></div>
        </div>

        <div class="dp">
          <div class="dp-h"><div><h3>Booking status</h3><p>All bookings by current status</p></div></div>
          <div class="dp-b st-w">
            <div class="st-d"><?= dashDonut($parts, $totalB) ?></div>
            <ul class="legend">
              <?php foreach ($parts as $p): ?>
                <li><i style="background:<?= e($p['color']) ?>"></i><?= e($p['label']) ?>
                  <b><?= (int)$p['n'] ?><small><?= $totalB ? round($p['n'] / $totalB * 100) : 0 ?>%</small></b></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <div class="occ2"><span>Room occupancy · <?= $occupied ?> of <?= count($rooms) ?> rooms</span><b><?= e($occPct) ?>%</b></div>
          <div class="obar"><i style="width:<?= (float)$occPct ?>%"></i></div>
        </div>
      </div>

      <div class="d-r3">
        <div class="dp">
          <div class="dp-h"><div><h3>Recent bookings</h3><p>Latest bookings</p></div>
            <?php if ($bookUrl): ?><a href="<?= e($bookUrl) ?>">View all</a><?php endif; ?></div>
          <div class="scroll">
          <table>
            <thead><tr><th>Guest</th><th>Room</th><th>Check-in</th><th>Check-out</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $x): ?>
            <tr>
              <td><span class="who"><?= avatar((string)($x['guestName'] ?? '?'), 22) ?><?= e($x['guestName'] ?? '-') ?></span></td>
              <td><?= e($x['roomTitle'] ?? '-') ?> <span class="muted">#<?= e($x['roomNo'] ?? '') ?></span></td>
              <td><?= e(fmtDay($x['checkIn'] ?? null)) ?></td>
              <td><?= e(fmtDay($x['checkOut'] ?? null)) ?></td>
              <td><?= badge((string)($x['status'] ?? '')) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$recent): ?><?= emptyRow(5, 'inbox', 'No bookings yet', 'New bookings will show up here.') ?><?php endif; ?>
            </tbody>
          </table>
          </div>
        </div>

        <div class="dp">
          <div class="dp-h"><div><h3>Needs attention</h3><p>Pending actions</p></div></div>
          <div class="dp-b d-list">
          <?php if ($allClear): ?>
            <?= emptyBlock('check', "You're all caught up", 'No pending actions right now.') ?>
          <?php else: foreach ($actions as $a): ?>
            <a class="d-act" href="<?= e($a[2] ?? '#') ?>">
              <span class="si <?= e($a[4]) ?>"><?= navIcon($a[3], 16) ?></span>
              <span class="t"><?= e($a[0]) ?></span>
              <b class="<?= $a[1] > 0 ? 'hot' : '' ?>"><?= (int)$a[1] ?></b>
            </a>
          <?php endforeach; endif; ?>
          </div>
        </div>
      </div>

    </div>

    <script>
// Trend chart: smooth, animated, with hover crosshair + tooltip
(function () {
  var D = <?= json_encode($chartData, $jf) ?>;
  var box = document.getElementById('trendBox');
  if (!box) return;
  var mode = 'bookings';
  var animate = true;          // play the draw-in only on first load / when switching tabs
  var G = null;                // geometry of the last draw, used by the hover handlers
  var COL = { bookings: '#123f3a', revenue: '#2b6cb0' };

  function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
  function fmt(v, money) {
    if (!money) return String(Math.round(v));
    return v >= 1000 ? '\u20B1' + (Math.round(v / 100) / 10) + 'k' : '\u20B1' + Math.round(v);
  }
  function full(v) {
    return '\u20B1' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  // Smooth curve through the points that never dips below or above the data
  function smooth(p) {
    var n = p.length;
    if (n < 2) return 'M' + p[0][0] + ',' + p[0][1];
    var m = [], t = [], i;
    for (i = 0; i < n - 1; i++) m[i] = (p[i + 1][1] - p[i][1]) / (p[i + 1][0] - p[i][0]);
    t[0] = m[0]; t[n - 1] = m[n - 2];
    for (i = 1; i < n - 1; i++) t[i] = (m[i - 1] * m[i] <= 0) ? 0 : (m[i - 1] + m[i]) / 2;
    for (i = 0; i < n - 1; i++) {
      if (m[i] === 0) { t[i] = 0; t[i + 1] = 0; continue; }
      var a = t[i] / m[i], b = t[i + 1] / m[i], s = a * a + b * b;
      if (s > 9) { var k = 3 / Math.sqrt(s); t[i] = k * a * m[i]; t[i + 1] = k * b * m[i]; }
    }
    var d = 'M' + p[0][0].toFixed(1) + ',' + p[0][1].toFixed(1);
    for (i = 0; i < n - 1; i++) {
      var dx = (p[i + 1][0] - p[i][0]) / 3;
      d += ' C' + (p[i][0] + dx).toFixed(1) + ',' + (p[i][1] + dx * t[i]).toFixed(1)
         + ' ' + (p[i + 1][0] - dx).toFixed(1) + ',' + (p[i + 1][1] - dx * t[i + 1]).toFixed(1)
         + ' ' + p[i + 1][0].toFixed(1) + ',' + p[i + 1][1].toFixed(1);
    }
    return d;
  }

  function draw() {
    var w = box.clientWidth, h = box.clientHeight;
    if (w < 60 || h < 60) return;
    var vals = D[mode] || [], n = vals.length, money = mode === 'revenue';
    if (!n) return;

    var max = Math.max.apply(null, vals.concat([0]));
    var rows = h < 150 ? 2 : 4, step;
    if (money) {
      if (max <= 0) { step = 100; }
      else {
        var raw = max / rows, mag = Math.pow(10, Math.floor(Math.log10(raw)));
        step = Math.ceil(raw / mag) * mag;
      }
    } else {
      step = Math.max(1, Math.ceil(max / rows));
    }
    var top = step * rows;
    var pl = money ? 46 : 28, pr = 14, pt = 10, pb = 20;
    var iw = w - pl - pr, ih = h - pt - pb, slot = iw / n;
    function cx(i) { return pl + slot * (i + 0.5); }
    function cy(v) { return pt + ih * (1 - v / top); }

    var c = COL[mode], gid = 'tg' + mode, cls = animate ? ' draw' : '';
    var s = '<svg viewBox="0 0 ' + w + ' ' + h + '" role="img" aria-label="Trend chart">';
    s += '<defs><linearGradient id="' + gid + '" x1="0" y1="0" x2="0" y2="1">'
       + '<stop offset="0" stop-color="' + c + '" stop-opacity=".28"/>'
       + '<stop offset="1" stop-color="' + c + '" stop-opacity="0"/></linearGradient></defs>';

    for (var g = 0; g <= rows; g++) {
      var y = cy(step * g);
      s += '<line x1="' + pl + '" x2="' + (w - pr) + '" y1="' + y.toFixed(1) + '" y2="' + y.toFixed(1)
         + '" stroke="#e6ecea" stroke-width="1"' + (g ? ' stroke-dasharray="3 4"' : '') + '/>';
      s += '<text x="' + (pl - 6) + '" y="' + (y + 3.5).toFixed(1) + '" text-anchor="end" class="ax">' + fmt(step * g, money) + '</text>';
    }

    var pts = vals.map(function (v, i) { return [cx(i), cy(v)]; });
    var base = pt + ih, line = smooth(pts);
    s += '<path class="ar' + cls + '" d="' + line + ' L' + cx(n - 1).toFixed(1) + ',' + base + ' L' + cx(0).toFixed(1) + ',' + base
       + ' Z" fill="url(#' + gid + ')"/>';
    s += '<path class="ln' + cls + '" pathLength="1" d="' + line + '" fill="none" stroke="' + c
       + '" stroke-width="2.4" stroke-linejoin="round" stroke-linecap="round"/>';

    // hover helpers (hidden until the pointer is over the chart)
    s += '<line class="hl" x1="0" x2="0" y1="' + pt + '" y2="' + base + '"/>';
    s += '<circle class="hd" r="9" fill="' + c + '" fill-opacity=".15"/>';
    s += '<circle class="hd hd2" r="4.5" fill="#fff" stroke="' + c + '" stroke-width="2.4"/>';

    var maxLabels = Math.max(2, Math.floor(iw / 52)), every = Math.ceil(n / maxLabels);
    D.labels.forEach(function (l, i) {
      if (i % every !== 0) return;
      s += '<text x="' + cx(i).toFixed(1) + '" y="' + (h - 5) + '" text-anchor="middle" class="ax">' + esc(l) + '</text>';
    });

    if (max <= 0) {
      s += '<text x="' + (pl + iw / 2) + '" y="' + (pt + ih / 2) + '" text-anchor="middle" class="nodata">No data in this period</text>';
    }
    box.innerHTML = s + '</svg><div class="tt" role="status"></div>';
    box.classList.remove('hover');
    animate = false;

    G = { w: w, h: h, pl: pl, slot: slot, n: n, vals: vals, money: money, c: c, cx: cx, cy: cy };
  }

  function show(i) {
    if (!G) return;
    var v = G.vals[i], x = G.cx(i), y = G.cy(v);
    var hl = box.querySelector('.hl'), tt = box.querySelector('.tt');
    if (!hl || !tt) return;
    hl.setAttribute('x1', x); hl.setAttribute('x2', x);
    box.querySelectorAll('.hd').forEach(function (d) { d.setAttribute('cx', x); d.setAttribute('cy', y); });
    box.classList.add('hover');

    var txt = G.money ? full(v) : v;
    var change = '';
    if (i > 0) {
      var diff = v - G.vals[i - 1];
      var dTxt = G.money ? full(Math.abs(diff)) : Math.abs(diff);
      change = diff > 0 ? '<span class="tt-c up">\u25B2 ' + dTxt + ' vs previous</span>'
             : diff < 0 ? '<span class="tt-c dn">\u25BC ' + dTxt + ' vs previous</span>'
             :            '<span class="tt-c eq">No change vs previous</span>';
    }
    tt.innerHTML = '<span class="tt-d">' + esc(D.tips[i]) + '</span>'
      + '<span class="tt-v"><i style="background:' + G.c + '"></i>' + txt
      + (G.money ? '' : '<small>booking' + (v === 1 ? '' : 's') + '</small>') + '</span>' + change;

    var tw = tt.offsetWidth, th = tt.offsetHeight;
    var left = x + 14;
    if (left + tw > G.w - 4) left = x - tw - 14;       // flip to the left near the right edge
    left = Math.max(4, left);
    var topPos = Math.min(Math.max(4, y - th - 12), G.h - th - 4);
    tt.style.left = left + 'px';
    tt.style.top = topPos + 'px';
    tt.classList.add('on');
  }

  function hide() {
    box.classList.remove('hover');
    var tt = box.querySelector('.tt');
    if (tt) tt.classList.remove('on');
  }

  function onMove(e) {
    if (!G) return;
    var r = box.getBoundingClientRect();
    var i = Math.round((e.clientX - r.left - G.pl) / G.slot - 0.5);
    show(Math.max(0, Math.min(G.n - 1, i)));
  }
  box.addEventListener('pointermove', onMove);
  box.addEventListener('pointerdown', onMove);
  box.addEventListener('pointerleave', hide);

  document.querySelectorAll('[data-mode]').forEach(function (b) {
    b.addEventListener('click', function () {
      mode = b.getAttribute('data-mode');
      document.querySelectorAll('[data-mode]').forEach(function (x) { x.classList.toggle('on', x === b); });
      animate = true;
      draw();
    });
  });

  if (window.ResizeObserver) { new ResizeObserver(draw).observe(box); }
  else { window.addEventListener('resize', draw); }
  draw();
})();
</script>
    <?php
    pageEnd();
}