<?php
require_once __DIR__ . '/layout.php';

// =====================================================================
//  ROOM CARD (used by admin/rooms.php and staff/rooms.php)
// =====================================================================

function roomState(array $r): string
{
    return $r['isActive'] ? ($r['status'] ?? 'AVAILABLE') : 'DISABLED';
}

function roomCard(array $r, string $actions = ''): string
{
    $st  = roomState($r);
    $cls = ['AVAILABLE' => 'p-ok', 'OCCUPIED' => 'p-info', 'MAINTENANCE' => 'p-warn', 'DISABLED' => 'p-off'][$st] ?? 'p-off';
    $rates  = array_values(array_filter($r['rates'] ?? [], fn($x) => $x['isActive']));
    $prices = array_column($rates, 'price');

    $h  = '<article class="rcard ' . ($st === 'DISABLED' ? 'dim' : '') . '" data-status="' . e($st) . '" '
        . 'data-text="' . e(strtolower($r['roomNo'] . ' ' . $r['title'] . ' ' . ($r['currentGuest'] ?? ''))) . '">';
    $h .= '<div class="rimg">';
    if (!empty($r['imageUrl'])) $h .= '<img src="' . e($r['imageUrl']) . '" alt="" loading="lazy">';
    else $h .= '<span class="rno">' . e($r['roomNo']) . '</span>';
    $h .= '<span class="pill ' . $cls . '">' . e(ucfirst(strtolower($st))) . '</span></div>';

    $h .= '<div class="rbody"><div class="rtop"><div><h3>Room ' . e($r['roomNo']) . '</h3>'
        . '<span class="muted">' . e($r['title']) . ' · sleeps ' . (int)$r['capacity'] . '</span></div>';
    if ($prices) {
        $h .= '<div class="rprice"><small>from</small>' . e(peso(min($prices))) . '</div>';
    }
    $h .= '</div>';

    if ($rates) {
        $h .= '<div class="rrates">';
        foreach ($rates as $rt) $h .= '<span>' . e($rt['label']) . ' · ' . e(peso($rt['price'])) . '</span>';
        $h .= '</div>';
    } else {
        $h .= '<p class="muted" style="margin:0">No rates yet. Guests cannot book this room.</p>';
    }

    if ($st === 'OCCUPIED') {
        $h .= '<div class="rnow"><b>' . e($r['currentGuest'] ?? 'Guest') . '</b><br>'
            . '<span class="muted">In ' . e(fmtShort($r['currentCheckIn'] ?? null)) . ' · out ' . e(fmtShort($r['currentCheckOut'] ?? null)) . '</span></div>';
    }

    $h .= $actions !== '' ? '<div class="racts">' . $actions . '</div>' : '';
    return $h . '</div></article>';
}

// =====================================================================
//  BOOKINGS (admin + staff)
// =====================================================================

function bookingAction(int $id, string $status, string $label, string $icon, string $cls, string $confirm = ''): string
{
    return '<form method="post" class="inl"' . $confirm . '>' . csrfField()
        . '<input type="hidden" name="booking_id" value="' . $id . '">'
        . '<input type="hidden" name="status" value="' . e($status) . '">'
        . '<button class="btn sm ' . $cls . '">' . navIcon($icon, 14) . e($label) . '</button></form>';
}

