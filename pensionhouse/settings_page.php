<?php

function audFetch(bool $isAdmin = true): ?array
{
    // Admin sees everyone. Staff only get their own activity (the API enforces this from the login token).
    $r = apiAuth($isAdmin ? 'audit-logs?limit=200' : 'audit-logs/mine?limit=200');
    return (apiOk($r) && is_array($r['data'])) ? $r['data'] : null;
}

function audIcon(string $key, int $size = 15): string
{
    $p = [
        'refresh'  => '<path d="M21 12a9 9 0 1 1-3-6.7"/><polyline points="21 3 21 9 15 9"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
    ];
    return '<svg class="ico" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . ($p[$key] ?? '') . '</svg>';
}

function audRoleLabel(string $role): string
{
    return match (strtolower($role)) {
        'admin' => 'Administrator',
        'staff' => 'Staff',
        'user'  => 'Guest',
        default => '',
    };
}

function audStatsHtml(array $s, bool $isAdmin = true): string
{
    if ($isAdmin) {
        $failed = (int)($s['failedLogins'] ?? 0);
        $cards = [
            ['Online now',    (int)($s['onlineNow'] ?? 0),    'guests.php', 'g'],
            ['Actions today', (int)($s['actionsToday'] ?? 0), 'clock',      'blue'],
            ['Failed logins', $failed,                        'x',          $failed > 0 ? 'coral' : 'g'],
        ];
    } else {
        $failed = (int)($s['failedAttempts'] ?? 0);
        $cards = [
            ['Actions today',   (int)($s['actionsToday'] ?? 0), 'clock',        'blue'],
            ['Last 7 days',     (int)($s['last7Days'] ?? 0),    'bookings.php', 'g'],
            ['Failed attempts', $failed,                        'x',            $failed > 0 ? 'coral' : 'g'],
        ];
    }
    $out = '';
    foreach ($cards as $c) {
        $out .= '<div class="ak"><span class="si ' . e($c[3]) . '">' . navIcon($c[2], 18) . '</span>'
              . '<div><small>' . e($c[0]) . '</small><b>' . (int)$c[1] . '</b></div></div>';
    }
    return $out;
}

function audOnlineHtml(array $online): string
{
    if (!$online) {
        return '<p class="muted a-none">No one is online right now.</p>';
    }
    $out = '';
    foreach ($online as $u) {
        $name = (string)($u['fullName'] ?? '?');
        $t    = strtotime((string)($u['lastSeenAt'] ?? ''));
        $when = $t ? 'Active ' . date('g:i A', $t) : 'Active now';
        $role = audRoleLabel((string)($u['role'] ?? ''));
        $out .= '<button type="button" class="ou" data-user="' . e($name) . '" title="Show this person\'s activity">'
              . '<span class="ou-av">' . avatar($name, 34) . '<i class="pdot"></i></span>'
              . '<span class="ou-t"><b>' . e($name) . '</b><small>' . e(trim($role . ' · ' . $when, ' ·')) . '</small></span>'
              . '</button>';
    }
    return $out;
}

function audChipsHtml(array $logs): string
{
    $labels = ['login' => 'Logins', 'guest' => 'Guests', 'payment' => 'Payments', 'room' => 'Rooms', 'booking' => 'Bookings', 'staff' => 'Staff'];
    $counts = [];
    foreach ($logs as $l) {
        $k = strtolower((string)($l['category'] ?? ''));
        if ($k !== '') $counts[$k] = ($counts[$k] ?? 0) + 1;
    }
    $chip = fn(string $k, string $label, int $n, bool $on = false) =>
        '<a href="#" class="' . ($on ? 'on' : '') . '" data-acat="' . e($k) . '">' . e($label) . '<em>' . $n . '</em></a>';

    $out = $chip('ALL', 'All', count($logs), true);
    foreach ($labels as $k => $label) {
        if (isset($counts[$k])) $out .= $chip($k, $label, $counts[$k]);
    }
    foreach ($counts as $k => $n) {
        if (!isset($labels[$k])) $out .= $chip($k, ucfirst($k), $n);
    }
    return $out;
}

function audRowsHtml(array $logs, bool $isAdmin = true): string
{
    $out = '';
    foreach ($logs as $l) {
        $cat    = (string)($l['category'] ?? '');
        $key    = strtolower($cat);
        $name   = trim((string)($l['userName'] ?? ''));
        $role   = audRoleLabel((string)($l['role'] ?? ''));
        $det    = (string)($l['details'] ?? '');
        $ip     = (string)($l['ipAddress'] ?? '');
        $failed = !empty($l['isFailed']);
        $when   = $l['createdAt'] ?? null;
        $t      = $when ? strtotime((string)$when) : false;
        $hay    = mb_strtolower($name . ' ' . $det . ' ' . $cat . ' ' . $ip . ($failed ? ' failed' : ''));
        $known  = in_array($key, ['login', 'guest', 'payment', 'room', 'booking', 'staff', 'settings'], true) ? 'ac-' . $key : 'ac-other';

        $out .= '<tr data-cat="' . e($key) . '" data-text="' . e($hay) . '"'
              . ' data-time="' . e(fmtDate($when)) . '" data-user="' . e($name) . '" data-role="' . e($role) . '"'
              . ' data-type="' . e($cat . ($failed ? ' (failed)' : '')) . '" data-details="' . e($det) . '" data-ip="' . e($ip) . '">'
              . '<td class="a-time" title="' . e(fmtDate($when)) . '"><b>' . e($t ? date('M d', $t) : '-') . '</b><small>' . e($t ? date('h:i A', $t) : '') . '</small></td>';

        if ($name !== '') {
            $out .= '<td><button type="button" class="ulink" data-user="' . e($name) . '" title="Show only this person\'s activity">'
                  . avatar($name, 26) . '<span><b>' . e($name) . '</b>' . ($role !== '' ? '<small>' . e($role) . '</small>' : '') . '</span></button></td>';
        } else {
            $out .= '<td><span class="muted">Unknown</span></td>';
        }

        $out .= '<td><span class="ac ' . e($known) . '">' . e($cat !== '' ? $cat : 'Other') . '</span>'
              . ($failed ? ' <span class="ac ac-failed">Failed</span>' : '') . '</td>'
              . '<td class="a-det">' . e($det) . '</td>'
              . '<td class="a-ip">' . e($ip !== '' ? $ip : '-') . '</td></tr>';
    }
    if (!$logs) {
        $out .= emptyRow(5, 'clock', 'No activity yet', $isAdmin
            ? 'Actions by admins and staff will be listed here.'
            : 'The things you do while signed in will be listed here.');
    }
    return $out . noMatchRow(5);
}

