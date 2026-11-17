<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/api.php';

// Fallback in case SITE_NAME was only defined in the old config.php
if (!defined('SITE_NAME')) define('SITE_NAME', 'Casa Norberto Pension House');

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$user = currentUser();   // null when not logged in

// A saved session with no role is stale: treat it as logged out
if ($user && empty($user['role'])) {
  unset($_SESSION['user']);
  $user = null;
}

if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// Login handling (connected to the API)
$login_error  = '';
$login_notice = '';
$login_email  = '';

// Forgot password state: which panel of the login card is showing (login | email | code)
$view      = 'login';
$fp_error  = '';
$fp_notice = '';
$fp_email  = '';

// An emailed code that has run out of time is forgotten
if (!empty($_SESSION['fp']) && (int)($_SESSION['fp']['expires'] ?? 0) <= time()) {
  unset($_SESSION['fp']);
}

if (!$user && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_login'])) {
  $login_email = trim($_POST['email'] ?? '');
  $password    = $_POST['password'] ?? '';

  if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
    $login_error = 'Session expired. Please try again.';
  } elseif ($login_email === '' || $password === '') {
    $login_error = 'Please enter your email and password.';
  } else {
    $res = callApi('auth/web-login', 'POST', ['email' => $login_email, 'password' => $password]);

    if ($res['status'] === 200 && !empty($res['data']['role'])) {
      session_regenerate_id(true);
      $d = $res['data'];
      $_SESSION['user'] = [
        'id'       => $d['id'],
        'fullName' => $d['fullName'],
        'email'    => $d['email'],
        'role'     => $d['role'],
        'token'    => $d['token'],
      ];
      $_SESSION['csrf'] = bin2hex(random_bytes(32));
      header('Location: ' . dashboardFor($d['role']));
      exit;
    } elseif ($res['status'] === 401) {
      $login_error = 'Invalid email or password.';
    } elseif ($res['status'] === 403) {
      $login_error = $res['data']['message'] ?? 'This account is disabled.';
    } elseif ($res['status'] === 429) {
      $login_error = $res['data']['message'] ?? 'Too many failed sign-ins. Please try again later.';
    } elseif ($res['status'] === 400) {
      $login_error = 'Please enter a valid email and password.';
    } else {
      $login_error = 'Could not reach the server. HTTP status: ' . $res['status'];
      if (!empty($res['error'])) {
        $login_error .= ' | ' . $res['error'];
      }
    }
  }
}

// Forgot password (connected to the API): send a code to the email, then set a new password
if (!$user && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fp_step'])) {
  $step = (string)$_POST['fp_step'];
  $view = 'email';

  if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
    $fp_error = 'Session expired. Please try again.';

  } elseif ($step === 'send' || $step === 'resend') {
    $fp_email = strtolower(trim($step === 'resend'
      ? (string)($_SESSION['fp']['email'] ?? '')
      : (string)($_POST['fp_email'] ?? '')));
    if ($step === 'resend' && $fp_email !== '') $view = 'code';

    if (!filter_var($fp_email, FILTER_VALIDATE_EMAIL) || strlen($fp_email) > 150) {
      $fp_error = 'Enter a valid email address.';
    } else {
      $res = callApi('auth/forgot-password', 'POST', ['email' => $fp_email]);
      $d   = is_array($res['data']) ? $res['data'] : [];

      if ($res['status'] === 200) {
        $_SESSION['fp'] = [
          'email'   => $fp_email,
          'resend'  => time() + (int)($d['resendInSeconds'] ?? 60),
          'expires' => time() + (int)($d['expiresInSeconds'] ?? 600),
        ];
        $view      = 'code';
        $fp_notice = 'If that email has an account, a 6-digit code is on its way.';
      } elseif ($res['status'] === 400) {
        $fp_error = (string)($d['message'] ?? 'Enter a valid email address.');
      } else {
        $fp_error = 'Could not reach the server. Please try again.';
      }
    }

  } elseif ($step === 'reset') {
    $fp = $_SESSION['fp'] ?? null;

    if (!$fp) {
      $fp_error = 'That code has expired. Enter your email to get a new one.';
    } else {
      $view     = 'code';
      $fp_email = (string)$fp['email'];
      $code = preg_replace('/\D/', '', (string)($_POST['fp_otp'] ?? ''));
      $new  = (string)($_POST['fp_new'] ?? '');
      $cfm  = (string)($_POST['fp_confirm'] ?? '');

      if (strlen($code) !== 6) {
        $fp_error = 'Enter the 6-digit code from your email.';
      } elseif ($new === '' || $cfm === '') {
        $fp_error = 'Enter and confirm your new password.';
      } elseif (strlen($new) < 8) {
        $fp_error = 'The new password must be at least 8 characters.';
      } elseif (strlen($new) > 32) {
        $fp_error = 'The new password can be at most 32 characters.';
      } elseif ($new !== $cfm) {
        $fp_error = 'The new password and confirmation do not match.';
      } else {
        $res = callApi('auth/reset-password', 'POST', ['email' => $fp_email, 'otp' => $code, 'newPassword' => $new]);
        $d   = is_array($res['data']) ? $res['data'] : [];

        if ($res['status'] === 200) {
          unset($_SESSION['fp']);
          $login_email  = $fp_email;
          $login_notice = 'Password updated. Log in with your new password.';
          $view         = 'login';
          $fp_email     = '';
        } elseif ($res['status'] === 400) {
          $fp_error = (string)($d['message'] ?? 'Could not reset the password.');
        } else {
          $fp_error = 'Could not reach the server. Please try again.';
        }
      }
    }
  }
}

