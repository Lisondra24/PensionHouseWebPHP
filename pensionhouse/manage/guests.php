<?php
require_once __DIR__ . '/../layout.php';
$user = requireStaffOrAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $id = (int)($_POST['id'] ?? 0);
    $res = apiAuth("guests/$id/active", 'PATCH', ['isActive' => ($_POST['active'] ?? '0') === '1']);
    if (apiOk($res)) {
        flash('ok', 'Guest updated.');
    } else {
        flash('err', apiMessage($res, 'Could not update the guest.'));
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

$res = apiAuth('guests');
$guests = is_array($res['data']) ? array_values(array_filter($res['data'], 'is_array')) : [];

$total      = count($guests);
$activeN    = count(array_filter($guests, fn($g) => !empty($g['isActive'])));
$disabledN  = $total - $activeN;
$bookingsN  = array_sum(array_map(fn($g) => (int)($g['totalBookings'] ?? 0), $guests));

// ----- Sign-ups per day / month for the chart (all three ranges, switched in the browser) -----
$jf = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$ranges = [];
foreach (['7d' => 'last 7 days', '30d' => 'last 30 days', '12m' => 'last 12 months'] as $rg => $title) {
    $b = dashBuckets($rg, 0);
    $labels = []; $tips = [];
    foreach ($b['keys'] as $k) {
        if ($rg === '12m')    { $t = strtotime($k . '-01'); $labels[] = date('M', $t);   $tips[] = date('F Y', $t); }
        elseif ($rg === '7d') { $t = strtotime($k);         $labels[] = date('D', $t);   $tips[] = date('D, M j', $t); }
        else                  { $t = strtotime($k);         $labels[] = date('M j', $t); $tips[] = date('M j, Y', $t); }
    }
    $ranges[$rg] = [
        'title'  => $title,
        'labels' => $labels,
        'tips'   => $tips,
        'values' => dashSeries($guests, fn($g) => $g['createdAt'] ?? '', $b),
    ];
}

/** Eye-off icon (the shared navIcon set only has the plain eye). */
function guestEyeOff(int $size = 14): string
{
    return '<svg class="ico" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . '<path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/>'
         . '<path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>';
}

pageStart('Guests', $user, $total . ' guest' . ($total === 1 ? '' : 's') . ' · registered from the mobile app');
?>
<style>
  main{padding:1rem 1.25rem}
  .top{margin-bottom:.7rem;align-items:center}
  h1{font-size:1.3rem}
  .sub{margin-top:0}

  .gp{display:grid;gap:.65rem}

  /* compact stat cards */
  .gk-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:.65rem}
  .gk{display:flex;align-items:center;gap:.65rem;min-width:0;background:#fff;border:1px solid #e3e9e7;border-radius:14px;padding:.6rem .8rem;box-shadow:0 1px 2px rgba(15,61,58,.05)}
  .gk .si{width:34px;height:34px;border-radius:10px}
  .gk small{display:block;font-size:.68rem;font-weight:500;color:var(--soft);white-space:nowrap}
  .gk b{display:block;font-size:1.2rem;font-weight:600;line-height:1.15}

  /* panels */
  .gpn{background:#fff;border:1px solid #e3e9e7;border-radius:14px;padding:.7rem .9rem;box-shadow:0 1px 2px rgba(15,61,58,.05);min-width:0}
  .gpn-h{display:flex;justify-content:space-between;align-items:center;gap:.6rem;margin-bottom:.35rem;flex-wrap:wrap}
  .gpn-h h3{margin:0;font-size:.82rem;font-weight:600}
  .gpn-h p{margin:.1rem 0 0;font-size:.64rem;color:var(--soft)}
  .seg{display:flex;gap:2px;padding:2px;border-radius:999px;background:#eef3f1}
  .seg button{border:0;background:none;padding:.22rem .75rem;border-radius:999px;font:inherit;font-size:.68rem;font-weight:500;color:var(--soft);cursor:pointer}
  .seg button.on{background:#123f3a;color:#fff}

  /* interactive chart */
  .cbox{position:relative;height:170px;cursor:crosshair;touch-action:pan-y}
  .cbox svg{position:absolute;inset:0;width:100%;height:100%;display:block}
  .ax{font-size:10px;fill:#6b7f7a;font-family:Poppins,system-ui,sans-serif}
  .nodata{font-size:11px;fill:#8a9b97;font-family:Poppins,system-ui,sans-serif}
  .cbox .hl{stroke:#9db3ae;stroke-width:1;stroke-dasharray:4 4;opacity:0;transition:opacity .15s}
  .cbox .hd{opacity:0;transition:opacity .15s}
  .cbox.hover .hl,.cbox.hover .hd{opacity:1}
  .cbox .ln.draw{stroke-dasharray:1;stroke-dashoffset:1;animation:lndraw .9s ease forwards}
  .cbox .ar.draw{opacity:0;animation:arfade .9s .25s ease forwards}
  @keyframes lndraw{to{stroke-dashoffset:0}}
  @keyframes arfade{to{opacity:1}}
  .tt{position:absolute;left:0;top:0;z-index:5;pointer-events:none;min-width:140px;padding:.5rem .7rem;border-radius:12px;background:#0f3d3a;color:#fff;box-shadow:0 10px 26px rgba(15,61,58,.28);font-size:.7rem;line-height:1.35;opacity:0;transform:translateY(4px);transition:opacity .12s,transform .12s}
  .tt.on{opacity:1;transform:none}
  .tt .tt-d{display:block;color:rgba(255,255,255,.65);font-size:.64rem;margin-bottom:.2rem}
  .tt .tt-v{display:flex;align-items:center;gap:.45rem;font-size:.95rem;font-weight:600}
  .tt .tt-v i{width:8px;height:8px;border-radius:50%;flex-shrink:0}
  .tt .tt-v small{font-size:.64rem;font-weight:400;color:rgba(255,255,255,.65)}
  .tt .tt-c{display:block;margin-top:.25rem;font-size:.64rem;font-weight:500}
  .tt .tt-c.up{color:#8ff0b0}.tt .tt-c.dn{color:#ffb4ad}.tt .tt-c.eq{color:rgba(255,255,255,.6)}
  @media(prefers-reduced-motion:reduce){.cbox .ln.draw,.cbox .ar.draw{animation:none;stroke-dashoffset:0;opacity:1}}

  /* toolbar + table */
  .g-tool{display:flex;justify-content:space-between;align-items:center;gap:.6rem;flex-wrap:wrap;margin-bottom:.5rem}
  .g-tool .tabs a{padding:.3rem .85rem;font-size:.72rem}
  .g-tool .search input{padding:.4rem .75rem .4rem 2.1rem;width:250px}
  .gt table{border:0;border-radius:0;font-size:.76rem}
  .gt th{padding:.4rem .7rem;font-size:.6rem}
  .gt td{padding:.4rem .7rem}
  .gt .who{gap:.55rem}
  .gt .who b{display:block;font-weight:600;font-size:.78rem;line-height:1.2}
  .gt .who small{display:block;font-size:.66rem;color:var(--soft);line-height:1.2}
  .gt .num{font-weight:600}
  .gt .acts form{margin:0}
  .gt .empty{padding:1.2rem .5rem}
  .gt .empty .ei{width:38px;height:38px}
  @media(max-width:700px){.g-tool .search input{width:100%}.g-tool .search{width:100%}}
</style>

<div class="gp">

  <div class="gk-row">
    <div class="gk"><span class="si g"><?= navIcon('guests.php', 18) ?></span><div><small>Total guests</small><b><?= $total ?></b></div></div>
    <div class="gk"><span class="si blue"><?= navIcon('check', 18) ?></span><div><small>Active</small><b><?= $activeN ?></b></div></div>
    <div class="gk"><span class="si coral"><?= navIcon('x', 18) ?></span><div><small>Disabled</small><b><?= $disabledN ?></b></div></div>
    <div class="gk"><span class="si amber"><?= navIcon('bookings.php', 18) ?></span><div><small>Total bookings</small><b><?= $bookingsN ?></b></div></div>
  </div>

  <div class="gpn">
    <div class="gpn-h">
      <div><h3>New guests</h3><p id="gSub">&nbsp;</p></div>
      <div class="seg" role="group" aria-label="Chart range">
        <button type="button" data-range="7d">Weekly</button>
        <button type="button" data-range="30d" class="on">Monthly</button>
        <button type="button" data-range="12m">Yearly</button>
      </div>
    </div>
    <div class="cbox" id="gBox"></div>
  </div>

  <div class="gpn gt">
    <div class="g-tool">
      <?= chipsHtml(['ALL' => 'All', 'ACTIVE' => 'Active', 'DISABLED' => 'Disabled'], '#gt', ['ALL' => $total, 'ACTIVE' => $activeN, 'DISABLED' => $disabledN]) ?>
      <div class="search"><?= searchIcon() ?><input data-filter="#gt" placeholder="Search name, email or contact" aria-label="Search guests"></div>
    </div>

    <div class="scroll" id="gt">
      <table>
        <thead><tr><th>Guest</th><th>Contact</th><th>Bookings</th><th>Joined</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($guests as $g):
            $on   = !empty($g['isActive']);
            $name = (string)($g['fullName'] ?? '');
            $hay  = mb_strtolower($name . ' ' . ($g['email'] ?? '') . ' ' . ($g['contactNo'] ?? '')); ?>
          <tr data-text="<?= e($hay) ?>" data-status="<?= $on ? 'ACTIVE' : 'DISABLED' ?>">
            <td>
              <span class="who"><?= avatar($name, 28) ?>
                <span><b><?= e($name) ?></b><small><?= e($g['email'] ?? '-') ?></small></span>
              </span>
            </td>
            <td><?= e($g['contactNo'] ?? '-') ?></td>
            <td class="num"><?= (int)($g['totalBookings'] ?? 0) ?></td>
            <td><?= e(!empty($g['createdAt']) ? date('M d, Y', strtotime($g['createdAt'])) : '-') ?></td>
            <td><?= $on ? '<span class="pill p-ok">Active</span>' : '<span class="pill p-off">Disabled</span>' ?></td>
            <td class="tright">
              <div class="acts" style="justify-content:flex-end">
                <form method="post"<?= confirmAttr(
                        $on ? 'Disable ' . $name . '? They will not be able to book until enabled again.' : 'Enable ' . $name . '?',
                        $on ? 'Disable guest' : 'Enable guest',
                        $on ? 'Disable' : 'Enable',
                        $on ? 'red' : 'green') ?>>
                  <?= csrfField() ?>
                  <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                  <input type="hidden" name="active" value="<?= $on ? '0' : '1' ?>">
                  <button class="btn sm <?= $on ? 'ghost' : 'green' ?>"><?= $on ? guestEyeOff(14) : navIcon('eye', 14) ?><?= $on ? 'Disable' : 'Enable' ?></button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$guests): ?><?= emptyRow(6, 'guests.php', 'No guests yet', 'Guests appear here after they register in the app.') ?><?php endif; ?>
        <?= noMatchRow(6) ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script>
// New-guests chart: smooth line, hover crosshair + tooltip, Weekly / Monthly / Yearly switch
(function () {
  var R = <?= json_encode($ranges, $jf) ?>;
  var box = document.getElementById('gBox');
  var sub = document.getElementById('gSub');
  if (!box) return;
  var range = '30d', animate = true, G = null, COL = '#123f3a';

  function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

  // Smooth curve that never overshoots the data
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

  function updateSub() {
    var d = R[range], sum = d.values.reduce(function (a, b) { return a + b; }, 0);
    sub.textContent = sum + ' new guest' + (sum === 1 ? '' : 's') + ' in the ' + d.title;
  }

  function draw() {
    var w = box.clientWidth, h = box.clientHeight;
    if (w < 60 || h < 60) return;
    var d = R[range], vals = d.values, n = vals.length;
    if (!n) return;

    var max = Math.max.apply(null, vals.concat([0]));
    var rows = 3, step = Math.max(1, Math.ceil(max / rows)), top = step * rows;
    var pl = 28, pr = 12, pt = 8, pb = 20;
    var iw = w - pl - pr, ih = h - pt - pb, slot = iw / n;
    function cx(i) { return pl + slot * (i + 0.5); }
    function cy(v) { return pt + ih * (1 - v / top); }

    var gid = 'gg', cls = animate ? ' draw' : '';
    var s = '<svg viewBox="0 0 ' + w + ' ' + h + '" role="img" aria-label="New guests chart">';
    s += '<defs><linearGradient id="' + gid + '" x1="0" y1="0" x2="0" y2="1">'
       + '<stop offset="0" stop-color="' + COL + '" stop-opacity=".28"/>'
       + '<stop offset="1" stop-color="' + COL + '" stop-opacity="0"/></linearGradient></defs>';

    for (var g = 0; g <= rows; g++) {
      var y = cy(step * g);
      s += '<line x1="' + pl + '" x2="' + (w - pr) + '" y1="' + y.toFixed(1) + '" y2="' + y.toFixed(1)
         + '" stroke="#e6ecea" stroke-width="1"' + (g ? ' stroke-dasharray="3 4"' : '') + '/>';
      s += '<text x="' + (pl - 6) + '" y="' + (y + 3.5).toFixed(1) + '" text-anchor="end" class="ax">' + (step * g) + '</text>';
    }

    var pts = vals.map(function (v, i) { return [cx(i), cy(v)]; });
    var base = pt + ih, line = smooth(pts);
    s += '<path class="ar' + cls + '" d="' + line + ' L' + cx(n - 1).toFixed(1) + ',' + base + ' L' + cx(0).toFixed(1) + ',' + base
       + ' Z" fill="url(#' + gid + ')"/>';
    s += '<path class="ln' + cls + '" pathLength="1" d="' + line + '" fill="none" stroke="' + COL
       + '" stroke-width="2.4" stroke-linejoin="round" stroke-linecap="round"/>';

    s += '<line class="hl" x1="0" x2="0" y1="' + pt + '" y2="' + base + '"/>';
    s += '<circle class="hd" r="9" fill="' + COL + '" fill-opacity=".15"/>';
    s += '<circle class="hd" r="4.5" fill="#fff" stroke="' + COL + '" stroke-width="2.4"/>';

    var maxLabels = Math.max(2, Math.floor(iw / 52)), every = Math.ceil(n / maxLabels);
    d.labels.forEach(function (l, i) {
      if (i % every !== 0) return;
      s += '<text x="' + cx(i).toFixed(1) + '" y="' + (h - 5) + '" text-anchor="middle" class="ax">' + esc(l) + '</text>';
    });

    if (max <= 0) {
      s += '<text x="' + (pl + iw / 2) + '" y="' + (pt + ih / 2) + '" text-anchor="middle" class="nodata">No new guests in this period</text>';
    }
    box.innerHTML = s + '</svg><div class="tt" role="status"></div>';
    box.classList.remove('hover');
    animate = false;

    G = { w: w, h: h, pl: pl, slot: slot, n: n, vals: vals, tips: d.tips, cx: cx, cy: cy };
  }

  function show(i) {
    if (!G) return;
    var v = G.vals[i], x = G.cx(i), y = G.cy(v);
    var hl = box.querySelector('.hl'), tt = box.querySelector('.tt');
    if (!hl || !tt) return;
    hl.setAttribute('x1', x); hl.setAttribute('x2', x);
    box.querySelectorAll('.hd').forEach(function (c) { c.setAttribute('cx', x); c.setAttribute('cy', y); });
    box.classList.add('hover');

    var change = '';
    if (i > 0) {
      var diff = v - G.vals[i - 1];
      change = diff > 0 ? '<span class="tt-c up">\u25B2 ' + diff + ' vs previous</span>'
             : diff < 0 ? '<span class="tt-c dn">\u25BC ' + Math.abs(diff) + ' vs previous</span>'
             :            '<span class="tt-c eq">No change vs previous</span>';
    }
    tt.innerHTML = '<span class="tt-d">' + esc(G.tips[i]) + '</span>'
      + '<span class="tt-v"><i style="background:' + COL + '"></i>' + v
      + '<small>new guest' + (v === 1 ? '' : 's') + '</small></span>' + change;

    var tw = tt.offsetWidth, th = tt.offsetHeight;
    var left = x + 14;
    if (left + tw > G.w - 4) left = x - tw - 14;
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

  document.querySelectorAll('[data-range]').forEach(function (b) {
    b.addEventListener('click', function () {
      range = b.getAttribute('data-range');
      document.querySelectorAll('[data-range]').forEach(function (x) { x.classList.toggle('on', x === b); });
      animate = true;
      updateSub();
      draw();
    });
  });

  if (window.ResizeObserver) { new ResizeObserver(draw).observe(box); }
  else { window.addEventListener('resize', draw); }
  updateSub();
  draw();
})();
</script>
<?php pageEnd();