function renderBookings(array $user): void
{
    $isAdmin = $user['role'] === 'admin';

        // Price preview for the Extend dialog (called by this page's own JavaScript)
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['quote'])) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $qid = (int)$_GET['quote'];
        $qh  = (int)($_GET['hours'] ?? 0);
        $res = apiAuth("bookings/$qid/extend-quote?hours=$qh");
        echo apiOk($res) && is_array($res['data'])
            ? json_encode(['ok' => true] + $res['data'])
            : json_encode(['ok' => false, 'message' => apiMessage($res, 'Could not get the price.')]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        checkCsrf();
        $id = (int)($_POST['booking_id'] ?? 0);

        if (($_POST['act'] ?? '') === 'extend') {
            $m   = strtoupper($_POST['method'] ?? 'CASH');
            $res = apiAuth("bookings/$id/extend", 'POST', [
                'extraHours'    => (int)($_POST['hours'] ?? 0),
                'paymentMethod' => $m,
                'referenceCode' => $m === 'GCASH' ? trim($_POST['ref'] ?? '') : null,
            ]);
            apiOk($res) ? flash('ok', apiMessage($res, 'Stay extended.'))
                        : flash('err', apiMessage($res, 'Could not extend the stay.'));
        } else {
            $res = apiAuth("bookings/$id/status", 'PATCH', ['status' => $_POST['status'] ?? '']);
            apiOk($res) ? flash('ok', 'Booking updated.') : flash('err', apiMessage($res, 'Could not update the booking.'));
        }
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $res = apiAuth('bookings');
    $all = is_array($res['data']) ? $res['data'] : [];
    usort($all, fn($a, $b) => strcmp($b['createdAt'], $a['createdAt']));

    $filter   = strtoupper($_GET['status'] ?? '');
    $counts   = array_count_values(array_column($all, 'status'));
    $bookings = $filter ? array_values(array_filter($all, fn($b) => $b['status'] === $filter)) : $all;

    $today   = date('Y-m-d');
    $month   = count(array_filter($all, fn($b) => substr($b['createdAt'], 0, 7) === date('Y-m')));
    $todayIn = count(array_filter($all, fn($b) => substr($b['checkIn'], 0, 10) === $today));

    pageStart('Bookings', $user, count($all) . ' booking' . (count($all) === 1 ? '' : 's') . ' in total',
        ['action' => newBookingBtn($user['role'])]);
    ?>
    <div class="stats">
      <?= statCard('Bookings this month', $month, 'bookings.php', 'g') ?>
      <?= statCard('Check-ins today', $todayIn, 'login', 'blue') ?>
      <?= statCard('Guests in-house', $counts['CHECKED_IN'] ?? 0, 'guests.php', 'amber') ?>
      <?= statCard('Awaiting payment review', $counts['PENDING'] ?? 0, 'clock', 'coral') ?>
    </div>

    <?php if ($isAdmin):
        $mon = strtotime('monday this week');
        $keys = []; $labels = [];
        for ($i = 0; $i < 7; $i++) {
            $t = strtotime("+$i day", $mon);
            $keys[] = date('Y-m-d', $t);
            $labels[] = date('D', $t);
        }
        $weekCounts = array_values(perDay($all, fn($b) => $b['checkIn'], $keys));
        $weekTotal  = array_sum($weekCounts);
        $weekMax    = max($weekCounts);
        $peakIdx    = $weekMax > 0 ? array_search($weekMax, $weekCounts) : null;

        $chartData = [];
        foreach ($keys as $i => $k) {
            $chartData[] = [
                'label' => $labels[$i],
                'date'  => date('M j', strtotime($k)),
                'n'     => (int)$weekCounts[$i],
                'today' => $k === $today,
            ];
        } ?>
    <div class="panel">
      <div class="cin-head">
        <div>
          <h3 style="margin:0">Check-ins this week</h3>
          <span class="muted"><?= e(date('M j', $mon)) ?> to <?= e(date('M j', strtotime('+6 day', $mon))) ?></span>
        </div>
        <div class="cin-sum">
          <span><b><?= (int)$weekTotal ?></b> check-in<?= $weekTotal === 1 ? '' : 's' ?></span>
          <span class="muted"><?= $peakIdx !== null ? 'Busiest: ' . e($labels[$peakIdx]) . ' (' . (int)$weekMax . ')' : 'No check-ins yet' ?></span>
        </div>
      </div>
      <div class="cin" id="cin" data-points="<?= e(json_encode($chartData)) ?>"></div>
    </div>

    <style>
      .cin-head{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:.4rem}
      .cin-sum{display:flex;flex-direction:column;align-items:flex-end;font-size:.78rem}
      .cin-sum b{font-size:1.25rem;font-weight:600;color:var(--g)}
      .cin{position:relative;width:100%}
      .cin svg{display:block;overflow:visible;touch-action:pan-y}      .cin .grid{stroke:#e6ebe9;stroke-width:1;stroke-dasharray:3 4}
      .cin .ylab,.cin .xlab{font-size:11px;fill:#6b7f7a;font-family:inherit}
      .cin .xlab.today{fill:#e8583f;font-weight:600}
      .cin .line{fill:none;stroke:#123f3a;stroke-width:3;stroke-linecap:round;stroke-linejoin:round;
                 stroke-dasharray:1;stroke-dashoffset:1;animation:cinDraw 1s ease forwards}
      .cin .area{opacity:0;animation:cinFade .8s .4s ease forwards}
      .cin .dot{fill:#fff;stroke:#123f3a;stroke-width:2.5;transition:r .15s}
      .cin .dot.today{stroke:#e8583f}
      .cin .dot.on{r:7}
      .cin .guide{stroke:#123f3a;stroke-width:1.5;stroke-dasharray:4 4;opacity:0;transition:opacity .15s}
      .cin .guide.on{opacity:.45}
      .cin .tip{position:absolute;pointer-events:none;transform:translate(-50%,-115%);padding:.45rem .7rem;border-radius:10px;
                background:#0f3d3a;color:#fff;font-size:.74rem;line-height:1.35;white-space:nowrap;text-align:center;
                box-shadow:0 6px 18px rgba(15,61,58,.25);opacity:0;transition:opacity .12s,left .08s,top .08s}
      .cin .tip.on{opacity:1}
      .cin .tip b{display:block;font-size:1rem;font-weight:600}
      .cin .tip small{opacity:.75}
      @keyframes cinDraw{to{stroke-dashoffset:0}}
      @keyframes cinFade{to{opacity:1}}
      @media (prefers-reduced-motion:reduce){.cin .line,.cin .area{animation:none;stroke-dashoffset:0;opacity:1}}
    </style>

        <script>
    (function () {
      var box = document.getElementById('cin');
      if (!box) return;
      var pts = JSON.parse(box.getAttribute('data-points') || '[]');
      if (!pts.length) return;

      var NS = 'http://www.w3.org/2000/svg';
      var H = 150, L = 28, R = 16, T = 14, B = 26;
      var max = Math.max.apply(null, pts.map(function (p) { return p.n; }));
      var yMax = Math.max(4, max % 2 ? max + 1 : max);

      function mk(tag, attrs, parent) {
        var n = document.createElementNS(NS, tag);
        for (var k in attrs) n.setAttribute(k, attrs[k]);
        if (parent) parent.appendChild(n);
        return n;
      }

      function draw() {
        var W = box.clientWidth || 700;
        var step = pts.length > 1 ? (W - L - R) / (pts.length - 1) : 0;
        function X(i) { return L + i * step; }
        function Y(n) { return T + (1 - n / yMax) * (H - T - B); }

        box.innerHTML = '';
        var svg = mk('svg', { width: W, height: H, viewBox: '0 0 ' + W + ' ' + H, role: 'img', 'aria-label': 'Check-ins per day this week' });
        var defs = mk('defs', {}, svg);
        var g = mk('linearGradient', { id: 'cinGrad', x1: 0, y1: 0, x2: 0, y2: 1 }, defs);
        mk('stop', { offset: '0%', 'stop-color': '#123f3a', 'stop-opacity': '.28' }, g);
        mk('stop', { offset: '100%', 'stop-color': '#123f3a', 'stop-opacity': '0' }, g);

        // Grid lines + y labels (0, 2, 4 style, only 3 lines to keep it compact)
        for (var s = 0; s <= 2; s++) {
          var v = Math.round(yMax / 2 * s), y = Y(v);
          mk('line', { x1: L, x2: W - R, y1: y, y2: y, 'class': 'grid' }, svg);
          var yl = mk('text', { x: L - 8, y: y + 4, 'text-anchor': 'end', 'class': 'ylab' }, svg);
          yl.textContent = v;
        }

        // Smooth line + filled area
        var d = 'M' + X(0) + ',' + Y(pts[0].n);
        for (var i = 1; i < pts.length; i++) {
          var mx = (X(i - 1) + X(i)) / 2;
          d += ' C' + mx + ',' + Y(pts[i - 1].n) + ' ' + mx + ',' + Y(pts[i].n) + ' ' + X(i) + ',' + Y(pts[i].n);
        }
        mk('path', { d: d + ' L' + X(pts.length - 1) + ',' + (H - B) + ' L' + X(0) + ',' + (H - B) + ' Z', fill: 'url(#cinGrad)', 'class': 'area' }, svg);
        mk('path', { d: d, 'class': 'line', pathLength: 1 }, svg);

        // Guide line, dots, x labels
        var guide = mk('line', { y1: T, y2: H - B, 'class': 'guide' }, svg);
        var dots = pts.map(function (p, i) {
          var xl = mk('text', { x: X(i), y: H - 6, 'text-anchor': 'middle', 'class': 'xlab' + (p.today ? ' today' : '') }, svg);
          xl.textContent = p.label;
          return mk('circle', { cx: X(i), cy: Y(p.n), r: 4, 'class': 'dot' + (p.today ? ' today' : '') }, svg);
        });

        var hit = mk('rect', { x: 0, y: 0, width: W, height: H, fill: 'transparent' }, svg);
        box.appendChild(svg);

        var tip = document.createElement('div');
        tip.className = 'tip';
        box.appendChild(tip);

        var active = -1;
        function show(i) {
          if (i === active) return;
          if (active > -1) dots[active].classList.remove('on');
          active = i;
          var p = pts[i];
          dots[i].classList.add('on');
          guide.setAttribute('x1', X(i)); guide.setAttribute('x2', X(i));
          guide.classList.add('on');
          tip.innerHTML = '<small>' + p.label + ', ' + p.date + (p.today ? ' (today)' : '') + '</small><b>' + p.n + '</b><small>check-in' + (p.n === 1 ? '' : 's') + '</small>';
          tip.style.left = X(i) + 'px';
          tip.style.top = Y(p.n) + 'px';
          tip.classList.add('on');
        }
        function hide() {
          if (active > -1) dots[active].classList.remove('on');
          active = -1;
          guide.classList.remove('on');
          tip.classList.remove('on');
        }
        function onMove(e) {
          var r = svg.getBoundingClientRect();
          var i = step ? Math.round((e.clientX - r.left - L) / step) : 0;
          show(Math.max(0, Math.min(pts.length - 1, i)));
        }
        hit.addEventListener('pointermove', onMove);
        hit.addEventListener('pointerdown', onMove);
        hit.addEventListener('pointerleave', hide);
      }

      draw();
      var lastW = box.clientWidth, t;
      window.addEventListener('resize', function () {
        clearTimeout(t);
        t = setTimeout(function () {
          if (box.clientWidth !== lastW) { lastW = box.clientWidth; draw(); }
        }, 150);
      });
    })();
    </script>
    <?php endif; ?>

    <div class="toolbar">
      <?= tabsHtml([
          '' => 'All', 'PENDING' => 'Pending', 'CONFIRMED' => 'Confirmed', 'CHECKED_IN' => 'Checked in',
          'COMPLETED' => 'Completed', 'CANCELLED' => 'Cancelled',
      ], $filter, ['' => count($all)] + $counts) ?>
      <div class="search"><?= searchIcon() ?><input data-filter="#bt" placeholder="Search guest, room or code" aria-label="Search bookings"></div>
    </div>

    <div class="panel" style="padding:0;overflow:hidden">
    <div class="scroll">
    <table id="bt" style="border:0;border-radius:0">
      <tr><th>Guest</th><th>Room</th><th>Stay</th><th>Total</th><th>Payment</th><th>Status</th><th>Actions</th></tr>
      <?php foreach ($bookings as $b): ?>
      <tr data-text="<?= e(strtolower($b['guestName'] . ' ' . $b['roomNo'] . ' ' . $b['roomTitle'] . ' ' . $b['bookingCode'])) ?>">
        <td><span class="who"><?= avatar($b['guestName'], 32) ?>
          <span><b><?= e($b['guestName']) ?></b><br><span class="muted"><?= e($b['bookingCode']) ?> · <?= e(ucfirst(strtolower($b['source']))) ?></span></span></span></td>
        <td><?= e($b['roomTitle']) ?> <span class="muted">#<?= e($b['roomNo']) ?></span><br><span class="muted"><?= e($b['rateLabel']) ?></span></td>
        <td><?= e(fmtShort($b['checkIn'])) ?><br><span class="muted">to <?= e(fmtShort($b['checkOut'])) ?></span></td>
        <td><b><?= e(peso($b['totalAmount'])) ?></b></td>
        <td><?= e($b['paymentMethod'] ?? '-') ?> <?= $b['paymentStatus'] ? badge($b['paymentStatus']) : '' ?>
            <?php if (!empty($b['referenceCode'])): ?><br><span class="muted">Ref: <?= e($b['referenceCode']) ?></span><?php endif; ?></td>
        <td><?= badge($b['status']) ?>
          <?php if ($b['status'] === 'CHECKED_IN' && strtotime($b['checkOut']) < time()): ?>
            <br><span class="pill p-warn" style="margin-top:.25rem">Overdue</span>
          <?php endif; ?></td>
        <td><div class="acts">
          <?php
            $a = '';
            if ($b['status'] === 'CONFIRMED') {
                $a .= bookingAction($b['id'], 'CHECKED_IN', 'Check in', 'login', 'green');
            }
            if ($b['status'] === 'CHECKED_IN') {
                $a .= '<button type="button" class="btn sm ghost" data-extend="' . (int)$b['id'] . '"'
                    . ' data-name="' . e($b['guestName']) . '"'
                    . ' data-room="' . e($b['roomTitle'] . ' #' . $b['roomNo']) . '"'
                    . ' data-out="' . e(fmtShort($b['checkOut'])) . '">' . navIcon('clock', 14) . 'Extend</button>';
                $a .= bookingAction($b['id'], 'COMPLETED', 'Complete', 'check', 'green',
                    confirmAttr('Mark ' . $b['bookingCode'] . ' (' . $b['guestName'] . ') as completed? The room becomes free again.', 'Complete stay', 'Complete', 'green'));
            }
            if (in_array($b['status'], ['PENDING', 'CONFIRMED'], true)) {
                $a .= bookingAction($b['id'], 'CANCELLED', 'Cancel', 'x', 'red',
                    confirmAttr('Cancel booking ' . $b['bookingCode'] . ' for ' . $b['guestName'] . '?', 'Cancel booking', 'Yes, cancel', 'red'));
            }
            echo $a !== '' ? $a : '<span class="muted">-</span>';
          ?>
        </div></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$bookings): ?>
        <?= emptyRow(7, 'bookings.php', 'No bookings found', $filter ? 'Nothing in this status yet.' : 'New bookings will show up here.') ?>
      <?php endif; ?>
      <?= noMatchRow(7) ?>
    </table>
    </div>
    </div>

        <style>
      .m-body select{width:100%}
      .ex-quote{white-space:pre-line;margin:.8rem 0;padding:.7rem .85rem;border:1px solid var(--line);border-radius:12px;background:var(--cream);font-size:.82rem;line-height:1.55}
      .ex-quote.bad{color:#d14a32;border-color:#f3c9c0}
    </style>

    <?php modalStart('d-extend', 'Extend stay', 'sm'); ?>
      <form method="post" autocomplete="off"><?= csrfField() ?>
        <input type="hidden" name="act" value="extend">
        <input type="hidden" name="booking_id" id="exId">
        <p class="muted" id="exWho" style="margin:0 0 .8rem"></p>

        <label for="exHours">Extra time</label>
        <select id="exHours" name="hours">
          <?php for ($i = 1; $i <= 12; $i++): ?>
            <option value="<?= $i ?>"><?= $i ?> hour<?= $i > 1 ? 's' : '' ?></option>
          <?php endfor; ?>
        </select>

        <div class="ex-quote" id="exQuote" aria-live="polite">Checking the price...</div>

        <label for="exMethod">Payment</label>
        <select id="exMethod" name="method">
          <option value="CASH">Cash</option>
          <option value="GCASH">GCash</option>
        </select>
        <div id="exRefBox" hidden>
          <label for="exRef">GCash reference code</label>
          <input id="exRef" name="ref" maxlength="50">
        </div>

        <div class="m-actions">
          <button type="button" class="btn ghost" data-close>Cancel</button>
          <button class="btn coral" id="exGo" disabled>Extend stay</button>
        </div>
      </form>
    <?php modalEnd(); ?>

    <script>
    (function () {
      var dlg = document.getElementById('d-extend');
      if (!dlg) return;
      var id = document.getElementById('exId'), who = document.getElementById('exWho'),
          hrs = document.getElementById('exHours'), quote = document.getElementById('exQuote'),
          method = document.getElementById('exMethod'), refBox = document.getElementById('exRefBox'),
          ref = document.getElementById('exRef'), go = document.getElementById('exGo'), seq = 0;

      function peso(n) { return '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
      function when(s) { return new Date(s).toLocaleString('en-US', { month: 'short', day: '2-digit', hour: 'numeric', minute: '2-digit' }); }

      function toggleRef() {
        var g = method.value === 'GCASH';
        refBox.hidden = !g;
        ref.required = g;
        if (!g) ref.value = '';
      }

      function check() {
        var mine = ++seq;
        go.disabled = true;
        quote.className = 'ex-quote';
        quote.textContent = 'Checking the price...';
        fetch(window.location.pathname + '?quote=' + encodeURIComponent(id.value) + '&hours=' + hrs.value,
              { credentials: 'same-origin', cache: 'no-store' })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            if (mine !== seq) return;
            if (!d.ok) { quote.className = 'ex-quote bad'; quote.textContent = d.message; return; }
            var extra = Number(d.extraAmount);
            var t = 'New check-out: ' + when(d.newCheckOut)
                  + '\nTotal stay: ' + d.totalHours + ' hrs (' + d.tierLabel + ')'
                  + '\n' + (extra > 0 ? 'Collect ' + peso(extra) : 'No extra charge');
            if (new Date(d.newCheckOut) < new Date()) t += '\nThis is still past the current time. Add more hours.';
            quote.textContent = t;
            go.disabled = false;
          })
          .catch(function () {
            if (mine !== seq) return;
            quote.className = 'ex-quote bad';
            quote.textContent = 'Could not get the price. Try again.';
          });
      }

      document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-extend]');
        if (!b) return;
        id.value = b.getAttribute('data-extend');
        who.textContent = b.getAttribute('data-name') + ' · ' + b.getAttribute('data-room')
                        + ' · now out ' + b.getAttribute('data-out');
        hrs.value = '1';
        method.value = 'CASH';
        toggleRef();
        dlg.showModal();
        check();
      });
      hrs.addEventListener('change', check);
      method.addEventListener('change', toggleRef);
    })();
    </script>
    <?php
    pageEnd();
}

// =====================================================================
//  GUESTS (admin + staff)
// =====================================================================

// Tier is based on number of bookings. Change the numbers here to adjust.
function guestTier(int $stays): string
{
    if ($stays >= 5) return 'VIP';
    if ($stays >= 2) return 'Regular';
    return 'New';
}

function renderGuests(array $user): void
{
    $isAdmin = $user['role'] === 'admin';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        checkCsrf();
        if ($isAdmin) {
            $id  = (int)($_POST['id'] ?? 0);
            $res = apiAuth("guests/$id/active", 'PATCH', ['isActive' => ($_POST['active'] ?? '0') === '1']);
            apiOk($res) ? flash('ok', 'Guest updated.') : flash('err', apiMessage($res, 'Could not update the guest.'));
        }
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $list = function (string $p): array {
        $r = apiAuth($p);
        return is_array($r['data']) ? $r['data'] : [];
    };
    $guests   = $list('guests');
    $bookings = $list('bookings');
    $payments = $list('payments');
    usort($bookings, fn($a, $b) => strcmp($b['checkIn'], $a['checkIn']));

    $byUser = [];
    foreach ($bookings as $b) if (!empty($b['userId'])) $byUser[(int)$b['userId']][] = $b;
    $payByCode = [];
    foreach ($payments as $p) $payByCode[$p['bookingCode']][] = $p;

    $tierColor = ['New' => '#c9a227', 'Regular' => '#123f3a', 'VIP' => '#e8583f'];
    $tierCount = ['New' => 0, 'Regular' => 0, 'VIP' => 0];
    foreach ($guests as $g) $tierCount[guestTier((int)$g['totalBookings'])]++;
    $total   = count($guests);
    $active  = count(array_filter($guests, fn($g) => $g['isActive']));
    $newThis = count(array_filter($guests, fn($g) => substr($g['createdAt'], 0, 7) === date('Y-m')));

    pageStart('Guests', $user, $total === 1 ? '1 registered guest' : $total . ' registered guests');
    ?>
    <style>
      .tier{display:inline-flex;align-items:center;gap:.4rem;font-size:.75rem}
      .tier i{width:8px;height:8px;border-radius:50%;display:inline-block}
      .donutcard{display:flex;align-items:center;gap:2rem;flex-wrap:wrap}
      .donut{width:104px;height:104px;flex-shrink:0}
      .donut text{font-family:Poppins,sans-serif}
      .legend{display:flex;flex-direction:column;gap:.45rem;font-size:.78rem}
      .legend span{display:flex;align-items:center;gap:.5rem}
      .legend i{width:10px;height:10px;border-radius:50%;display:inline-block}
      .idimg{display:block;max-width:100%;max-height:420px;margin-top:1rem;border:1px solid var(--line);border-radius:12px}
    </style>

    <div class="stats">
      <?= statCard('Registered guests', $total, 'guests.php', 'g') ?>
      <?= statCard('Active accounts', $active, 'check', 'blue') ?>
      <?= statCard('Joined this month', $newThis, 'plus', 'amber') ?>
      <?= statCard('VIP guests', $tierCount['VIP'], 'reviews.php', 'coral') ?>
    </div>

    <?php if ($isAdmin && $total): $C = 2 * M_PI * 40; $off = 0; ?>
    <div class="panel">
      <h3>Guest tiers</h3>
      <div class="donutcard">
        <svg class="donut" viewBox="0 0 100 100" role="img" aria-label="Guests by tier">
          <circle cx="50" cy="50" r="40" fill="none" stroke="#e6ebe9" stroke-width="14"/>
          <?php foreach ($tierCount as $name => $c): if (!$c) continue; $len = $c / $total * $C; ?>
            <circle cx="50" cy="50" r="40" fill="none" stroke="<?= $tierColor[$name] ?>" stroke-width="14"
                    stroke-dasharray="<?= round($len, 2) ?> <?= round($C - $len, 2) ?>"
                    stroke-dashoffset="<?= round(-$off, 2) ?>" transform="rotate(-90 50 50)"/>
          <?php $off += $len; endforeach; ?>
          <text x="50" y="55" text-anchor="middle" font-size="18" font-weight="600" fill="#0f3d3a"><?= $total ?></text>
        </svg>
        <div class="legend">
          <?php foreach ($tierCount as $name => $c): ?>
            <span><i style="background:<?= $tierColor[$name] ?>"></i><?= e($name) ?> · <?= $c ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="toolbar">
      <div class="search"><?= searchIcon() ?><input data-filter="#gt" placeholder="Search name, email or contact" aria-label="Search guests"></div>
    </div>

    <div class="panel" style="padding:0;overflow:hidden">
    <div class="scroll">
    <table id="gt" style="border:0;border-radius:0">
      <tr><th>Guest</th><th>Contact</th><th>Stays</th><th>Tier</th><th>Joined</th><th>Status</th><th>Actions</th></tr>
      <?php foreach ($guests as $g): $tier = guestTier((int)$g['totalBookings']); ?>
      <tr data-text="<?= e(strtolower($g['fullName'] . ' ' . $g['email'] . ' ' . ($g['contactNo'] ?? ''))) ?>">
        <td><span class="who"><?= avatar($g['fullName'], 34) ?>
          <span><b><?= e($g['fullName']) ?></b><br><span class="muted"><?= e($g['email']) ?></span></span></span></td>
        <td><?= e($g['contactNo'] ?? '-') ?></td>
        <td><?= (int)$g['totalBookings'] ?></td>
        <td><span class="tier"><i style="background:<?= $tierColor[$tier] ?>"></i><?= e($tier) ?></span></td>
        <td><?= e(fmtDay($g['createdAt'])) ?></td>
        <td><?= $g['isActive'] ? badge('APPROVED') : badge('CANCELLED') ?></td>
        <td><div class="acts">
          <button type="button" class="btn sm ghost" data-open="#g<?= (int)$g['id'] ?>"><?= navIcon('eye', 14) ?>View</button>
          <?php if ($isAdmin): ?>
          <form method="post"<?= confirmAttr(($g['isActive'] ? 'Disable ' : 'Enable ') . $g['fullName'] . '\'s account?',
              $g['isActive'] ? 'Disable guest' : 'Enable guest', $g['isActive'] ? 'Disable' : 'Enable', $g['isActive'] ? 'red' : 'green') ?>>
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
            <input type="hidden" name="active" value="<?= $g['isActive'] ? '0' : '1' ?>">
            <button class="btn sm <?= $g['isActive'] ? 'gray' : 'green' ?>"><?= $g['isActive'] ? 'Disable' : 'Enable' ?></button>
          </form>
          <?php endif; ?>
        </div></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$guests): ?><?= emptyRow(7, 'guests.php', 'No guests yet', 'Guests appear here after they register in the mobile app.') ?><?php endif; ?>
      <?= noMatchRow(7) ?>
    </table>
    </div>
    </div>

    <?php foreach ($guests as $g):
        $gid = (int)$g['id'];
        $gb  = $byUser[$gid] ?? [];
        $tier = guestTier((int)$g['totalBookings']);
        modalStart('g' . $gid, $g['fullName'], 'lg'); ?>
      <div class="mtabs" role="tablist">
        <button type="button" class="on" data-pane="info">Personal Information</button>
        <button type="button" data-pane="book">Booking History</button>
        <button type="button" data-pane="pay">Payment History</button>
        <?php if ($isAdmin): ?><button type="button" data-pane="id">ID</button><?php endif; ?>
      </div>

      <div class="pane" data-pane="info">
        <dl class="kv">
          <dt>Full name</dt><dd><?= e($g['fullName']) ?></dd>
          <dt>Email</dt><dd><?= e($g['email']) ?></dd>
          <dt>Mobile number</dt><dd><?= e($g['contactNo'] ?? '-') ?></dd>
          <dt>Member since</dt><dd><?= e(fmtDate($g['createdAt'])) ?></dd>
          <dt>Total bookings</dt><dd><?= (int)$g['totalBookings'] ?> (<?= e($tier) ?>)</dd>
          <dt>Account</dt><dd><?= $g['isActive'] ? badge('APPROVED') : badge('CANCELLED') ?></dd>
        </dl>
      </div>

      <div class="pane" data-pane="book" hidden>
        <?php if ($gb): ?>
        <div class="scroll"><table>
          <tr><th>Code</th><th>Room</th><th>Check-in</th><th>Check-out</th><th>Total</th><th>Status</th></tr>
          <?php foreach ($gb as $b): ?>
          <tr>
            <td><?= e($b['bookingCode']) ?></td>
            <td><?= e($b['roomTitle']) ?> <span class="muted">#<?= e($b['roomNo']) ?></span></td>
            <td><?= e(fmtShort($b['checkIn'])) ?></td>
            <td><?= e(fmtShort($b['checkOut'])) ?></td>
            <td><?= e(peso($b['totalAmount'])) ?></td>
            <td><?= badge($b['status']) ?></td>
          </tr>
          <?php endforeach; ?>
        </table></div>
        <?php else: ?><?= emptyBlock('bookings.php', 'No bookings yet', 'This guest has not booked a room.') ?><?php endif; ?>
      </div>

      <div class="pane" data-pane="pay" hidden>
        <?php
          $rows = [];
          foreach ($gb as $b) foreach ($payByCode[$b['bookingCode']] ?? [] as $p) $rows[] = $p;
        ?>
        <?php if ($rows): ?>
        <div class="scroll"><table>
          <tr><th>Booking</th><th>Method</th><th>Amount</th><th>Reference</th><th>Status</th></tr>
          <?php foreach ($rows as $p): ?>
          <tr>
            <td><?= e($p['bookingCode']) ?></td>
            <td><?= e($p['method']) ?></td>
            <td><?= e(peso($p['amount'])) ?></td>
            <td><span class="ref"><?= e($p['referenceCode'] ?? '-') ?></span></td>
            <td><?= badge($p['status']) ?></td>
          </tr>
          <?php endforeach; ?>
        </table></div>
        <?php else: ?><?= emptyBlock('payments.php', 'No payments yet', 'Payments for this guest will appear here.') ?><?php endif; ?>
      </div>

      <?php if ($isAdmin): ?>
      <div class="pane" data-pane="id" hidden>
        <?php if (!empty($g['hasId'])): ?>
          <dl class="kv">
            <dt>ID type</dt><dd><?= e($g['idType'] ?? '-') ?></dd>
          </dl>
          <a href="guest_id.php?id=<?= $gid ?>" target="_blank" rel="noopener" title="Open full size">
            <img class="idimg" src="guest_id.php?id=<?= $gid ?>" alt="ID of <?= e($g['fullName']) ?>" loading="lazy">
          </a>
        <?php else: ?>
          <?= emptyBlock('inbox', 'No ID uploaded', 'This guest registered before ID upload was added.') ?>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <div class="m-actions"><button type="button" class="btn ghost" data-close>Close</button></div>
    <?php modalEnd(); endforeach; ?>
    <?php
    pageEnd();
}

// =====================================================================
//  PAYMENTS (admin + staff)
// =====================================================================

function reviewForm(int $id, string $decision, string $label, string $icon, string $cls, string $confirm): string
{
    return '<form method="post" class="inl"' . $confirm . '>' . csrfField()
        . '<input type="hidden" name="payment_id" value="' . $id . '">'
        . '<input type="hidden" name="decision" value="' . e($decision) . '">'
        . '<button class="btn sm ' . $cls . '">' . navIcon($icon, 14) . e($label) . '</button></form>';
}

function renderPayments(array $user): void
{
    $isAdmin = $user['role'] === 'admin';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        checkCsrf();
        $id       = (int)($_POST['payment_id'] ?? 0);
        $decision = $_POST['decision'] ?? '';
        $res = apiAuth("payments/$id/review", 'PATCH', ['decision' => $decision]);
        if (apiOk($res)) {
            flash('ok', $decision === 'APPROVED' ? 'Payment approved. Booking confirmed.' : 'Payment rejected. Booking cancelled.');
        } else {
            flash('err', apiMessage($res, 'Could not review the payment.'));
        }
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $filter = isset($_GET['status']) ? strtoupper($_GET['status']) : 'PENDING';
    $res = apiAuth('payments');
    $all = is_array($res['data']) ? $res['data'] : [];
    usort($all, fn($a, $b) => strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? ''));

    $counts   = array_count_values(array_column($all, 'status'));
    $payments = $filter ? array_values(array_filter($all, fn($p) => $p['status'] === $filter)) : $all;
    $revenue  = array_sum(array_map(fn($p) => $p['status'] === 'APPROVED' ? (float)$p['amount'] : 0, $all));
    $waiting  = array_sum(array_map(fn($p) => $p['status'] === 'PENDING' ? (float)$p['amount'] : 0, $all));

    pageStart('Payments', $user, 'Review GCash payments and confirm bookings.');
    ?>
    <div class="stats">
      <?= statCard('Awaiting review', $counts['PENDING'] ?? 0, 'clock', 'coral') ?>
      <?= statCard('Approved', $counts['APPROVED'] ?? 0, 'check', 'g') ?>
      <?= statCard('Rejected', $counts['REJECTED'] ?? 0, 'x', 'amber') ?>
      <?= $isAdmin ? statCard('Revenue (approved)', peso($revenue), 'payments.php', 'blue')
                   : statCard('Amount waiting', peso($waiting), 'payments.php', 'blue') ?>
    </div>

    <div class="toolbar">
      <?= tabsHtml(['PENDING' => 'Pending', 'APPROVED' => 'Approved', 'REJECTED' => 'Rejected', '' => 'All'],
                   $filter, ['PENDING' => $counts['PENDING'] ?? 0, 'APPROVED' => $counts['APPROVED'] ?? 0,
                             'REJECTED' => $counts['REJECTED'] ?? 0, '' => count($all)]) ?>
      <div class="search"><?= searchIcon() ?><input data-filter="#pt" placeholder="Search guest, booking or reference" aria-label="Search payments"></div>
    </div>

    <?php if ($filter === 'PENDING' && $payments): ?>
      <div class="note"><?= navIcon('clock', 16) ?><span>Check the reference code against the GCash transaction before approving.</span></div>
    <?php endif; ?>

    <div class="panel" style="padding:0;overflow:hidden">
    <div class="scroll">
    <table id="pt" style="border:0;border-radius:0">
      <tr><th>Booking</th><th>Guest</th><th>Room and stay</th><th>Method</th><th>Amount</th><th>Reference code</th><th>Status</th><th>Actions</th></tr>
      <?php foreach ($payments as $p): ?>
      <tr data-text="<?= e(strtolower($p['bookingCode'] . ' ' . $p['guestName'] . ' ' . ($p['referenceCode'] ?? ''))) ?>">
        <td><b><?= e($p['bookingCode']) ?></b></td>
        <td><span class="who"><?= avatar($p['guestName'], 30) ?><?= e($p['guestName']) ?></span></td>
        <td><?= e($p['roomTitle']) ?> <span class="muted">#<?= e($p['roomNo']) ?></span><br>
            <span class="muted"><?= e(fmtShort($p['checkIn'])) ?> to <?= e(fmtShort($p['checkOut'])) ?></span></td>
        <td><?= e($p['method']) ?></td>
        <td><b><?= e(peso($p['amount'])) ?></b></td>
        <td>
          <?php if (!empty($p['referenceCode'])): ?>
            <span class="ref"><?= e($p['referenceCode']) ?></span>
            <button type="button" class="btn sm ghost" data-copy="<?= e($p['referenceCode']) ?>" title="Copy reference code"><?= navIcon('copy', 14) ?></button>
          <?php else: ?><span class="muted">-</span><?php endif; ?>
        </td>
        <td><?= badge($p['status']) ?>
            <?php if (!empty($p['reviewedByRole'])): ?><br><span class="muted">by <?= e($p['reviewedByRole']) ?></span><?php endif; ?></td>
        <td><div class="acts">
          <?php if ($p['status'] === 'PENDING'):
              $who = $p['guestName'] . ' (' . peso($p['amount']) . ', ref ' . ($p['referenceCode'] ?? '-') . ')';
              echo reviewForm($p['id'], 'APPROVED', 'Approve', 'check', 'green',
                  confirmAttr('Approve the payment from ' . $who . '? The booking will be confirmed.', 'Approve payment', 'Approve', 'green'));
              echo reviewForm($p['id'], 'REJECTED', 'Reject', 'x', 'red',
                  confirmAttr('Reject the payment from ' . $who . '? The booking will be cancelled.', 'Reject payment', 'Reject', 'red'));
          else: ?><span class="muted">-</span><?php endif; ?>
        </div></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$payments): ?>
        <?= emptyRow(8, 'payments.php', $filter === 'PENDING' ? 'All caught up' : 'Nothing here', $filter === 'PENDING' ? 'No payments are waiting for review.' : 'No payments in this status.') ?>
      <?php endif; ?>
      <?= noMatchRow(8) ?>
    </table>
    </div>
    </div>
    <?php
    pageEnd();
}