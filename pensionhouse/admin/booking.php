<?php
require_once __DIR__ . '/../layout.php';
$user = requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $id     = (int)($_POST['booking_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    $res = apiAuth("bookings/$id/status", 'PATCH', ['status' => $status]);
    if (apiOk($res)) {
        flash('ok', 'Booking updated.');
    } else {
        flash('err', apiMessage($res, 'Could not update the booking.'));
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

$filter = strtoupper($_GET['status'] ?? '');
$res = apiAuth('bookings');
$all = is_array($res['data']) ? $res['data'] : [];
usort($all, fn($a, $b) => strcmp($b['createdAt'], $a['createdAt']));
$bookings = $filter ? array_values(array_filter($all, fn($b) => $b['status'] === $filter)) : $all;

// Bookings created this month
$thisMonth = date('Y-m');
$monthCount = count(array_filter($all, fn($b) => substr($b['createdAt'], 0, 7) === $thisMonth));

// "This week" chart: check-ins per day, Monday to Sunday
$mon = strtotime('monday this week');
$keys = []; $labels = [];
for ($i = 0; $i < 7; $i++) {
    $t = strtotime("+$i day", $mon);
    $keys[] = date('Y-m-d', $t);
    $labels[] = date('D', $t);
}
$weekCounts = perDay($all, fn($b) => $b['checkIn'], $keys);
$weekMax = max($weekCounts) ?: 1;
$today = date('Y-m-d');

function statusForm(int $id, string $status, string $label, string $cls = ''): string
{
    return '<form method="post" style="display:inline">' . csrfField()
        . '<input type="hidden" name="booking_id" value="' . $id . '">'
        . '<input type="hidden" name="status" value="' . e($status) . '">'
        . '<button class="btn ' . $cls . '">' . e($label) . '</button></form> ';
}

pageStart('Bookings', $user, $monthCount . ' total this month', ['action' => newBookingBtn('admin')]);

$tabs = ['' => 'All', 'CONFIRMED' => 'Confirmed', 'PENDING' => 'Pending',
         'CHECKED_IN' => 'Checked in', 'COMPLETED' => 'Completed', 'CANCELLED' => 'Cancelled'];
?>
<style>
  .filters{display:flex;gap:1rem;flex-wrap:wrap;align-items:center;justify-content:space-between;margin-bottom:1rem}
  .filters .tabs{margin:0}
  .chart{display:flex;align-items:flex-end;gap:.9rem;height:130px;padding-top:.5rem}
  .chart .col{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;gap:.3rem;font-size:.68rem;color:var(--soft)}
  .chart .b{width:100%;max-width:46px;border-radius:5px 5px 0 0;background:#d9dedc;transition:background .15s}
  .chart .col:hover .b{background:#9fb6b0}
  .chart .col.today .b{background:var(--g)}
  .chart .n{font-weight:600;color:var(--ink);font-size:.72rem;min-height:1em}
</style>

<div class="filters">
  <div class="tabs">
    <?php foreach ($tabs as $key => $label): ?>
      <a class="<?= $filter === $key ? 'on' : '' ?>" href="?status=<?= e($key) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
  <div class="search"><?= searchIcon() ?><input id="q" placeholder="Search guest or room" aria-label="Search bookings"></div>
</div>

<div class="panel">
  <h3>This week</h3>
  <div class="chart">
    <?php foreach ($weekCounts as $i => $n): ?>
      <div class="col <?= $keys[$i] === $today ? 'today' : '' ?>" title="<?= (int)$n ?> check-in(s)">
        <span class="n"><?= $n ?: '' ?></span>
        <div class="b" style="height:<?= max(6, round($n / $weekMax * 100)) ?>%"></div>
        <span><?= e($labels[$i]) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="panel">
  <h3>Recent bookings</h3>
  <div class="scroll">
  <table id="bt">
    <tr>
      <th>Guest</th><th>Room</th><th>Check-in</th><th>Check-out</th>
      <th>Total</th><th>Payment</th><th>Status</th><th>Actions</th>
    </tr>
    <?php foreach ($bookings as $b): ?>
    <tr data-text="<?= e(strtolower($b['guestName'] . ' ' . $b['roomNo'] . ' ' . $b['roomTitle'] . ' ' . $b['bookingCode'])) ?>">
      <td><span class="who"><?= avatar($b['guestName'], 28) ?>
        <span><?= e($b['guestName']) ?><br><span class="muted"><?= e($b['bookingCode']) ?> · <?= e($b['source']) ?></span></span></span></td>
      <td><?= e($b['roomTitle']) ?> <span class="muted">#<?= e($b['roomNo']) ?></span><br><span class="muted"><?= e($b['rateLabel']) ?></span></td>
      <td><?= e(fmtShort($b['checkIn'])) ?></td>
      <td><?= e(fmtShort($b['checkOut'])) ?></td>
      <td><?= e(peso($b['totalAmount'])) ?></td>
      <td><?= e($b['paymentMethod'] ?? '-') ?> <?= $b['paymentStatus'] ? badge($b['paymentStatus']) : '' ?>
          <?php if (!empty($b['referenceCode'])): ?><br><span class="muted">Ref: <?= e($b['referenceCode']) ?></span><?php endif; ?></td>
      <td><?= badge($b['status']) ?></td>
      <td>
        <?php
          if ($b['status'] === 'CONFIRMED')  echo statusForm($b['id'], 'CHECKED_IN', 'Check in', 'green');
          if ($b['status'] === 'CHECKED_IN') echo statusForm($b['id'], 'COMPLETED', 'Complete', 'green');
          if (in_array($b['status'], ['PENDING', 'CONFIRMED'], true)) echo statusForm($b['id'], 'CANCELLED', 'Cancel', 'red');
        ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$bookings): ?><tr><td colspan="8" class="muted">No bookings found.</td></tr><?php endif; ?>
  </table>
  </div>
</div>

<script>
  document.getElementById('q').addEventListener('input', function () {
    var q = this.value.toLowerCase();
    document.querySelectorAll('#bt tr[data-text]').forEach(function (r) {
      r.hidden = !r.dataset.text.includes(q);
    });
  });
</script>
<?php pageEnd();