function audPayload(array $d, bool $isAdmin = true): array
{
    $logs   = array_values(array_filter($d['logs'] ?? [], 'is_array'));
    $online = $isAdmin ? array_values(array_filter($d['online'] ?? [], 'is_array')) : [];
    $stats  = is_array($d['stats'] ?? null) ? $d['stats'] : [];
    return [
        'stats'       => audStatsHtml($stats, $isAdmin),
        'online'      => $isAdmin ? audOnlineHtml($online) : '',
        'onlineCount' => count($online),
        'chips'       => audChipsHtml($logs),
        'rows'        => audRowsHtml($logs, $isAdmin),
        'updated'     => date('g:i:s A'),
    ];
}

// ---------- Page ----------

function settingsPage(array $user): void
{
    $isAdmin = ($user['role'] ?? '') === 'admin';
    $phoneOk = fn(string $s) => $s === '' || preg_match('/^[0-9+\-\s]{7,20}$/', $s);

    // Live refresh for the Audit logs tab (called by the page's own JavaScript)
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['audit'] ?? '') === '1') {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $d = audFetch($isAdmin);
        if ($d === null) {
            http_response_code(502);
            echo json_encode(['error' => 'Could not load the audit log.']);
        } else {
            echo json_encode(audPayload($d, $isAdmin), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        checkCsrf();
        $act = $_POST['act'] ?? '';

        // Staff may edit their own profile and change their own password; everything else is admin only
        if (!$isAdmin && !in_array($act, ['profile', 'password_otp', 'password'], true)) {
            flash('err', 'Only an admin can do that.');
            header('Location: settings.php');
            exit;
        }

        if ($act === 'profile') {
            $name    = trim($_POST['full_name'] ?? '');
            $contact = trim($_POST['contact'] ?? '');
            if ($name === '' || mb_strlen($name) > 100) {
                flash('err', 'Enter your full name (up to 100 characters).');
            } elseif (!$isAdmin && !$phoneOk($contact)) {
                flash('err', 'Enter a valid mobile number, or leave it blank.');
            } else {
                $payload = ['fullName' => $name];
                if (!$isAdmin) $payload['contactNo'] = $contact !== '' ? $contact : null;
                $res = apiAuth('account/me', 'PUT', $payload);
                if (apiOk($res)) {
                    $_SESSION['user']['fullName'] = $name;
                    flash('ok', 'Profile updated.');
                } else {
                    flash('err', apiMessage($res, 'Could not update your profile.'));
                }
            }
        }

        // Step 1: ask the API to email a 6-digit code
        if ($act === 'password_otp') {
            $res = apiAuth('account/password/otp', 'POST', []);
            $d   = is_array($res['data']) ? $res['data'] : [];
            if (apiOk($res)) {
                $_SESSION['pw_otp'] = [
                    'email'   => (string)($d['maskedEmail'] ?? ''),
                    'resend'  => time() + (int)($d['resendInSeconds'] ?? 60),
                    'expires' => time() + (int)($d['expiresInSeconds'] ?? 600),
                ];
                flash('ok', (string)($d['message'] ?? 'We sent a 6-digit code to your email.'));
            } else {
                if (isset($_SESSION['pw_otp']) && (int)($d['retryAfterSeconds'] ?? 0) > 0) {
                    $_SESSION['pw_otp']['resend'] = time() + min(60, (int)$d['retryAfterSeconds']);
                }
                flash('err', apiMessage($res, 'Could not send the code. Please try again.'));
            }
        }

        // Step 2: send the code and the new password to the API
        if ($act === 'password') {
            $code = preg_replace('/\D/', '', (string)($_POST['otp'] ?? ''));
            $new  = (string)($_POST['new_password'] ?? '');
            $cfm  = (string)($_POST['confirm_password'] ?? '');
            if (strlen($code) !== 6) {
                flash('err', 'Enter the 6-digit code from your email.');
            } elseif ($new === '' || $cfm === '') {
                flash('err', 'Enter and confirm your new password.');
            } elseif (mb_strlen($new) < 8) {
                flash('err', 'The new password must be at least 8 characters.');
            } elseif (mb_strlen($new) > 32) {
                flash('err', 'The new password can be at most 32 characters.');
            } elseif ($new !== $cfm) {
                flash('err', 'The new password and confirmation do not match.');
            } else {
                $res = apiAuth('account/password', 'PATCH', ['otp' => $code, 'newPassword' => $new]);
                if (apiOk($res)) {
                    unset($_SESSION['pw_otp']);
                    flash('ok', 'Password changed. Use your new password the next time you sign in.');
                } else {
                    if (is_array($res['data']) && !empty($res['data']['codeInvalidated'])) {
                        unset($_SESSION['pw_otp']);   // the code is dead, so go back to the "send code" step
                    }
                    flash('err', apiMessage($res, 'Could not change the password.'));
                }
            }
        }

        if ($act === 'business') {
            $gNum  = trim($_POST['gcash_number'] ?? '');
            $bCont = trim($_POST['business_contact'] ?? '');
            if (!$phoneOk($gNum) || !$phoneOk($bCont)) {
                flash('err', 'Enter valid phone numbers, or leave them blank.');
            } else {
                $res = apiAuth('settings', 'PUT', [
                    'gcashNumber'     => $gNum,
                    'gcashName'       => trim($_POST['gcash_name'] ?? ''),
                    'businessName'    => trim($_POST['business_name'] ?? ''),
                    'businessAddress' => trim($_POST['business_address'] ?? ''),
                    'businessContact' => $bCont,
                ]);
                apiOk($res) ? flash('ok', 'Settings saved.')
                            : flash('err', apiMessage($res, 'Could not save the settings.'));
            }
        }

        if ($act === 'staff_add') {
            $name    = trim($_POST['full_name'] ?? '');
            $email   = strtolower(trim($_POST['email'] ?? ''));
            $contact = trim($_POST['contact'] ?? '');
            $pw      = $_POST['password'] ?? '';
            if ($name === '' || mb_strlen($name) > 100) {
                flash('err', 'Enter the staff member\'s full name.');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
                flash('err', 'Enter a valid email address.');
            } elseif (!$phoneOk($contact)) {
                flash('err', 'Enter a valid mobile number, or leave it blank.');
            } elseif (strlen($pw) < 8 || strlen($pw) > 72) {
                flash('err', 'The password must be 8 to 72 characters.');
            } else {
                $res = apiAuth('staff-accounts', 'POST', [
                    'fullName' => $name, 'email' => $email,
                    'contactNo' => $contact !== '' ? $contact : null, 'password' => $pw,
                ]);
                apiOk($res) ? flash('ok', 'Staff account created. Give them the email and password you set.')
                            : flash('err', apiMessage($res, 'Could not create the staff account.'));
            }
        }

        if ($act === 'staff_active') {
            $id  = (int)($_POST['id'] ?? 0);
            $res = apiAuth("staff-accounts/$id/active", 'PATCH', ['isActive' => ($_POST['value'] ?? '0') === '1']);
            apiOk($res) ? flash('ok', 'Staff account updated.')
                        : flash('err', apiMessage($res, 'Could not update the staff account.'));
        }

        if ($act === 'staff_reset') {
            $id = (int)($_POST['id'] ?? 0);
            $pw = $_POST['new_password'] ?? '';
            if (strlen($pw) < 8 || strlen($pw) > 72) {
                flash('err', 'The password must be 8 to 72 characters.');
            } else {
                $res = apiAuth("staff-accounts/$id/password", 'PATCH', ['newPassword' => $pw]);
                apiOk($res) ? flash('ok', 'Password reset. Give the new password to the staff member.')
                            : flash('err', apiMessage($res, 'Could not reset the password.'));
            }
        }

        // Come back to the tab the action was made in
        $hash = str_starts_with($act, 'staff_') ? '#staff' : '#general';
        header('Location: settings.php' . $hash);
        exit;
    }

    // ----- Load data -----
    $me      = apiAuth('account/me');
    $contact = is_array($me['data']) ? ($me['data']['contactNo'] ?? '') : '';
    $cfg = []; $staff = [];
    if ($isAdmin) {
        $s = apiAuth('settings');       $cfg   = is_array($s['data']) ? $s['data'] : [];
        $t = apiAuth('staff-accounts'); $staff = is_array($t['data']) ? $t['data'] : [];
    }
    $audit = audFetch($isAdmin);                 // admin: everyone's activity, staff: their own
    $pay   = audPayload($audit ?? [], $isAdmin);
    $roleLabel = $isAdmin ? 'Administrator' : 'Staff member';

    pageStart('Settings', $user, $isAdmin ? 'Your account, payment details, staff accounts and audit logs.' : 'Your account details and your recent activity.');
    ?>
    <style>
      .setcols{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;align-items:start;max-width:1100px}
      .setcols.one{grid-template-columns:minmax(0,480px)}
      @media(max-width:860px){.setcols{grid-template-columns:1fr}}
      .card input,.m-body input{width:100%} 
      .sethead{margin:0 0 .25rem;font-size:1rem;font-weight:600}
      .setsub{margin:0 0 1rem;font-size:.82rem;color:var(--soft)}
      .setfields{display:grid;gap:.4rem}
      .setfoot{display:flex;justify-content:flex-end;margin-top:1.1rem;padding-top:1rem;border-top:1px solid #ece7d6}
      .rolechip{display:inline-block;padding:.15rem .65rem;border-radius:999px;background:#e4eee9;color:#123f3a;font-size:.75rem;font-weight:500}
      .hint{margin:.3rem 0 0;font-size:.74rem;color:var(--soft)}
      .wide2{max-width:980px}
      .twocol{display:grid;grid-template-columns:1fr 1fr;gap:.2rem .9rem}
      .twocol .full{grid-column:1/-1}
      @media(max-width:640px){.twocol{grid-template-columns:1fr}}

      /* ---------- Change password (email code) ---------- */
      .pw-mail{display:flex;align-items:center;gap:.6rem;margin:0 0 1rem;padding:.65rem .8rem;border:1px solid var(--line);border-radius:12px;background:var(--cream);font-size:.82rem;word-break:break-all}
      .pw-mail .ico{flex-shrink:0;color:var(--g)}
      .otp-in{font-size:1.5rem;font-weight:600;letter-spacing:.55em;text-align:center;padding-left:.55em;font-variant-numeric:tabular-nums}
      .pw-foot{justify-content:space-between;gap:.6rem;flex-wrap:wrap}
      .pw-show{display:flex;align-items:center;gap:.45rem;margin-top:.55rem;font-size:.76rem;font-weight:400;cursor:pointer}
      .pw-show input{width:auto!important}
      .pw-hint{min-height:1.1em;margin:.3rem 0 0;font-size:.74rem}
      .pw-hint.good{color:#1f7a4d}
      .pw-hint.bad{color:#d14a32}
      .pw-timer{display:block;margin-top:.2rem;font-weight:500}

      /* ---------- Tabs ---------- */
      .stabs{display:flex;gap:.2rem;margin-bottom:1.1rem;box-shadow:inset 0 -1px 0 #ece7d6;overflow:visible}
      @media(max-width:640px){
        .stabs{overflow-x:auto;overflow-y:hidden;scrollbar-width:none}
        .stabs::-webkit-scrollbar{display:none}
      }
      .stabs button{display:inline-flex;align-items:center;gap:.5rem;padding:.65rem 1rem;border:0;border-bottom:2px solid transparent;background:none;font:inherit;font-size:.84rem;font-weight:500;color:var(--soft);cursor:pointer;white-space:nowrap;transition:color .15s}
      .stabs button:hover{color:var(--g)}
      .stabs button.on{color:var(--g);border-bottom-color:var(--coral)}
      .stabs button:focus-visible{outline:2px solid var(--coral);outline-offset:-2px;border-radius:6px}
      .stabs em{font-style:normal;font-size:.64rem;padding:1px 8px;border-radius:999px;background:#e5ece9}
      .stabs em.live{background:#d8f5d0;color:#166534}
      .stabs em.live::before{content:"";display:inline-block;width:6px;height:6px;margin-right:5px;border-radius:50%;background:#1f9d5a;vertical-align:1px}
      .sp-head{display:flex;justify-content:space-between;align-items:center;gap:.8rem;flex-wrap:wrap;padding:1rem 1.25rem .4rem}
      .sp-head h3{margin:0;font-size:.95rem;font-weight:600}

      /* ---------- Audit logs ---------- */
      .aud-head{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:.8rem}
      .aud-head h2{margin:0;font-size:1rem;font-weight:600}
      .aud-head p{margin:.15rem 0 0;font-size:.78rem;color:var(--soft)}
      .aud-tools{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
      .a-live{display:inline-flex;align-items:center;gap:.45rem;font-size:.72rem;color:var(--soft);padding:.3rem .7rem;border:1px solid var(--line);border-radius:999px;background:#fff}
      .a-live i{width:8px;height:8px;border-radius:50%;background:#1f9d5a;box-shadow:0 0 0 0 rgba(31,157,90,.5);animation:alive 2s infinite}
      .a-live.off i{background:#9ca3af;animation:none}
      @keyframes alive{70%{box-shadow:0 0 0 6px rgba(31,157,90,0)}100%{box-shadow:0 0 0 0 rgba(31,157,90,0)}}
      #aRefresh.busy svg{animation:aspin .8s linear infinite}
      @keyframes aspin{to{transform:rotate(360deg)}}

      .aud-k{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:.65rem;margin-bottom:.8rem}
      .ak{display:flex;align-items:center;gap:.65rem;background:#fff;border:1px solid #e3e9e7;border-radius:14px;padding:.6rem .85rem;box-shadow:0 1px 2px rgba(15,61,58,.05)}
      .ak .si{width:34px;height:34px;border-radius:10px}
      .ak small{display:block;font-size:.68rem;font-weight:500;color:var(--soft)}
      .ak b{display:block;font-size:1.25rem;font-weight:600;line-height:1.15}

      .apn{background:#fff;border:1px solid #e3e9e7;border-radius:16px;padding:.85rem 1rem;margin-bottom:.8rem;box-shadow:0 1px 2px rgba(15,61,58,.05)}
      .apn-h{display:flex;justify-content:space-between;align-items:center;gap:.6rem;flex-wrap:wrap;margin-bottom:.65rem}
      .apn-h h3{margin:0;font-size:.85rem;font-weight:600}
      .apn-h span{font-size:.68rem;color:var(--soft)}

      .a-online{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:.55rem}
      .a-none{margin:0}
      .ou{display:flex;align-items:center;gap:.65rem;width:100%;padding:.5rem .65rem;border:1px solid #e3e9e7;border-radius:12px;background:#fff;font:inherit;color:var(--ink);text-align:left;cursor:pointer;transition:background .15s,border-color .15s,transform .15s}
      .ou:hover{background:#f6faf8;border-color:#b9d1ca;transform:translateY(-1px)}
      .ou:focus-visible{outline:2px solid var(--coral);outline-offset:2px}
      .ou-av{position:relative;flex-shrink:0;display:inline-flex}
      .pdot{position:absolute;right:-1px;bottom:-1px;width:11px;height:11px;border-radius:50%;background:#1f9d5a;border:2px solid #fff}
      .pdot::after{content:"";position:absolute;inset:-2px;border-radius:50%;background:#1f9d5a;opacity:.45;animation:apulse 1.8s ease-out infinite}
      @keyframes apulse{to{transform:scale(2.3);opacity:0}}
      .ou-t{min-width:0}
      .ou-t b{display:block;font-size:.8rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
      .ou-t small{display:block;font-size:.68rem;color:var(--soft)}

      .a-bar{display:flex;justify-content:space-between;align-items:center;gap:.6rem;flex-wrap:wrap;margin-bottom:.6rem}
      .a-bar .tabs a{padding:.3rem .85rem;font-size:.72rem}
      .a-bar .search input{padding:.4rem .75rem .4rem 2.1rem;width:250px}
      @media(max-width:700px){.a-bar .search,.a-bar .search input{width:100%}}

      .a-tbl{overflow-x:auto}
      .a-tbl table{border:0;border-radius:0;font-size:.76rem}
      .a-tbl th{padding:.4rem .7rem;font-size:.6rem}
      .a-tbl td{padding:.45rem .7rem}
      .a-time{white-space:nowrap}
      .a-time b{display:block;font-size:.76rem;font-weight:600;line-height:1.2}
      .a-time small{display:block;font-size:.66rem;color:var(--soft);line-height:1.2}
      .ulink{display:inline-flex;align-items:center;gap:.5rem;padding:.15rem .4rem .15rem .15rem;margin:-.15rem;border:0;border-radius:999px;background:none;font:inherit;color:var(--ink);text-align:left;cursor:pointer}
      .ulink:hover{background:#eef3f1}
      .ulink:focus-visible{outline:2px solid var(--coral);outline-offset:1px}
      .ulink b{display:block;font-size:.76rem;font-weight:600;line-height:1.2}
      .ulink small{display:block;font-size:.64rem;color:var(--soft);line-height:1.2}
      .a-det{min-width:200px}
      .a-ip{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:.7rem;color:var(--soft);white-space:nowrap}
      .ac{display:inline-block;padding:2px 10px;border-radius:999px;font-size:.66rem;font-weight:500;white-space:nowrap}
      .ac-login{background:#e0ebf8;color:#1f4f86}
      .ac-guest{background:#efe5f8;color:#5b2f8f}
      .ac-payment{background:#d8f5d0;color:#166534}
      .ac-room{background:#fdf0cf;color:#8a5200}
      .ac-booking{background:#fde4de;color:#b23a22}
      .ac-staff{background:#e3efec;color:#123f3a}
      .ac-settings,.ac-other{background:#e5e7eb;color:#4b5563}
      .ac-failed{background:#fecaca;color:#b91c1c}
      .a-foot{display:flex;justify-content:space-between;align-items:center;gap:.6rem;margin-top:.7rem;padding-top:.7rem;border-top:1px solid #ece7d6;font-size:.72rem;color:var(--soft)}
      .a-foot[hidden]{display:none}
      @media(prefers-reduced-motion:reduce){.pdot::after,.a-live i,#aRefresh.busy svg{animation:none}.ou{transition:none}}
    </style>

    <div class="stabs" id="setTabs" role="tablist" aria-label="Settings sections">
      <button type="button" role="tab" data-stab="general" class="on" aria-selected="true"><?= navIcon('settings.php', 16) ?>General</button>
      <?php if ($isAdmin): ?>
      <button type="button" role="tab" data-stab="staff" aria-selected="false"><?= navIcon('walkin.php', 16) ?>Staff accounts<em><?= count($staff) ?></em></button>
      <button type="button" role="tab" data-stab="audit" aria-selected="false"><?= navIcon('clock', 16) ?>Audit logs<em class="live" id="tabOnline"><?= (int)$pay['onlineCount'] ?></em></button>
      <?php else: ?>
      <button type="button" role="tab" data-stab="audit" aria-selected="false"><?= navIcon('clock', 16) ?>My activity</button>
      <?php endif; ?>
    </div>
    <section data-spane="general">

    <div class="setcols">
      <form method="post" class="card"><?= csrfField() ?>
        <input type="hidden" name="act" value="profile">
        <h2 class="sethead">My profile</h2>
        <p class="setsub">Your name appears in the sidebar.</p>
        <div class="setfields">
          <div><label for="sn">Full name</label>
            <input id="sn" name="full_name" required maxlength="100" value="<?= e($user['fullName'] ?? '') ?>"></div>
          <?php if (!$isAdmin): ?>
          <div><label for="sc">Mobile number</label>
            <input id="sc" name="contact" maxlength="20" inputmode="tel" placeholder="09123456789" value="<?= e($contact) ?>"></div>
          <?php endif; ?>
          <div><label for="se">Email</label>
            <input id="se" value="<?= e($user['email'] ?? '') ?>" disabled>
            <p class="hint">Your email is your login, so it can't be changed here.</p></div>
          <div><label>Role</label><span class="rolechip"><?= e($roleLabel) ?></span></div>
        </div>
        <div class="setfoot"><button class="btn coral">Save profile</button></div>
      </form>

      <?php
        $pwOtp = $_SESSION['pw_otp'] ?? null;
        if ($pwOtp && (int)($pwOtp['expires'] ?? 0) <= time()) { unset($_SESSION['pw_otp']); $pwOtp = null; }
        $pwLeft = $pwOtp ? max(0, (int)$pwOtp['expires'] - time()) : 0;
        $pwWait = $pwOtp ? max(0, (int)$pwOtp['resend'] - time()) : 0;
        $mailIcon = '<svg class="ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>';
      ?>

      <?php if (!$pwOtp): ?>
      <div class="card">
        <h2 class="sethead">Change password</h2>
        <p class="setsub">To keep your account safe, we’ll email a 6-digit code to the address below before you can set a new password.</p>
        <div class="pw-mail"><?= $mailIcon ?><span><?= e($user['email'] ?? '') ?></span></div>
        <form method="post"><?= csrfField() ?>
          <input type="hidden" name="act" value="password_otp">
          <div class="setfoot"><button class="btn coral">Send verification code</button></div>
        </form>
      </div>
      <?php else: ?>
      <div class="card">
        <form method="post" id="pwForm" autocomplete="off"><?= csrfField() ?>
          <input type="hidden" name="act" value="password">
          <h2 class="sethead">Change password</h2>
          <p class="setsub">
            Enter the code we sent to <b><?= e($pwOtp['email'] !== '' ? $pwOtp['email'] : 'your email') ?></b>.
            <span class="pw-timer" id="pwLeft"></span>
          </p>
          <div class="setfields">
            <div><label for="po">6-digit code</label>
              <input id="po" class="otp-in" name="otp" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000"></div>
            <div><label for="pn">New password</label>
              <input id="pn" type="password" name="new_password" required minlength="8" maxlength="72" autocomplete="new-password"></div>
            <div><label for="pf">Confirm new password</label>
              <input id="pf" type="password" name="confirm_password" required minlength="8" maxlength="72" autocomplete="new-password">
              <p class="pw-hint" id="pwHint" aria-live="polite"></p>
              <label class="pw-show"><input type="checkbox" id="pwShow"> Show passwords</label></div>
          </div>
          <div class="setfoot pw-foot">
            <button type="submit" form="pwResend" class="btn ghost" id="pwResendBtn"<?= $pwWait > 0 ? ' disabled' : '' ?>>Resend code</button>
            <button class="btn coral">Update password</button>
          </div>
        </form>
        <form method="post" id="pwResend"><?= csrfField() ?><input type="hidden" name="act" value="password_otp"></form>
      </div>
      <script>
      (function () {
        var otp = document.getElementById('po');
        if (!otp) return;
        var left = <?= (int)$pwLeft ?>, wait = <?= (int)$pwWait ?>;
        var leftEl = document.getElementById('pwLeft'), btn = document.getElementById('pwResendBtn');
        var pn = document.getElementById('pn'), pf = document.getElementById('pf');
        var hint = document.getElementById('pwHint'), show = document.getElementById('pwShow');

        otp.addEventListener('input', function () { this.value = this.value.replace(/\D/g, '').slice(0, 6); });

        function fmt(s) { var m = Math.floor(s / 60), r = s % 60; return m + ':' + (r < 10 ? '0' : '') + r; }
        function tick() {
          leftEl.textContent = left > 0 ? 'This code expires in ' + fmt(left) + '.' : 'This code has expired. Request a new one.';
          btn.disabled = wait > 0;
          btn.textContent = wait > 0 ? 'Resend code (' + wait + 's)' : 'Resend code';
          if (left > 0) left--;
          if (wait > 0) wait--;
        }
        tick();
        setInterval(tick, 1000);

        show.addEventListener('change', function () { pn.type = pf.type = this.checked ? 'text' : 'password'; });

        function match() {
          if (!pf.value) { hint.textContent = ''; hint.className = 'pw-hint'; return; }
          var ok = pn.value === pf.value;
          hint.textContent = ok ? 'Passwords match.' : 'Passwords do not match yet.';
          hint.className = 'pw-hint ' + (ok ? 'good' : 'bad');
        }
        pn.addEventListener('input', match);
        pf.addEventListener('input', match);
      })();
      </script>
      <?php endif; ?>
    </div>

    <?php if ($isAdmin): ?>
    <form method="post" class="card wide2"><?= csrfField() ?>
      <input type="hidden" name="act" value="business">
      <h2 class="sethead">Business and GCash details</h2>
      <p class="setsub">Guests see the GCash details when they pay, so keep them up to date.</p>
      <div class="twocol">
        <div><label for="gn1">GCash number</label>
          <input id="gn1" name="gcash_number" maxlength="20" inputmode="tel" placeholder="09123456789" value="<?= e($cfg['gcashNumber'] ?? '') ?>"></div>
        <div><label for="gn2">GCash account name</label>
          <input id="gn2" name="gcash_name" maxlength="100" value="<?= e($cfg['gcashName'] ?? '') ?>"></div>
        <div><label for="bn1">Business name</label>
          <input id="bn1" name="business_name" maxlength="100" value="<?= e($cfg['businessName'] ?? '') ?>"></div>
        <div><label for="bn2">Contact number</label>
          <input id="bn2" name="business_contact" maxlength="20" inputmode="tel" value="<?= e($cfg['businessContact'] ?? '') ?>"></div>
        <div class="full"><label for="bn3">Address</label>
          <input id="bn3" name="business_address" maxlength="300" value="<?= e($cfg['businessAddress'] ?? '') ?>"></div>
      </div>
      <div class="setfoot"><button class="btn coral">Save details</button></div>
    </form>
    <?php endif; ?>
    </section>

    <?php if ($isAdmin): ?>
    <!-- ================= Staff accounts ================= -->
    <section data-spane="staff" hidden>
    <div class="panel wide2" style="padding:0;overflow:hidden">
      <div class="sp-head">
        <h3>Staff accounts</h3>
        <button type="button" class="btn coral sm" data-open="#d-staff-new"><?= navIcon('plus', 14) ?>Add staff</button>
      </div>
      <div class="scroll">
      <table style="border:0;border-radius:0">
        <tr><th>Staff</th><th>Mobile</th><th>Added</th><th>Status</th><th>Actions</th></tr>
        <?php foreach ($staff as $s): $sid = (int)$s['id']; ?>
        <tr>
          <td><span class="who"><?= avatar($s['fullName'], 32) ?><span><b><?= e($s['fullName']) ?></b><br><span class="muted"><?= e($s['email']) ?></span></span></span></td>
          <td><?= e($s['contactNo'] ?? '-') ?></td>
          <td><?= e(fmtDay($s['createdAt'])) ?></td>
          <td><?= $s['isActive'] ? badge('APPROVED') : badge('CANCELLED') ?></td>
          <td><div class="acts">
            <button type="button" class="btn sm ghost" data-open="#sr<?= $sid ?>"><?= navIcon('edit', 14) ?>Reset password</button>
            <form method="post"<?= confirmAttr(
                ($s['isActive'] ? 'Disable ' : 'Enable ') . $s['fullName'] . '\'s account?' . ($s['isActive'] ? ' They will not be able to log in.' : ''),
                $s['isActive'] ? 'Disable staff' : 'Enable staff', $s['isActive'] ? 'Disable' : 'Enable', $s['isActive'] ? 'red' : 'green') ?>>
              <?= csrfField() ?>
              <input type="hidden" name="act" value="staff_active">
              <input type="hidden" name="id" value="<?= $sid ?>">
              <input type="hidden" name="value" value="<?= $s['isActive'] ? '0' : '1' ?>">
              <button class="btn sm <?= $s['isActive'] ? 'gray' : 'green' ?>"><?= $s['isActive'] ? 'Disable' : 'Enable' ?></button>
            </form>
          </div></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$staff): ?><?= emptyRow(5, 'guests.php', 'No staff accounts yet', 'Use "Add staff" to create the first one.') ?><?php endif; ?>
      </table>
      </div>
    </div>
    </section>
    <?php endif; ?>

    <!-- ================= Audit logs (admin: everyone, staff: their own) ================= -->
    <section data-spane="audit" hidden>
      <div class="aud-head">
        <div>
          <h2><?= $isAdmin ? 'Audit logs' : 'My activity' ?></h2>
          <p><?= $isAdmin
              ? 'See who is online and what each admin and staff member did.'
              : 'Everything you did while signed in. The admin can also see this log.' ?></p>
        </div>
        <div class="aud-tools">
          <span class="a-live" id="aLive" role="status"><i></i><span id="aUpd">Live · updated <?= e($pay['updated']) ?></span></span>
          <button type="button" class="btn sm ghost" id="aRefresh"><?= audIcon('refresh', 14) ?>Refresh</button>
          <button type="button" class="btn sm ghost" id="aExport"><?= audIcon('download', 14) ?>Export CSV</button>
        </div>
      </div>

      <div class="note" id="aErr" role="alert"<?= $audit === null ? '' : ' hidden' ?>>
        <?= navIcon('x', 16) ?><span><?= $isAdmin ? 'Couldn\'t load the audit log.' : 'Couldn\'t load your activity.' ?> Check that the API is running and has the new audit endpoint.</span>
      </div>

      <div class="aud-k" id="aStats"><?= $pay['stats'] ?></div>

      <?php if ($isAdmin): ?>
      <div class="apn">
        <div class="apn-h"><h3>Online users</h3><span>Active in the last 5 minutes. Select a person to see their activity.</span></div>
        <div class="a-online" id="aOnline"><?= $pay['online'] ?></div>
      </div>
      <?php endif; ?>

      <div class="apn" id="aLog">
        <div class="apn-h"><h3>Activity log</h3><span>Newest first</span></div>
        <div class="a-bar">
          <div class="tabs" id="aChips" role="group" aria-label="Filter the log by type"><?= $pay['chips'] ?></div>
          <div class="search"><?= searchIcon() ?><input id="aSearch" placeholder="Search user, action or IP" aria-label="Search the activity log" autocomplete="off"></div>
        </div>
        <div class="a-tbl">
          <table>
            <thead><tr><th>Time</th><th>User</th><th>Type</th><th>Action</th><th>IP address</th></tr></thead>
            <tbody id="aRows"><?= $pay['rows'] ?></tbody>
          </table>
        </div>
        <div class="a-foot" id="aFoot" hidden>
          <span id="aInfo"></span>
          <button type="button" class="btn sm ghost" id="aMore">Show more</button>
        </div>
      </div>
    </section>

    <?php if ($isAdmin): ?>
    <?php modalStart('d-staff-new', 'Add staff', 'sm'); ?>
      <form method="post" autocomplete="off"><?= csrfField() ?>
        <input type="hidden" name="act" value="staff_add">
        <label for="na1">Full name</label><input id="na1" name="full_name" required maxlength="100">
        <label for="na2">Email (their login)</label><input id="na2" type="email" name="email" required maxlength="150" autocomplete="off">
        <label for="na3">Mobile number (optional)</label><input id="na3" name="contact" maxlength="20" inputmode="tel">
        <label for="na4">Starting password</label><input id="na4" type="password" name="password" required minlength="8" maxlength="72" autocomplete="new-password">
        <p class="hint">At least 8 characters. Tell the staff member their password in person.</p>
        <div class="m-actions">
          <button type="button" class="btn ghost" data-close>Cancel</button>
          <button class="btn coral">Create account</button>
        </div>
      </form>
    <?php modalEnd(); ?>

    <?php foreach ($staff as $s): $sid = (int)$s['id'];
        modalStart('sr' . $sid, 'Reset password: ' . $s['fullName'], 'sm'); ?>
      <form method="post" autocomplete="off"><?= csrfField() ?>
        <input type="hidden" name="act" value="staff_reset">
        <input type="hidden" name="id" value="<?= $sid ?>">
        <label for="rp<?= $sid ?>">New password</label>
        <input id="rp<?= $sid ?>" type="password" name="new_password" required minlength="8" maxlength="72" autocomplete="new-password">
        <p class="hint">At least 8 characters. Tell the staff member their new password in person.</p>
        <div class="m-actions">
          <button type="button" class="btn ghost" data-close>Cancel</button>
          <button class="btn coral">Reset password</button>
        </div>
      </form>
    <?php modalEnd(); endforeach; ?>
    <?php endif; ?>

    <script>
    (function () {
      var tabs = document.getElementById('setTabs');
      if (!tabs) return;

      var btns = tabs.querySelectorAll('[data-stab]');
      var panes = document.querySelectorAll('[data-spane]');
      var A = { cat: 'ALL', q: '', limit: 25, step: 25, busy: false, last: Date.now() };
      var $ = function (id) { return document.getElementById(id); };
      var search = $('aSearch'), more = $('aMore'), foot = $('aFoot'), info = $('aInfo');
      var live = $('aLive'), upd = $('aUpd'), rbtn = $('aRefresh');

      /* ----- Tabs (remembered in the address bar) ----- */
      function show(name, push) {
        var found = false;
        btns.forEach(function (b) { if (b.getAttribute('data-stab') === name) found = true; });
        if (!found) name = 'general';
        btns.forEach(function (b) {
          var on = b.getAttribute('data-stab') === name;
          b.classList.toggle('on', on);
          b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        panes.forEach(function (p) { p.hidden = p.getAttribute('data-spane') !== name; });
        if (push !== false && window.history.replaceState) window.history.replaceState(null, '', '#' + name);
        if (name === 'audit' && Date.now() - A.last > 15000) refresh();
      }
      tabs.addEventListener('click', function (e) {
        var b = e.target.closest('[data-stab]');
        if (b) show(b.getAttribute('data-stab'));
      });
      window.addEventListener('hashchange', function () { show(window.location.hash.slice(1), false); });

      /* ----- Filter + "show more" ----- */
      function matches(r) {
        return (A.cat === 'ALL' || r.getAttribute('data-cat') === A.cat) &&
               (!A.q || r.getAttribute('data-text').indexOf(A.q) > -1);
      }
      function apply() {
        var rows = document.querySelectorAll('#aRows tr[data-cat]'), m = 0, shown = 0;
        rows.forEach(function (r) {
          if (matches(r)) { m++; r.hidden = m > A.limit; if (!r.hidden) shown++; }
          else r.hidden = true;
        });
        var nm = document.querySelector('#aRows .nomatch');
        if (nm) nm.hidden = !(rows.length && m === 0);
        foot.hidden = rows.length === 0 || m === 0;
        info.textContent = 'Showing ' + shown + ' of ' + m + (m === 1 ? ' entry' : ' entries');
        more.hidden = shown >= m;
      }
      function setUser(name) {
        search.value = name;
        A.q = name.toLowerCase();
        A.limit = A.step;
        apply();
        $('aLog').scrollIntoView({ behavior: 'smooth', block: 'start' });
      }

      $('aChips').addEventListener('click', function (e) {
        var a = e.target.closest('[data-acat]');
        if (!a) return;
        e.preventDefault();
        A.cat = a.getAttribute('data-acat');
        A.limit = A.step;
        $('aChips').querySelectorAll('[data-acat]').forEach(function (x) { x.classList.toggle('on', x === a); });
        apply();
      });
      search.addEventListener('input', function () {
        A.q = search.value.trim().toLowerCase();
        A.limit = A.step;
        apply();
      });
      more.addEventListener('click', function () { A.limit += A.step; apply(); });
      if ($('aOnline')) $('aOnline').addEventListener('click', function (e) {
        var b = e.target.closest('[data-user]');
        if (b) setUser(b.getAttribute('data-user'));
      });
      $('aRows').addEventListener('click', function (e) {
        var b = e.target.closest('.ulink');
        if (b) setUser(b.getAttribute('data-user'));
      });

      /* ----- Live refresh ----- */
      function status(text, ok) {
        upd.textContent = text;
        live.classList.toggle('off', !ok);
      }
      function render(d) {
        $('aStats').innerHTML = d.stats;
        if ($('aOnline')) $('aOnline').innerHTML = d.online;
        $('aChips').innerHTML = d.chips;
        $('aRows').innerHTML = d.rows;
        var tb = $('tabOnline'); if (tb) tb.textContent = d.onlineCount;
        var chips = $('aChips').querySelectorAll('[data-acat]'), ok = false;
        chips.forEach(function (c) {
          var on = c.getAttribute('data-acat') === A.cat;
          c.classList.toggle('on', on);
          if (on) ok = true;
        });
        if (!ok) { A.cat = 'ALL'; if (chips[0]) chips[0].classList.add('on'); }
        apply();
      }
      function refresh() {
        if (A.busy) return;
        A.busy = true;
        rbtn.classList.add('busy');
        fetch(window.location.pathname + '?audit=1', {
          credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' }
        })
          .then(function (r) { if (!r.ok) throw new Error('bad'); return r.json(); })
          .then(function (d) {
            render(d);
            var er = $('aErr'); if (er) er.hidden = true;
            status('Live · updated ' + d.updated, true);
          })
          .catch(function () { status('Couldn\'t refresh. Will retry.', false); })
          .then(function () { A.busy = false; A.last = Date.now(); rbtn.classList.remove('busy'); });
      }
      rbtn.addEventListener('click', refresh);
      setInterval(function () {
        var pane = document.querySelector('[data-spane="audit"]');
        if (!document.hidden && pane && !pane.hidden) refresh();
      }, 30000);

      /* ----- Export the filtered log as CSV ----- */
      function cell(s) { return '"' + String(s || '').replace(/\s+/g, ' ').trim().replace(/"/g, '""') + '"'; }
      $('aExport').addEventListener('click', function () {
        var lines = [['Time', 'User', 'Role', 'Type', 'Action', 'IP address'].map(cell).join(',')];
        document.querySelectorAll('#aRows tr[data-cat]').forEach(function (r) {
          if (!matches(r)) return;
          lines.push(['data-time', 'data-user', 'data-role', 'data-type', 'data-details', 'data-ip']
            .map(function (k) { return cell(r.getAttribute(k)); }).join(','));
        });
        if (lines.length === 1) return;
        var blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'audit-logs-' + new Date().toISOString().slice(0, 10) + '.csv';
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
      });

      apply();
      show(window.location.hash.slice(1) || 'general', false);
    })();
    </script>
    <?php
    pageEnd();
}