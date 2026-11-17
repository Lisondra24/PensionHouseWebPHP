<?php
require_once __DIR__ . '/../views.php';
require_once __DIR__ . '/../roomview.php';
$user = requireRole('staff');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $id = (int)($_POST['id'] ?? 0);
    $res = apiAuth("rooms/$id/maintenance", 'PATCH', ['isMaintenance' => ($_POST['value'] ?? '0') === '1']);
    apiOk($res) ? flash('ok', 'Room status updated.') : flash('err', apiMessage($res, 'Could not update the room.'));
    header('Location: rooms.php');
    exit;
}

$res = apiAuth('rooms');
$rooms = is_array($res['data']) ? $res['data'] : [];
$counts = array_count_values(array_map('roomState', $rooms));

pageStart('Rooms', $user, 'See which rooms are free, occupied or under maintenance.');
?>
<div class="stats">
  <?= statCard('Available', $counts['AVAILABLE'] ?? 0, 'check', 'blue') ?>
  <?= statCard('Occupied', $counts['OCCUPIED'] ?? 0, 'guests.php', 'amber') ?>
  <?= statCard('Maintenance', $counts['MAINTENANCE'] ?? 0, 'tool', 'coral') ?>
</div>

<div class="toolbar">
  <?= chipsHtml(['ALL' => 'All rooms', 'AVAILABLE' => 'Available', 'OCCUPIED' => 'Occupied', 'MAINTENANCE' => 'Maintenance'],
                '#rg', ['ALL' => count($rooms)] + $counts) ?>
  <div class="tools">
    <div class="search"><?= searchIcon() ?><input data-filter="#rg" placeholder="Search room or guest" aria-label="Search rooms"></div>
    <?= rvToggleHtml() ?>
  </div>
</div>

<div id="rg">
  <div class="rgrid">
  <?php
  $items = [];
  foreach ($rooms as $r):
      $a = '';
      if ($r['status'] !== 'OCCUPIED') {
          $a = '<form method="post"' . confirmAttr(
                  $r['isMaintenance'] ? 'Mark Room ' . $r['roomNo'] . ' as available again?' : 'Put Room ' . $r['roomNo'] . ' under maintenance? Guests cannot book it meanwhile.',
                  $r['isMaintenance'] ? 'Mark available' : 'Maintenance', $r['isMaintenance'] ? 'Mark available' : 'Start maintenance', $r['isMaintenance'] ? 'green' : '') . '>'
             . csrfField() . '<input type="hidden" name="id" value="' . (int)$r['id'] . '">'
             . '<input type="hidden" name="value" value="' . ($r['isMaintenance'] ? '0' : '1') . '">'
             . '<button class="btn sm ' . ($r['isMaintenance'] ? 'green' : 'ghost') . '">' . navIcon('tool', 14)
             . ($r['isMaintenance'] ? 'Mark available' : 'Mark maintenance') . '</button></form>';
      }
      $items[] = ['room' => $r, 'actions' => $a];
      echo roomCard($r, $a);
  endforeach; ?>
  </div>
  <?= rvTableHtml($items) ?>
  <?php if (!$rooms): ?><?= emptyBlock('rooms.php', 'No active rooms', 'Ask an admin to add or enable a room.') ?><?php endif; ?>
  <?= noMatchBlock() ?>
</div>
<p class="muted" style="margin-top:1rem">A room becomes occupied when its booking is checked in, and free again when the booking is completed.</p>
<?= rvAssets() ?>
<?php pageEnd();