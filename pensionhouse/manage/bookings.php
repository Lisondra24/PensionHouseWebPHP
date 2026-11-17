<?php
require_once __DIR__ . '/../layout.php';
$user = requireStaffOrAdmin();

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
$res = apiAuth('bookings' . ($filter ? '?status=' . urlencode($filter) : ''));
$bookings = is_array($res['data']) ? $res['data'] : [];

function statusForm(int $id, string $status, string $label, string $cls = ''): string
{
    return '<form method="post" style="display:inline">' . csrfField()
        . '<input type="hidden" name="booking_id" value="' . $id . '">'
        . '<input type="hidden" name="status" value="' . e($status) . '">'
        . '<button class="btn ' . $cls . '">' . e($label) . '</button></form> ';
}

pageStart('Bookings', $user);

$tabs = ['' => 'All', 'PENDING' => 'Pending', 'CONFIRMED' => 'Confirmed',
         'CHECKED_IN' => 'Checked in', 'COMPLETED' => 'Completed', 'CANCELLED' => 'Cancelled'];
echo '<p class="tabs">';
foreach ($tabs as $key => $label) {
    $on = $filter === $key ? 'on' : '';
    echo '<a class="' . $on . '" href="?status=' . e($key) . '">' . e($label) . '</a>';
}
echo '</p>';
?>
<div class="scroll">
<table>
  <tr>
    <th>Code</th><th>Guest</th><th>Room</th><th>Rate</th><th>Check-in</th><th>Check-out</th>
    <th>Total</th><th>Payment</th><th>Status</th><th>Actions</th>
  </tr>
  <?php foreach ($bookings as $b): ?>
  <tr>
    <td><?= e($b['bookingCode']) ?><br><span class="muted"><?= e($b['source']) ?></span></td>
    <td><?= e($b['guestName']) ?><br><span class="muted"><?= e($b['guestContact'] ?? '') ?></span></td>
    <td><?= e($b['roomNo']) ?> - <?= e($b['roomTitle']) ?></td>
    <td><?= e($b['rateLabel']) ?></td>
    <td><?= e(fmtDate($b['checkIn'])) ?></td>
    <td><?= e(fmtDate($b['checkOut'])) ?></td>
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
  <?php if (!$bookings): ?><tr><td colspan="10" class="muted">No bookings found.</td></tr><?php endif; ?>
</table>
</div>
<?php pageEnd();