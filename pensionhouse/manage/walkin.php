<?php
require_once __DIR__ . '/../layout.php';
$user = requireStaffOrAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    [$roomId, $rateId] = array_map('intval', explode('|', $_POST['room_rate'] ?? '0|0'));
    $checkIn = trim($_POST['check_in'] ?? '');

    $res = apiAuth('bookings/walk-in', 'POST', [
        'guestName'     => trim($_POST['guest_name'] ?? ''),
        'guestContact'  => trim($_POST['guest_contact'] ?? '') ?: null,
        'roomId'        => $roomId,
        'rateId'        => $rateId,
        'checkIn'       => $checkIn !== '' ? date('Y-m-d\TH:i:s', strtotime($checkIn)) : null,
        'paymentMethod' => $_POST['payment_method'] ?? 'CASH',
        'referenceCode' => trim($_POST['reference_code'] ?? '') ?: null,
    ]);

    if (apiOk($res)) {
        flash('ok', 'Walk-in booking created.');
        header('Location: bookings.php');
    } else {
        flash('err', apiMessage($res, 'Could not create the booking.'));
        header('Location: walkin.php');
    }
    exit;
}

$res = apiAuth('rooms');
$rooms = is_array($res['data']) ? $res['data'] : [];

pageStart('Walk-in Booking', $user);
?>
<div class="card" style="max-width:520px">
  <form method="post">
    <?= csrfField() ?>
    <label>Guest name</label>
    <input name="guest_name" required style="width:100%">

    <label>Contact no.</label>
    <input name="guest_contact" style="width:100%">

    <label>Room and rate</label>
    <select name="room_rate" required style="width:100%">
      <option value="">Select...</option>
      <?php foreach ($rooms as $r): foreach ($r['rates'] as $rt): ?>
        <option value="<?= (int)$r['id'] ?>|<?= (int)$rt['id'] ?>">
          Room <?= e($r['roomNo']) ?> (<?= e($r['title']) ?>) - <?= e($rt['label']) ?> - <?= e(peso($rt['price'])) ?>
        </option>
      <?php endforeach; endforeach; ?>
    </select>

    <label>Check-in (leave empty for now)</label>
    <input type="datetime-local" name="check_in" style="width:100%">

    <label>Payment</label>
    <select name="payment_method" style="width:100%">
      <option value="CASH">Cash</option>
      <option value="GCASH">GCash</option>
    </select>

    <label>GCash reference code (only for GCash)</label>
    <input name="reference_code" style="width:100%">

    <p><button class="btn">Create booking</button></p>
  </form>
</div>
<?php pageEnd();