<?php
require_once __DIR__ . '/../layout.php';
$user = requireStaffOrAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $act = $_POST['act'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);
    $res = ['status' => 0, 'data' => null];

    switch ($act) {
        case 'add_room':
            $res = apiAuth('rooms', 'POST', [
                'roomNo'      => trim($_POST['room_no'] ?? ''),
                'title'       => trim($_POST['title'] ?? ''),
                'description' => trim($_POST['description'] ?? '') ?: null,
                'capacity'    => (int)($_POST['capacity'] ?? 2),
                'imageUrl'    => null,
            ]);
            break;
        case 'toggle_room':
            $res = apiAuth("rooms/$id/active", 'PATCH', ['isActive' => ($_POST['active'] ?? '0') === '1']);
            break;
        case 'add_rate':
            $res = apiAuth("rooms/$id/rates", 'POST', [
                'label' => trim($_POST['label'] ?? ''),
                'hours' => (int)($_POST['hours'] ?? 0),
                'price' => (float)($_POST['price'] ?? 0),
            ]);
            break;
        case 'del_rate':
            $res = apiAuth("rooms/rates/$id", 'DELETE');
            break;
    }

    if (apiOk($res)) {
        flash('ok', 'Saved.');
    } else {
        flash('err', apiMessage($res, 'Could not save. Check the values and try again.'));
    }
    header('Location: rooms.php');
    exit;
}

$res = apiAuth('rooms?includeInactive=true');
$rooms = is_array($res['data']) ? $res['data'] : [];

pageStart('Rooms', $user);
?>
<div class="card">
  <b>Add room</b>
  <form method="post" class="row">
    <?= csrfField() ?>
    <input type="hidden" name="act" value="add_room">
    <div><label>Room no.</label><input name="room_no" required size="8"></div>
    <div><label>Title</label><input name="title" required></div>
    <div><label>Description</label><input name="description" size="36"></div>
    <div><label>Capacity</label><input type="number" name="capacity" value="2" min="1" max="20" style="width:70px"></div>
    <button class="btn">Add</button>
  </form>
</div>

<?php foreach ($rooms as $r): ?>
<div class="card">
  <div class="row" style="justify-content:space-between">
    <div>
      <b>Room <?= e($r['roomNo']) ?> - <?= e($r['title']) ?></b>
      <?= $r['isActive'] ? badge('APPROVED') : badge('CANCELLED') ?>
      <div class="muted"><?= e($r['description'] ?? '') ?> (sleeps <?= (int)$r['capacity'] ?>)</div>
    </div>
    <form method="post">
      <?= csrfField() ?>
      <input type="hidden" name="act" value="toggle_room">
      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <input type="hidden" name="active" value="<?= $r['isActive'] ? '0' : '1' ?>">
      <button class="btn gray"><?= $r['isActive'] ? 'Disable room' : 'Enable room' ?></button>
    </form>
  </div>

  <table style="margin-top:.75rem">
    <tr><th>Rate</th><th>Hours</th><th>Price</th><th></th></tr>
    <?php foreach ($r['rates'] as $rt): if (!$rt['isActive']) continue; ?>
    <tr>
      <td><?= e($rt['label']) ?></td>
      <td><?= (int)$rt['hours'] ?></td>
      <td><?= e(peso($rt['price'])) ?></td>
      <td>
        <form method="post" onsubmit="return confirm('Remove this rate?')">
          <?= csrfField() ?>
          <input type="hidden" name="act" value="del_rate">
          <input type="hidden" name="id" value="<?= (int)$rt['id'] ?>">
          <button class="btn red">Remove</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>

  <form method="post" class="row" style="margin-top:.5rem">
    <?= csrfField() ?>
    <input type="hidden" name="act" value="add_rate">
    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
    <div><label>Label</label><input name="label" placeholder="e.g. 3 Hours" required></div>
    <div><label>Hours</label><input type="number" name="hours" min="1" required style="width:80px"></div>
    <div><label>Price</label><input type="number" name="price" min="1" step="0.01" required style="width:110px"></div>
    <button class="btn">Add rate</button>
  </form>
</div>
<?php endforeach; ?>
<?php pageEnd();