<?php
require_once __DIR__ . '/../views.php';
$user = requireRole('admin');

$res = apiAuth('bookings?source=WALKIN');
$rows = is_array($res['data']) ? $res['data'] : [];
usort($rows, fn($a, $b) => strcmp($b['createdAt'], $a['createdAt']));

$today   = date('Y-m-d');
$todayN  = count(array_filter($rows, fn($b) => substr($b['createdAt'], 0, 10) === $today));
$inHouse = count(array_filter($rows, fn($b) => $b['status'] === 'CHECKED_IN'));
$sales   = array_sum(array_map(fn($b) => $b['status'] === 'CANCELLED' ? 0 : (float)$b['totalAmount'], $rows));

pageStart('Walk-in History', $user, 'Every walk-in booking made by the front desk. Only staff can create them.');
?>
<div class="stats">
  <?= statCard('Total walk-ins', count($rows), 'walkins.php', 'g') ?>
  <?= statCard('Today', $todayN, 'clock', 'blue') ?>
  <?= statCard('In-house now', $inHouse, 'guests.php', 'amber') ?>
  <?= statCard('Walk-in sales', peso($sales), 'payments.php', 'coral') ?>
</div>

<div class="toolbar">
  <span class="muted"><?= count($rows) ?> record<?= count($rows) === 1 ? '' : 's' ?>, newest first</span>
  <div class="search"><?= searchIcon() ?><input data-filter="#wt" placeholder="Search guest, room or code" aria-label="Search walk-ins"></div>
</div>

<div class="panel" style="padding:0;overflow:hidden">
<div class="scroll">
<table id="wt" style="border:0;border-radius:0">
  <tr><th>Guest</th><th>ID shown</th><th>Room</th><th>Stay</th><th>Total</th><th>Payment</th><th>Status</th></tr>
  <?php foreach ($rows as $b): ?>
  <tr data-text="<?= e(strtolower($b['guestName'] . ' ' . $b['roomNo'] . ' ' . $b['roomTitle'] . ' ' . $b['bookingCode'])) ?>">
    <td><span class="who"><?= avatar($b['guestName'], 32) ?><span><b><?= e($b['guestName']) ?></b><br>
        <span class="muted"><?= e($b['bookingCode']) ?><?= !empty($b['guestContact']) ? ' · ' . e($b['guestContact']) : '' ?></span></span></span></td>
    <td><?= e($b['idType'] ?? '-') ?></td>
    <td><?= e($b['roomTitle']) ?> <span class="muted">#<?= e($b['roomNo']) ?></span><br><span class="muted"><?= e($b['rateLabel']) ?></span></td>
    <td><?= e(fmtShort($b['checkIn'])) ?><br><span class="muted">to <?= e(fmtShort($b['checkOut'])) ?></span></td>
    <td><b><?= e(peso($b['totalAmount'])) ?></b></td>
    <td><?= e($b['paymentMethod'] ?? '-') ?>
        <?php if (!empty($b['referenceCode'])): ?><br><span class="muted">Ref: <?= e($b['referenceCode']) ?></span><?php endif; ?></td>
    <td><?= badge($b['status']) ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><?= emptyRow(7, 'walkins.php', 'No walk-in bookings yet', 'Walk-ins created by staff will be listed here.') ?><?php endif; ?>
  <?= noMatchRow(7) ?>
</table>
</div>
</div>
<?php pageEnd();