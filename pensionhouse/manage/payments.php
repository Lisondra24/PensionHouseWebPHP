<?php
require_once __DIR__ . '/../layout.php';
$user = requireStaffOrAdmin();

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

$filter = strtoupper($_GET['status'] ?? 'PENDING');
$res = apiAuth('payments' . ($filter ? '?status=' . urlencode($filter) : ''));
$payments = is_array($res['data']) ? $res['data'] : [];

function reviewForm(int $id, string $decision, string $label, string $cls): string
{
    return '<form method="post" style="display:inline">' . csrfField()
        . '<input type="hidden" name="payment_id" value="' . $id . '">'
        . '<input type="hidden" name="decision" value="' . e($decision) . '">'
        . '<button class="btn ' . $cls . '">' . e($label) . '</button></form> ';
}

pageStart('Payments', $user);

$tabs = ['PENDING' => 'Pending', 'APPROVED' => 'Approved', 'REJECTED' => 'Rejected', '' => 'All'];
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
    <th>Booking</th><th>Guest</th><th>Room</th><th>Stay</th><th>Method</th>
    <th>Amount</th><th>Reference code</th><th>Status</th><th>Actions</th>
  </tr>
  <?php foreach ($payments as $p): ?>
  <tr>
    <td><?= e($p['bookingCode']) ?></td>
    <td><?= e($p['guestName']) ?></td>
    <td><?= e($p['roomNo']) ?> - <?= e($p['roomTitle']) ?></td>
    <td><?= e(fmtDate($p['checkIn'])) ?><br><span class="muted">to <?= e(fmtDate($p['checkOut'])) ?></span></td>
    <td><?= e($p['method']) ?></td>
    <td><?= e(peso($p['amount'])) ?></td>
    <td><b><?= e($p['referenceCode'] ?? '-') ?></b></td>
    <td><?= badge($p['status']) ?>
        <?php if ($p['reviewedByRole']): ?><br><span class="muted">by <?= e($p['reviewedByRole']) ?></span><?php endif; ?></td>
    <td>
      <?php if ($p['status'] === 'PENDING'):
          echo reviewForm($p['id'], 'APPROVED', 'Approve', 'green');
          echo reviewForm($p['id'], 'REJECTED', 'Reject', 'red');
      endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$payments): ?><tr><td colspan="9" class="muted">Nothing here.</td></tr><?php endif; ?>
</table>
</div>
<p class="muted">Check the reference code against the GCash transaction before approving.</p>
<?php pageEnd();