$fp     = $_SESSION['fp'] ?? null;                 // set while a code is waiting to be used
$fpLeft = $fp ? max(0, (int)$fp['expires'] - time()) : 0;
$fpWait = $fp ? max(0, (int)$fp['resend']  - time()) : 0;
$open   = $login_error !== '' || $login_notice !== '' || $view !== 'login';
?><!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="description" content="Clean, secure, affordable rooms in Lapu-Lapu. Stay for a few hours or overnight at Casa Norberto Pension House.">
<meta name="theme-color" content="#2f5d45">
<title><?= h(SITE_NAME) ?> | Rooms in Lapu-Lapu</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@500;800&family=Instrument+Sans:wght@400;500;600&display=swap">
<link rel="stylesheet" href="assets/style.css">
<style>
  /* Taller staff login card */
  .login-card{
    box-sizing:border-box;
    min-height:min(540px, calc(100vh - 110px));
    max-height:calc(100vh - 100px);
    overflow-y:auto;
    padding:28px 26px 22px;
  }
  .login-card.open{
    display:flex;
    flex-direction:column;
  }
  .login-card .lc-head{
    display:flex;
    align-items:center;
    gap:14px;
    margin-bottom:6px;
  }
  .login-card .lc-head img{
    width:48px;
    height:48px;
    border-radius:10px;
    flex:none;
  }
  .login-card .lc-head h3{
    margin:0;
  }
  .login-card .lc-head .lsub{
    margin:2px 0 0;
  }
  .login-card .lc-welcome{
    margin:14px 0 18px;
    font-size:.92rem;
    line-height:1.55;
    opacity:.75;
  }
  .login-card label{
    display:block;
    margin-top:14px;
    margin-bottom:6px;
  }
  .login-card input[type="email"],
  .login-card .pw input{
    min-height:52px;
    font-size:1rem;
  }
  .login-card .lerr{
    margin:0 0 6px;
  }
  .login-card .lsubmit{
    min-height:52px;
    margin-top:22px;
    font-size:1rem;
  }
  .login-card .lc-help{
    margin:14px 0 0;
    text-align:center;
    font-size:.85rem;
    opacity:.75;
  }
  .login-card .lc-foot{
    margin-top:auto;
    padding-top:18px;
    border-top:1px solid color-mix(in srgb, currentColor 15%, transparent);
    font-size:.82rem;
    line-height:1.5;
    text-align:center;
    opacity:.7;
  }
  .login-card .lc-spacer{
    flex:1 1 auto;
    min-height:18px;
  }

  /* Forgot password panels */
  .login-card .lc-panel{display:flex;flex-direction:column;flex:1 1 auto;margin:0}
  .login-card .lc-panel[hidden]{display:none}
  .login-card .lnote{margin:0 0 6px;padding:9px 11px;font-size:.85rem;color:#166534;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px}
  [data-theme="dark"] .login-card .lnote{background:#12301f;color:#bbf7d0;border-color:#1f5a38}
  .login-card input.lc-in{min-height:52px;font-size:1rem}
  .login-card input.lc-otp{min-height:52px;font-size:1.5rem;font-weight:600;letter-spacing:.5em;text-align:center;padding-left:.5em;font-variant-numeric:tabular-nums}
  .login-card .lc-link{padding:0;border:0;background:none;font:inherit;font-weight:600;color:var(--green);text-decoration:underline;cursor:pointer}
  .login-card .lc-link:hover{color:var(--green-d)}
  .login-card .lc-link:disabled{opacity:.5;cursor:not-allowed;text-decoration:none}
  .login-card .lc-show{display:flex;align-items:center;gap:.5rem;margin:0;font-size:.82rem;font-weight:400;cursor:pointer}
  .login-card .lc-show input{display:inline-block;width:auto;margin:0}
  .login-card .lc-hint{min-height:1.2em;margin:-6px 0 8px;font-size:.78rem}
  .login-card .lc-hint.good{color:#1f7a4d}
  .login-card .lc-hint.bad{color:#d14a32}
  .login-card .lc-timer{display:block;margin-top:4px;font-weight:500}
</style>
<script src="assets/dark-mode.js" data-scope="site"></script>
<link rel="icon" type="image/svg+xml" href="assets/logo.svg">
</head>
<body>

<header class="nav" id="nav">
    <a href="#top" class="brand"><span class="mk"><img src="assets/logo.svg" alt="" width="40" height="40"></span><span class="bt"><?= h(SITE_NAME) ?></span></a>

  <nav class="links" id="navLinks" aria-label="Main">
    <a href="#rooms">Rooms</a>
    <a href="#estimate">Plan your stay</a>
    <a href="#why">Why us</a>
    <a href="#faq">FAQ</a>
    <a href="#contact">Book</a>
  </nav>

  <div class="actions">
    <?php if ($user): ?>
      <a class="nav-cta" href="<?= h(dashboardFor($user['role'])) ?>">Dashboard</a>
    <?php else: ?>
      <div class="login-wrap">
        <button type="button" class="nav-cta" id="loginBtn" aria-expanded="<?= $open ? 'true' : 'false' ?>" aria-controls="loginCard">Login</button>
        <div class="login-card<?= $open ? ' open' : '' ?>" id="loginCard">

        <!-- Panel 1: log in -->
        <form class="lc-panel" data-panel="login" method="post" action="index.php"<?= $view === 'login' ? '' : ' hidden' ?>>
          <div class="lc-head">
            <img src="assets/logo.svg" alt="" width="48" height="48">
            <div>
              <h3>Staff login</h3>
              <p class="lsub">Sign in to open the dashboard.</p>
            </div>
          </div>
          <p class="lc-welcome">Welcome back. Use your staff email and password to manage rooms, bookings, and guests.</p>
          <?php if ($login_notice): ?><p class="lnote" role="status"><?= h($login_notice) ?></p><?php endif; ?>
          <?php if ($login_error): ?><p class="lerr" role="alert"><?= h($login_error) ?></p><?php endif; ?>
          <input type="hidden" name="do_login" value="1">
          <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">

          <label for="lEmail">Email address</label>
          <input id="lEmail" type="email" name="email" value="<?= h($login_email) ?>" placeholder="you@pensionhouse.com"
                 autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false" required>

          <label for="lPass">Password</label>
          <span class="pw">
            <input id="lPass" type="password" name="password" autocomplete="current-password" required>
            <button type="button" class="pw-toggle" id="pwToggle" aria-label="Show password" aria-pressed="false">Show</button>
          </span>

          <button type="submit" class="lsubmit" id="loginSubmit">Log in</button>

          <p class="lc-help">Forgot your password? <button type="button" class="lc-link" data-goto="email">Reset it</button></p>

          <div class="lc-spacer"></div>
        </form>

        <!-- Panel 2: ask for a code -->
        <form class="lc-panel" data-panel="email" data-fp method="post" action="index.php"<?= $view === 'email' ? '' : ' hidden' ?>>
          <div class="lc-head">
            <img src="assets/logo.svg" alt="" width="48" height="48">
            <div>
              <h3>Reset your password</h3>
              <p class="lsub">We will email you a 6-digit code.</p>
            </div>
          </div>
          <p class="lc-welcome">Enter the email you use to log in. If it has an account, we will send a code to it.</p>
          <?php if ($fp_error && $view === 'email'): ?><p class="lerr" role="alert"><?= h($fp_error) ?></p><?php endif; ?>
          <input type="hidden" name="fp_step" value="send">
          <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">

          <label for="fpEmail">Email address</label>
          <input id="fpEmail" class="lc-in" type="email" name="fp_email" value="<?= h($fp_email) ?>" placeholder="you@pensionhouse.com"
                 autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false" maxlength="150" required>

          <button type="submit" class="lsubmit" data-idle="Send code" data-busy="Sending&hellip;">Send code</button>

          <p class="lc-help">
            <?php if ($fp): ?><button type="button" class="lc-link" data-goto="code">I already have a code</button> &middot; <?php endif; ?>
            <button type="button" class="lc-link" data-goto="login">Back to log in</button>
          </p>

          <div class="lc-spacer"></div>
        </form>

        <?php if ($fp): ?>
        <!-- Panel 3: code + new password -->
        <form class="lc-panel" data-panel="code" data-fp id="fpReset" method="post" action="index.php" autocomplete="off"<?= $view === 'code' ? '' : ' hidden' ?>>
          <div class="lc-head">
            <img src="assets/logo.svg" alt="" width="48" height="48">
            <div>
              <h3>Enter your code</h3>
              <p class="lsub">Then choose a new password.</p>
            </div>
          </div>
          <p class="lc-welcome">
            Code sent to <b><?= h($fp['email']) ?></b>.
            <span class="lc-timer" id="fpLeft"></span>
          </p>
          <?php if ($fp_notice && $view === 'code'): ?><p class="lnote" role="status"><?= h($fp_notice) ?></p><?php endif; ?>
          <?php if ($fp_error && $view === 'code'): ?><p class="lerr" role="alert"><?= h($fp_error) ?></p><?php endif; ?>
          <input type="hidden" name="fp_step" value="reset">
          <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">

          <label for="fpOtp">6-digit code</label>
          <input id="fpOtp" class="lc-otp" type="text" name="fp_otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                 autocomplete="one-time-code" placeholder="000000" required>

          <label for="fpNew">New password</label>
          <input id="fpNew" class="lc-in" type="password" name="fp_new" minlength="8" maxlength="32" autocomplete="new-password" required>

          <label for="fpCfm">Confirm new password</label>
          <input id="fpCfm" class="lc-in" type="password" name="fp_confirm" minlength="8" maxlength="32" autocomplete="new-password" required>
          <p class="lc-hint" id="fpHint" aria-live="polite"></p>
          <label class="lc-show"><input type="checkbox" id="fpShow"> Show passwords</label>

          <button type="submit" class="lsubmit" data-idle="Update password" data-busy="Updating&hellip;">Update password</button>

          <p class="lc-help">
            <button type="submit" form="fpResend" class="lc-link" id="fpResendBtn"<?= $fpWait > 0 ? ' disabled' : '' ?>>Resend code</button>
            &middot; <button type="button" class="lc-link" data-goto="email">Use a different email</button>
          </p>

          <div class="lc-spacer"></div>
        </form>
        <form id="fpResend" data-fp method="post" action="index.php" hidden>
          <input type="hidden" name="fp_step" value="resend">
          <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
        </form>
        <?php endif; ?>

        <p class="lc-foot">Staff access only. Guests can book through our mobile app.</p>
      </div>
      </div>
    <?php endif; ?>
    <button type="button" class="burger" id="burger" aria-label="Open menu" aria-expanded="false" aria-controls="navLinks">
      <span></span><span></span><span></span>
    </button>
  </div>
</header>
<div class="progress"></div>

<main>
<section class="hero" id="top">
  <div class="blob b1" data-speed="0.35"></div>
  <div class="blob b2" data-speed="0.15"></div>
  <span class="eyebrow">Lapu-Lapu City</span>
  <h1><span class="l"><span>Rest easy,</span></span><span class="l"><span>stay local.</span></span></h1>
  <p>Clean, secure rooms at low prices in Lapu-Lapu. Stay for a few hours or overnight.</p>
  <div class="hero-cta">
    <a class="btn" href="#estimate">Plan your stay</a>
    <a class="btn ghost" href="#rooms">See rooms</a>
  </div>
  <ul class="stats">
    <li><strong>24/7</strong><span>Front desk</span></li>
    <li><strong>3</strong><span>Room types</span></li>
    <li><strong>&#8369;300</strong><span>Starting rate / 3 hours</span></li>
  </ul>
  <a class="scroll-cue" href="#scene" aria-label="Scroll down"><span></span></a>
</section>

<section class="statement">
  <p class="words">Need a few hours of rest or a whole night's sleep? Clean, secure, affordable rooms, run by family.</p>
</section>

<section class="scene" id="scene">
  <svg viewBox="0 0 800 450" preserveAspectRatio="xMidYMax slice" role="img" aria-label="Illustration of Casa Norberto Pension House going from day to night">
    <defs>
      <g id="leaves">
        <ellipse cx="24" cy="0" rx="24" ry="6" fill="#4C9A4F" transform="rotate(-120)"/>
        <ellipse cx="24" cy="0" rx="24" ry="6" fill="#4C9A4F" transform="rotate(-90)"/>
        <ellipse cx="24" cy="0" rx="24" ry="6" fill="#4C9A4F" transform="rotate(-60)"/>
      </g>
    </defs>
    <rect id="sky" width="800" height="450" fill="#BFE3F2"/>
    <g id="stars" opacity="0" fill="#fff">
      <circle cx="90" cy="50" r="2"/><circle cx="220" cy="90" r="1.6"/><circle cx="340" cy="40" r="2"/>
      <circle cx="470" cy="80" r="1.6"/><circle cx="560" cy="30" r="2"/><circle cx="700" cy="70" r="2"/><circle cx="760" cy="140" r="1.6"/>
    </g>
    <circle id="sun" cx="620" cy="90" r="38" fill="#FFD36B"/>
    <circle id="moon" cx="620" cy="90" r="30" fill="#F4F1DE" transform="translate(0 340)"/>
    <g class="cloud" fill="#fff" opacity=".9"><ellipse cx="150" cy="80" rx="50" ry="16"/><ellipse cx="185" cy="68" rx="32" ry="14"/></g>
    <g class="cloud" fill="#fff" opacity=".9"><ellipse cx="430" cy="130" rx="44" ry="14"/><ellipse cx="460" cy="120" rx="28" ry="12"/></g>
    <path d="M0 360 Q200 300 400 350 T800 340 V450 H0Z" fill="#5E8C61"/>
    <rect y="388" width="800" height="62" fill="#9A9A94"/>

    <rect x="190" y="190" width="470" height="112" fill="#E4A15F"/>
    <rect class="win" x="205" y="205" width="70" height="84" rx="2" stroke="#C9CED2" stroke-width="3"/>
    <rect class="win" x="280" y="205" width="70" height="84" rx="2" stroke="#C9CED2" stroke-width="3"/>
    <rect class="win" x="355" y="205" width="70" height="84" rx="2" stroke="#C9CED2" stroke-width="3"/>
    <rect class="win" x="430" y="205" width="70" height="84" rx="2" stroke="#C9CED2" stroke-width="3"/>
    <rect class="win" x="505" y="205" width="70" height="84" rx="2" stroke="#C9CED2" stroke-width="3"/>
    <rect class="win" x="580" y="205" width="70" height="84" rx="2" stroke="#C9CED2" stroke-width="3"/>
    <text class="led" x="428" y="238" text-anchor="middle" font-family="sans-serif" font-weight="700" font-size="18" fill="#39D353">CASA NORBERTO</text>
    <text class="led" x="428" y="260" text-anchor="middle" font-family="sans-serif" font-weight="700" font-size="13" fill="#FF5A4D">PENSION HOUSE</text>

    <polygon points="170,196 690,196 665,160 195,160" fill="#8B5A2B"/>
    <path d="M195 172H665M185 184H675" stroke="#6E441E" stroke-width="2" fill="none"/>

    <rect x="185" y="289" width="480" height="14" fill="#fff" stroke="#D9D9D4"/>
    <rect x="190" y="303" width="470" height="85" fill="#F1F1EE"/>
    <path d="M190 325H660M190 347H660M190 369H660M300 303V388M410 303V388M520 303V388" stroke="#D9D9D4" stroke-width="1" fill="none"/>
    <rect x="235" y="342" width="90" height="28" fill="#1D2024"/>
    <rect x="450" y="335" width="85" height="36" fill="#15171A"/>
    <path d="M450 347H535M450 359H535M471 335V371M492 335V371M513 335V371" stroke="#E8E8E4" stroke-width="1.5" fill="none"/>
    <rect x="606" y="312" width="4" height="76" fill="#2D6CB5"/>
    <rect x="610" y="312" width="42" height="76" fill="#1A1A1A"/>

    <rect x="130" y="336" width="60" height="52" fill="#C8342B"/>
    <rect x="124" y="322" width="72" height="14" fill="#F2B63C"/>
    <rect x="136" y="322" width="12" height="14" fill="#E87A2B"/>
    <rect x="160" y="322" width="12" height="14" fill="#E87A2B"/>
    <rect x="184" y="322" width="12" height="14" fill="#E87A2B"/>

    <g transform="translate(300 388)"><g class="palm"><use href="#leaves"/></g></g>
    <g transform="translate(420 388)"><g class="palm"><use href="#leaves"/></g></g>
    <g transform="translate(575 388)"><g class="palm"><use href="#leaves"/></g></g>
  </svg>
  <div class="caps"><h2>Morning check-in</h2><h2>Evening arrivals</h2><h2>Good night</h2></div>
</section>

<section class="rooms" id="rooms">
  <div class="track">
    <div class="intro"><h2>Pick your room</h2><p>Scroll sideways through the rooms, then estimate your stay.</p></div>

    <article class="room">
      <div><h3>Room rates</h3><p>Good for 2 persons.</p></div>
      <table style="width:100%;border-collapse:collapse;font-size:.95rem">
        <tr><td>3 hours</td><td style="text-align:right"><strong>&#8369;300</strong></td></tr>
        <tr><td>4&ndash;5 hours</td><td style="text-align:right"><strong>&#8369;400</strong></td></tr>
        <tr><td>6&ndash;8 hours</td><td style="text-align:right"><strong>&#8369;460</strong></td></tr>
        <tr><td>9&ndash;10 hours</td><td style="text-align:right"><strong>&#8369;500</strong></td></tr>
        <tr><td>11&ndash;12 hours</td><td style="text-align:right"><strong>&#8369;600</strong></td></tr>
        <tr><td>24 hours</td><td style="text-align:right"><strong>&#8369;1,000</strong></td></tr>
      </table>
    </article>

    <article class="room">
      <div class="pic">
        <svg viewBox="0 0 120 70" width="120" aria-hidden="true">
          <rect x="10" y="15" width="8" height="45" rx="3" fill="#8a6a46"/>
          <rect x="10" y="38" width="100" height="16" rx="4" fill="#c9d6c0"/>
          <rect x="18" y="28" width="30" height="12" rx="5" fill="#fff"/>
          <rect x="102" y="45" width="8" height="15" rx="3" fill="#8a6a46"/>
          <circle cx="85" cy="14" r="9" fill="none" stroke="#51634F" stroke-width="3"/>
          <circle cx="85" cy="14" r="2" fill="#51634F"/>
        </svg>
        <img src="assets/rooms/fan.jpg" alt="Fan Room" onerror="this.remove()">
      </div>
      <div><h3>Fan Room</h3><p>Single or double bed, shared bathroom, free Wi-Fi.</p></div>
      <div class="foot">
        <strong>From &#8369;300 <small>/ 3 hrs</small></strong>
        <button type="button" class="link-btn" data-room="fan">Estimate stay</button>
      </div>
    </article>

    <article class="room">
      <div class="pic">
        <svg viewBox="0 0 120 70" width="120" aria-hidden="true">
          <rect x="10" y="15" width="8" height="45" rx="3" fill="#8a6a46"/>
          <rect x="10" y="38" width="100" height="16" rx="4" fill="#c9d6c0"/>
          <rect x="18" y="28" width="30" height="12" rx="5" fill="#fff"/>
          <rect x="102" y="45" width="8" height="15" rx="3" fill="#8a6a46"/>
          <rect x="68" y="5" width="42" height="13" rx="4" fill="#9db5b9"/>
        </svg>
        <img src="assets/rooms/aircon.jpg" alt="Aircon Double" onerror="this.remove()">
      </div>
      <div><h3>Aircon Double</h3><p>Private bathroom, TV, hot shower, free Wi-Fi.</p></div>
      <div class="foot">
        <strong>From &#8369;300 <small>/ 3 hrs</small></strong>
        <button type="button" class="link-btn" data-room="aircon">Estimate stay</button>
      </div>
    </article>

    <article class="room">
      <div class="pic">
        <svg viewBox="0 0 230 70" width="230" aria-hidden="true">
          <g>
            <rect x="10" y="15" width="8" height="45" rx="3" fill="#8a6a46"/>
            <rect x="10" y="38" width="90" height="16" rx="4" fill="#c9d6c0"/>
            <rect x="18" y="28" width="28" height="12" rx="5" fill="#fff"/>
          </g>
          <g transform="translate(110 0)">
            <rect x="10" y="15" width="8" height="45" rx="3" fill="#8a6a46"/>
            <rect x="10" y="38" width="90" height="16" rx="4" fill="#c9d6c0"/>
            <rect x="18" y="28" width="28" height="12" rx="5" fill="#fff"/>
          </g>
          <rect x="95" y="5" width="42" height="13" rx="4" fill="#9db5b9"/>
        </svg>
        <img src="assets/rooms/family.jpg" alt="Family Room" onerror="this.remove()">
      </div>
      <div><h3>Family Room</h3><p>Sleeps up to five, aircon, private bathroom.</p></div>
      <div class="foot">
        <strong>From &#8369;300 <small>/ 3 hrs</small></strong>
        <button type="button" class="link-btn" data-room="family">Estimate stay</button>
      </div>
    </article>
  </div>
</section>

<section class="estimate" id="estimate">
  <div class="wrap est">
    <div class="est-copy reveal">
      <span class="eyebrow dark">Plan your stay</span>
      <h2>Know the cost before you book.</h2>
      <p>Choose how long you want to stay for a quick estimate. Reservations are completed in our mobile app.</p>
    </div>
    <form class="est-card reveal" id="estForm" style="--d:.1s" novalidate>
      <label for="estRoom">Length of stay</label>
      <select id="estRoom">
        <option value="300" data-key="fan">3 hours &mdash; &#8369;300</option>
        <option value="400" data-key="aircon">4&ndash;5 hours &mdash; &#8369;400</option>
        <option value="460" data-key="family">6&ndash;8 hours &mdash; &#8369;460</option>
        <option value="500">9&ndash;10 hours &mdash; &#8369;500</option>
        <option value="600">11&ndash;12 hours &mdash; &#8369;600</option>
        <option value="1000">24 hours &mdash; &#8369;1,000</option>
      </select>

      <label for="estNights">Nights</label>
      <div class="stepper">
        <button type="button" id="nMinus" aria-label="Fewer nights">&minus;</button>
        <input id="estNights" type="number" min="1" max="30" value="1" inputmode="numeric">
        <button type="button" id="nPlus" aria-label="More nights">+</button>
      </div>

      <div class="est-total" aria-live="polite">
        <span id="estLabel">3 hours &times; 1</span>
        <strong id="estTotal">&#8369;300</strong>
      </div>
      <small class="note">Estimate only. Good for 2 persons. Final details are confirmed in the app.</small>
      <a class="btn" href="#contact">Book in our app</a>
    </form>
  </div>
</section>

<section class="why" id="why">
  <div class="wrap">
    <div class="head reveal"><span class="eyebrow dark">Why stay with us</span><h2>Simple, comfortable, and close to everything.</h2></div>
    <div class="cards">
      <article class="card reveal">
        <span class="ico"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
        <h3>Stay for a few hours or overnight</h3>
        <p>Rest as long as you need, from 3 hours.</p>
      </article>
      <article class="card reveal" style="--d:.1s">
        <span class="ico"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5a10 10 0 0 1 14 0M8.5 16a5 5 0 0 1 7 0"/><circle cx="12" cy="19" r="1"/></svg></span>
        <h3>Aircon and free Wi-Fi</h3>
        <p>Cool, connected, and comfortable.</p>
      </article>
      <article class="card reveal" style="--d:.2s">
        <span class="ico"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/></svg></span>
        <h3>Clean and secure</h3>
        <p>Family-run, with a 24-hour front desk and secure parking.</p>
      </article>
    </div>
  </div>
</section>

<section class="faq" id="faq">
  <div class="wrap narrow">
    <div class="head reveal"><span class="eyebrow dark">FAQ</span><h2>Quick answers</h2></div>
    <div class="reveal">
      <details><summary>Can I stay for just a few hours?</summary><p>Yes. You can stay for a few hours or overnight. Rates start at &#8369;300 for 3 hours and go up to &#8369;1,000 for 24 hours.</p></details>
      <details><summary>How much are the rates?</summary><p>3 hours &#8369;300, 4&ndash;5 hours &#8369;400, 6&ndash;8 hours &#8369;460, 9&ndash;10 hours &#8369;500, 11&ndash;12 hours &#8369;600, and 24 hours &#8369;1,000. Rates are good for 2 persons.</p></details>
      <details><summary>How do I book a room?</summary><p>Reservations are made through the Casa Norberto Pension House mobile app.</p></details>
      <details><summary>Which room fits a family?</summary><p>The Family Room sleeps up to five and has aircon and a private bathroom.</p></details>
      <details><summary>Is there Wi-Fi and parking?</summary><p>Free Wi-Fi is available in every room, and we have secure parking.</p></details>
      <details><summary>Where are you located?</summary><p>We're in Lapu-Lapu, a short five-minute walk from Crab Island.</p></details>
    </div>
  </div>
</section>

<section class="end" id="contact">
  <div class="wrap">
    <h2>Book in our app</h2>
    <p>Reservations are made through the Casa Norberto Pension House mobile app.</p>
    <div class="end-cta">
      <a class="btn" href="#estimate">Estimate your stay</a>
      <a class="btn ghost" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&amp;query=<?= rawurlencode(SITE_NAME . ' Lapu-Lapu') ?>">Find us on the map</a>
    </div>
  </div>
</section>
</main>

<footer class="foot-bar">
  <div class="wrap fb">
    <div><strong><?= h(SITE_NAME) ?></strong><span>Lapu-Lapu City, Cebu</span></div>
    <nav aria-label="Footer">
      <a href="#rooms">Rooms</a><a href="#estimate">Plan your stay</a><a href="#faq">FAQ</a>
    </nav>
    <small>&copy; <?= date('Y') ?> <?= h(SITE_NAME) ?></small>
  </div>
</footer>

<button type="button" class="to-top" id="toTop" aria-label="Back to top">
  <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
</button>

<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/lenis@1.1.13/dist/lenis.min.js"></script>
<script src="assets/app.js"></script>
<script>
(function () {
  var card = document.getElementById('loginCard');
  if (!card) return;

  var panels = card.querySelectorAll('[data-panel]');
  function show(name) {
    panels.forEach(function (p) { p.hidden = p.getAttribute('data-panel') !== name; });
    var f = card.querySelector('[data-panel="' + name + '"] input:not([type=hidden]):not([type=checkbox])');
    if (f) setTimeout(function () { f.focus(); }, 60);
  }
  card.addEventListener('click', function (e) {
    var b = e.target.closest('[data-goto]');
    if (!b || b.disabled) return;
    e.preventDefault();
    show(b.getAttribute('data-goto'));
  });

  // Reset forms show their own "busy" text (assets/app.js only handles the log in form)
  card.querySelectorAll('form[data-fp]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      e.stopPropagation();
      var b = f.querySelector('button.lsubmit');
      if (b) { b.disabled = true; b.textContent = b.getAttribute('data-busy') || 'Please wait\u2026'; }
    });
  });
  window.addEventListener('pageshow', function () {   // back button (bfcache)
    card.querySelectorAll('button.lsubmit[data-idle]').forEach(function (b) {
      b.disabled = false; b.textContent = b.getAttribute('data-idle');
    });
  });

  var otp = document.getElementById('fpOtp');
  if (otp) {
    var pn = document.getElementById('fpNew'), pf = document.getElementById('fpCfm');
    var hint = document.getElementById('fpHint'), showPw = document.getElementById('fpShow');
    var leftEl = document.getElementById('fpLeft'), btn = document.getElementById('fpResendBtn');
    var left = <?= (int)$fpLeft ?>, wait = <?= (int)$fpWait ?>;

    otp.addEventListener('input', function () { this.value = this.value.replace(/\D/g, '').slice(0, 6); });
    showPw.addEventListener('change', function () { pn.type = pf.type = this.checked ? 'text' : 'password'; });

    function match() {
      if (!pf.value) { hint.textContent = ''; hint.className = 'lc-hint'; return; }
      var ok = pn.value === pf.value;
      hint.textContent = ok ? 'Passwords match.' : 'Passwords do not match yet.';
      hint.className = 'lc-hint ' + (ok ? 'good' : 'bad');
    }
    pn.addEventListener('input', match);
    pf.addEventListener('input', match);

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
  }

  <?php if ($view !== 'login'): ?>show('<?= h($view) ?>');<?php endif; ?>
})();
</script>
</body></html>