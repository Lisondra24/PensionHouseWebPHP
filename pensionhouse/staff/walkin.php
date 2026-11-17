<?php
require_once __DIR__ . '/../views.php';
$user = requireRole('staff');

$idTypes = ['Passport', "Driver's License", 'National ID (PhilSys)', 'UMID', "Voter's ID", 'Postal ID', 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();

    if (($_POST['act'] ?? '') === 'complete') {
        $res = apiAuth('bookings/' . (int)($_POST['booking_id'] ?? 0) . '/status', 'PATCH', ['status' => 'COMPLETED']);
        apiOk($res) ? flash('ok', 'Guest checked out. The room is free again.')
                    : flash('err', apiMessage($res, 'Could not check out the guest.'));
        header('Location: walkin.php');
        exit;
    }

    [$roomId, $rateId] = array_map('intval', explode('|', $_POST['room_rate'] ?? '0|0'));
    $method = ($_POST['payment_method'] ?? 'CASH') === 'GCASH' ? 'GCASH' : 'CASH';
    $idType = $_POST['id_type'] ?? '';

    if (!in_array($idType, $idTypes, true)) {
        flash('err', 'Choose the type of ID the guest showed.');
        header('Location: walkin.php');
        exit;
    }

    $res = apiAuth('bookings/walk-in', 'POST', [
        'guestName'     => trim($_POST['guest_name'] ?? ''),
        'guestContact'  => trim($_POST['guest_contact'] ?? '') ?: null,
        'idType'        => $idType,
        'roomId'        => $roomId,
        'rateId'        => $rateId,
        'checkIn'       => null,
        'paymentMethod' => $method,
        'referenceCode' => $method === 'GCASH' ? (trim($_POST['reference_code'] ?? '') ?: null) : null,
    ]);

    if (apiOk($res)) {
        flash('ok', 'Walk-in saved. It appears in the list below.');
    } else {
        flash('err', apiMessage($res, 'Could not create the booking.'));
    }
    header('Location: walkin.php');
    exit;
}

$res = apiAuth('rooms');
$rooms = is_array($res['data']) ? $res['data'] : [];

// Only rooms that have at least one rate can be booked
$bookable = array_values(array_filter($rooms, fn($r) => !empty($r['rates'])));

// Rates per room for the "stay" picker (filled in by JS when a room is chosen)
$rateMap = [];
foreach ($bookable as $r) {
    $rateMap[(int)$r['id']] = [
        'no'    => (string)$r['roomNo'],
        'rates' => array_map(fn($rt) => [
            'id'    => (int)$rt['id'],
            'label' => (string)$rt['label'],
            'price' => (float)$rt['price'],
        ], array_values($r['rates'])),
    ];
}

$wi = apiAuth('bookings?source=WALKIN');
$walkins = is_array($wi['data']) ? $wi['data'] : [];
usort($walkins, fn($a, $b) => strcmp($b['createdAt'], $a['createdAt']));

pageStart('Walk-in Booking', $user, 'Register a guest who arrived at the front desk.');
?>
<style>
  .wcols{display:grid;grid-template-columns:2fr 1fr;gap:1.25rem;align-items:start}
  @media(max-width:960px){.wcols{grid-template-columns:1fr}}
  .wcols input:not([type=radio]),.wcols select{width:100%}
  .sticky{position:sticky;top:1rem}
  .step{display:flex;align-items:center;gap:.6rem;margin-bottom:.8rem;font-weight:600;font-size:.88rem}
  .step i{width:24px;height:24px;border-radius:50%;background:var(--g);color:#fff;font-style:normal;font-size:.72rem;display:grid;place-items:center}

  .sub{display:flex;justify-content:space-between;align-items:baseline;gap:.5rem;margin:0 0 .55rem;font-size:.78rem;font-weight:600;color:var(--soft);text-transform:uppercase;letter-spacing:.04em}
  .sub span{font-weight:500;text-transform:none;letter-spacing:0}
  .legend{display:flex;gap:.9rem;font-size:.74rem;color:var(--soft)}
  .legend b{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:.35rem;vertical-align:baseline}
  .legend .a{background:#2f8f5b}.legend .o{background:#d9822b}.legend .m{background:#9a947c}

  .rooms-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(112px,1fr));gap:.6rem}
  .rtile{position:relative;margin:0;display:flex;flex-direction:column;gap:.15rem;padding:.7rem .8rem;border:1px solid var(--line);border-radius:12px;background:#fff;cursor:pointer;transition:border-color .15s,background .15s,box-shadow .15s}
  .rtile:hover{border-color:var(--g)}
  .rtile input{position:absolute;opacity:0;pointer-events:none}
  .rtile .no{font-size:1.05rem;font-weight:700;color:var(--g)}
  .rtile .ty{font-size:.74rem;color:var(--soft);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .rtile .st{display:flex;align-items:center;gap:.35rem;margin-top:.25rem;font-size:.7rem;color:var(--soft)}
  .rtile .st::before{content:"";width:8px;height:8px;border-radius:50%;background:#2f8f5b}
  .rtile.occupied .st::before{background:#d9822b}
  .rtile.maintenance .st::before{background:#9a947c}
  .rtile:has(input:checked){border-color:var(--coral);background:#fff1ee;box-shadow:0 0 0 1px var(--coral)}
  .rtile:has(input:focus-visible){outline:2px solid var(--coral);outline-offset:2px}
  .rtile:has(input:disabled){cursor:not-allowed;background:#f4f2ea;border-style:dashed;opacity:.65}
  .rtile:has(input:disabled):hover{border-color:var(--line)}

  .stay{margin-top:1.3rem;padding-top:1.1rem;border-top:1px solid #ece7d6}
  .opts{display:flex;flex-wrap:wrap;gap:.55rem}
  .opt{position:relative;margin:0;display:flex;flex-direction:column;align-items:center;min-width:96px;padding:.55rem .85rem;
       border:1px solid var(--line);border-radius:12px;cursor:pointer;font-size:.8rem;text-align:center;background:#fff;transition:border-color .15s,background .15s}
  .opt:hover{border-color:var(--g)}
  .opt input{position:absolute;opacity:0;pointer-events:none}
  .opt small{color:var(--soft);font-size:.72rem}
  .opt:has(input:checked){border-color:var(--coral);background:#fff1ee;box-shadow:0 0 0 1px var(--coral)}
  .opt:has(input:focus-visible){outline:2px solid var(--coral);outline-offset:2px}
  .stay-empty{margin:0;padding:.85rem 1rem;border:1px dashed #d8d2bd;border-radius:12px;font-size:.82rem;color:var(--soft);background:#faf8ef}

  .sumrow{display:flex;justify-content:space-between;gap:1rem;font-size:.8rem;padding:.4rem 0;border-bottom:1px dashed #e3ddc6}
  .sumrow span:first-child{color:var(--soft)}
  .total{display:flex;justify-content:space-between;align-items:center;margin:1rem 0;font-weight:600}
  .total b{font-size:1.4rem}
</style>

<form method="post" id="wf">
  <?= csrfField() ?>
  <input type="hidden" name="act" value="create">
  <input type="hidden" name="room_rate" id="roomRate" value="0|0">
  <div class="wcols">
    <div>
      <div class="card">
        <div class="step"><i>1</i>Guest details</div>
        <div class="fgrid">
          <div><label for="gn">Full name</label><input id="gn" name="guest_name" required maxlength="100" placeholder="Guest's full name"></div>
          <div><label for="gc">Mobile number</label><input id="gc" name="guest_contact" maxlength="20" placeholder="09123456789"></div>
          <div><label for="idt">ID shown by guest</label>
            <select id="idt" name="id_type" required>
              <option value="">Select ID type...</option>
              <?php foreach ($idTypes as $t): ?><option><?= e($t) ?></option><?php endforeach; ?>
            </select></div>
        </div>
      </div>

      <div class="card">
        <div class="step"><i>2</i>Room and length of stay</div>

        <div class="sub">Room
          <div class="legend"><span><b class="a"></b>Available</span><span><b class="o"></b>Occupied</span><span><b class="m"></b>Maintenance</span></div>
        </div>
        <div class="rooms-grid">
          <?php foreach ($bookable as $r):
              $status = strtoupper($r['status'] ?? 'AVAILABLE');
              $free   = $status === 'AVAILABLE'; ?>
            <label class="rtile <?= e(strtolower($status)) ?>">
              <input type="radio" name="room" value="<?= (int)$r['id'] ?>" required <?= $free ? '' : 'disabled' ?>>
              <span class="no">Room <?= e($r['roomNo']) ?></span>
              <span class="ty"><?= e($r['title']) ?></span>
              <span class="st"><?= $free ? 'Sleeps ' . (int)$r['capacity'] : e(ucfirst(strtolower($status))) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <?php if (!$bookable): ?><?= emptyBlock('rooms.php', 'No bookable rooms', 'Ask an admin to enable a room and add its rates.') ?><?php endif; ?>

        <div class="stay">
          <div class="sub">Length of stay <span id="stayFor"></span></div>
          <p class="stay-empty" id="stayEmpty">Pick a room to see its stay options.</p>
          <div class="opts" id="rates" hidden></div>
        </div>
      </div>
    </div>

    <div class="card sticky">
      <div class="step"><i>3</i>Payment</div>
      <div class="opts" style="margin-bottom:1rem">
        <label class="opt"><input type="radio" name="payment_method" value="CASH" checked>Cash at desk</label>
        <label class="opt"><input type="radio" name="payment_method" value="GCASH">GCash</label>
      </div>
      <div id="ref" hidden>
        <label for="rc">GCash reference code</label>
        <input id="rc" name="reference_code" maxlength="50" placeholder="Reference no. from the receipt">
      </div>

      <div style="margin-top:1rem">
        <div class="sumrow"><span>Room</span><b id="sRoom">-</b></div>
        <div class="sumrow"><span>Stay</span><b id="sRate">-</b></div>
      </div>
      <div class="total"><span>Total due</span><b id="total"><?= e(peso(0)) ?></b></div>
      <button class="btn coral wide">Confirm walk-in booking</button>
      <p class="muted" style="margin:.7rem 0 0">Cash is paid at the desk, so the booking is confirmed right away. GCash needs the reference code.</p>
    </div>
  </div>
</form>
<script>
  (function () {
    var ROOMS = <?= json_encode($rateMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    var total = document.getElementById('total'), ref = document.getElementById('ref'), rc = document.getElementById('rc');
    var sRoom = document.getElementById('sRoom'), sRate = document.getElementById('sRate');
    var ratesBox = document.getElementById('rates'), empty = document.getElementById('stayEmpty');
    var stayFor = document.getElementById('stayFor'), roomRate = document.getElementById('roomRate');
    var ZERO = total.textContent;

    function peso(n) { return '\u20b1' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2 }); }

    function resetStay() {
      roomRate.value = '0|0';
      sRate.textContent = '-';
      total.textContent = ZERO;
    }

    function showRates(roomId) {
      var room = ROOMS[roomId];
      ratesBox.innerHTML = '';
      resetStay();
      if (!room) { ratesBox.hidden = true; empty.hidden = false; stayFor.textContent = ''; return; }

      sRoom.textContent = 'Room ' + room.no;
      stayFor.textContent = 'for Room ' + room.no;
      empty.hidden = true;
      ratesBox.hidden = false;

      room.rates.forEach(function (rt) {
        var lab = document.createElement('label'); lab.className = 'opt';
        var inp = document.createElement('input');
        inp.type = 'radio'; inp.name = 'rate'; inp.required = true; inp.value = rt.id;
        inp.addEventListener('change', function () {
          roomRate.value = roomId + '|' + rt.id;
          sRate.textContent = rt.label;
          total.textContent = peso(rt.price);
        });
        var t = document.createTextNode(rt.label);
        var s = document.createElement('small'); s.textContent = peso(rt.price);
        lab.appendChild(inp); lab.appendChild(t); lab.appendChild(s);
        ratesBox.appendChild(lab);
      });
    }

    document.querySelectorAll('[name=room]').forEach(function (i) {
      i.addEventListener('change', function () { showRates(i.value); });
    });

    document.querySelectorAll('[name=payment_method]').forEach(function (i) {
      i.addEventListener('change', function () {
        ref.hidden = i.value !== 'GCASH';
        rc.required = i.value === 'GCASH';
      });
    });
  })();
</script>

<div class="toolbar" style="margin-top:2rem">
  <h2 style="font-size:1.05rem;margin:0">Saved walk-ins</h2>
  <div class="search"><?= searchIcon() ?><input data-filter="#wt" placeholder="Search guest, room or code" aria-label="Search walk-ins"></div>
</div>
<div class="panel" style="padding:0;overflow:hidden">
<div class="scroll">
<table id="wt" style="border:0;border-radius:0">
  <tr><th>Guest</th><th>ID shown</th><th>Room</th><th>Stay</th><th>Total</th><th>Payment</th><th>Status</th><th>Actions</th></tr>
  <?php foreach ($walkins as $w): ?>
  <tr data-text="<?= e(strtolower($w['guestName'] . ' ' . $w['roomNo'] . ' ' . $w['roomTitle'] . ' ' . $w['bookingCode'])) ?>">
    <td><span class="who"><?= avatar($w['guestName'], 32) ?><span><b><?= e($w['guestName']) ?></b><br>
        <span class="muted"><?= e($w['bookingCode']) ?><?= !empty($w['guestContact']) ? ' · ' . e($w['guestContact']) : '' ?></span></span></span></td>
    <td><?= e($w['idType'] ?? '-') ?></td>
    <td><?= e($w['roomTitle']) ?> <span class="muted">#<?= e($w['roomNo']) ?></span><br><span class="muted"><?= e($w['rateLabel']) ?></span></td>
    <td><?= e(fmtShort($w['checkIn'])) ?><br><span class="muted">to <?= e(fmtShort($w['checkOut'])) ?></span></td>
    <td><b><?= e(peso($w['totalAmount'])) ?></b></td>
    <td><?= e($w['paymentMethod'] ?? '-') ?>
        <?php if (!empty($w['referenceCode'])): ?><br><span class="muted">Ref: <?= e($w['referenceCode']) ?></span><?php endif; ?></td>
    <td><?= badge($w['status']) ?></td>
    <td>
      <?php if ($w['status'] === 'CHECKED_IN'): ?>
      <form method="post"<?= confirmAttr('Check out ' . $w['guestName'] . ' from Room ' . $w['roomNo'] . '?', 'Check out guest', 'Check out', 'green') ?>>
        <?= csrfField() ?>
        <input type="hidden" name="act" value="complete">
        <input type="hidden" name="booking_id" value="<?= (int)$w['id'] ?>">
        <button class="btn sm green"><?= navIcon('check', 14) ?>Check out</button>
      </form>
      <?php else: ?><span class="muted">-</span><?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$walkins): ?><?= emptyRow(8, 'walkin.php', 'No walk-ins yet', 'Walk-ins you save will be listed here.') ?><?php endif; ?>
  <?= noMatchRow(8) ?>
</table>
</div>
</div>
<?php pageEnd();