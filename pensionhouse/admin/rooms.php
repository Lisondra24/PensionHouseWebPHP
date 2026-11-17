<?php
require_once __DIR__ . '/../views.php';
require_once __DIR__ . '/../roomphotos.php';
require_once __DIR__ . '/../roomview.php';
$user = requireRole('admin');

function fetchRooms(): array
{
    $res = apiAuth('rooms?includeInactive=true');
    return is_array($res['data']) ? $res['data'] : [];
}

/** A room's photo URLs in order. Falls back to its single imageUrl if it has no gallery yet. */
function roomPhotoList(array $r): array
{
    $p = array_values(array_filter($r['photos'] ?? [], 'is_string'));
    if (!$p && !empty($r['imageUrl'])) $p = [$r['imageUrl']];
    return $p;
}

/** Small icons used only on this page: settings (Manage), eye (Enable), eye-off (Disable). */
function rmIcon(string $name, int $size = 14): string
{
    $paths = [
        'settings' => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
        'eye'      => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off'  => '<path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/>',
    ];
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . ($paths[$name] ?? '') . '</svg>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // If the total upload is bigger than post_max_size, PHP drops $_POST completely.
    if (empty($_POST) && !empty($_SERVER['CONTENT_LENGTH'])) {
        flash('err', 'Upload too large. Try fewer or smaller photos.');
        header('Location: rooms.php');
        exit;
    }
    checkCsrf();

    $act = $_POST['act'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);
    $on  = ($_POST['value'] ?? '0') === '1';
    $room = [
        'roomNo'      => trim($_POST['room_no'] ?? ''),
        'title'       => trim($_POST['title'] ?? ''),
        'description' => trim($_POST['description'] ?? '') ?: null,
        'capacity'    => (int)($_POST['capacity'] ?? 2),
        'imageUrl'    => null,
    ];
    $rate = ['label' => trim($_POST['label'] ?? ''), 'hours' => (int)($_POST['hours'] ?? 0), 'price' => (float)($_POST['price'] ?? 0)];

    // ----- Photo gallery (add / update room only) -----
    $isRoomSave = in_array($act, ['add_room', 'update_room'], true);
    $touch    = false;  // true only when the photo manager was submitted
    $final    = [];     // ordered photo URLs after this save
    $current  = [];     // photo URLs before this save
    $newSaved = [];     // files written during this request (deleted again if the API save fails)

    if ($isRoomSave) {
        if ($act === 'update_room') {
            $oldUrl = null;
            foreach (fetchRooms() as $x) {
                if ((int)$x['id'] === $id) { $current = roomPhotoList($x); $oldUrl = $x['imageUrl'] ?? null; break; }
            }
            $room['imageUrl'] = $oldUrl;   // unchanged unless the photo manager says otherwise
        }

        if (isset($_POST['photo_order'])) {
            $touch   = true;
            $order   = json_decode((string)$_POST['photo_order'], true);
            $uploads = collectUploads('photos');
            if (!is_array($order)) $order = [];

            foreach ($order as $tok) {
                if (!is_string($tok) || count($final) >= ROOM_PHOTO_LIMIT) continue;

                if (str_starts_with($tok, 'e:')) {                 // existing photo: keep it (must really belong to this room)
                    $key = substr($tok, 2);
                    if (in_array($key, $current, true) && !in_array($key, $final, true)) $final[] = $key;
                } elseif (str_starts_with($tok, 'n:')) {           // newly uploaded photo
                    $i = (int)substr($tok, 2);
                    if (!isset($uploads[$i])) continue;
                    [$name, $err] = saveUpload($uploads[$i]);
                    unset($uploads[$i]);
                    if ($err) {
                        foreach ($newSaved as $f) @unlink(ROOM_UPLOAD_DIR . '/' . $f);
                        flash('err', $err);
                        header('Location: rooms.php');
                        exit;
                    }
                    $newSaved[] = $name;
                    $final[]    = roomPhotoUrl($name);
                }
            }
            // The first photo is the cover that admin and staff see everywhere.
            $room['photos']   = $final;
            $room['imageUrl'] = $final[0] ?? null;
        }
    }

    $res = match ($act) {
        'add_room'      => apiAuth('rooms', 'POST', $room),
        'update_room'   => apiAuth("rooms/$id", 'PUT', $room),
        'toggle_active' => apiAuth("rooms/$id/active", 'PATCH', ['isActive' => $on]),
        'maintenance'   => apiAuth("rooms/$id/maintenance", 'PATCH', ['isMaintenance' => $on]),
        'add_rate'      => apiAuth("rooms/$id/rates", 'POST', $rate),
        'update_rate'   => apiAuth("rooms/rates/$id", 'PUT', $rate),
        'del_rate'      => apiAuth("rooms/rates/$id", 'DELETE'),
        default         => ['status' => 0, 'data' => null],
    };

    $ok = apiOk($res);
    if ($isRoomSave && $touch) {
        if ($ok) {
            foreach (array_diff($current, $final) as $gone) deleteStoredPhoto($gone);   // removed photos
        } else {
            foreach ($newSaved as $f) @unlink(ROOM_UPLOAD_DIR . '/' . $f);               // API refused: discard uploads
        }
    }

    $ok ? flash('ok', 'Saved.') : flash('err', apiMessage($res, 'Could not save. Check the values and try again.'));
    header('Location: rooms.php');
    exit;
}

$rooms = fetchRooms();

$counts = array_count_values(array_map('roomState', $rooms));
$active = count($rooms) - ($counts['DISABLED'] ?? 0);
$occPct = $active ? round(($counts['OCCUPIED'] ?? 0) / $active * 100) : 0;

/** Photos of a room as [['key'=>..., 'url'=>...], ...] for the photo manager. */
function roomPhotoItems(array $r): array
{
    return array_map(fn($u) => ['key' => $u, 'url' => $u], roomPhotoList($r));
}

function roomFormFields(?array $r = null): string
{
    $v = fn($k) => e($r[$k] ?? '');
    return '<div class="rm-fields">'
         . '<div><label>Room no.</label><input name="room_no" required maxlength="20" value="' . $v('roomNo') . '"></div>'
         . '<div><label>Type / title</label><input name="title" required maxlength="100" value="' . $v('title') . '"></div>'
         . '<div><label>Capacity (guests)</label><input type="number" name="capacity" min="1" max="20" value="' . e($r['capacity'] ?? 2) . '"></div>'
         . '<div></div>'
         . '<div class="full"><label>Description</label><textarea name="description" maxlength="1000" placeholder="What guests should know about this room">' . $v('description') . '</textarea></div>'
         . '</div>';
}

function photoManager(array $items): string
{
    return '<div class="pm" data-items="' . e(json_encode($items, JSON_UNESCAPED_SLASHES)) . '">'
         . '<div class="pm-top"><div><b>Room photos</b>'
         . '<p class="pm-help">The first photo is the cover that admin and staff see. Guests in the app can view all photos.</p></div>'
         . '<span class="pm-count"></span></div>'
         . '<div class="pm-grid"></div>'
         . '<p class="pm-msg" role="status" aria-live="polite"></p>'
         . '<input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple hidden>'
         . '<input type="hidden" name="photo_order" value="[]">'
         . '</div>';
}

pageStart('Rooms', $user, count($rooms) . ' room' . (count($rooms) === 1 ? '' : 's') . ' · manage details, photos, prices and availability',
    ['action' => '<button type="button" class="btn coral big" data-open="#d-new">+ Add room</button>']);
?>
<div class="stats">
  <?= statCard('Total rooms', count($rooms), 'rooms.php', 'g') ?>
  <?= statCard('Available', $counts['AVAILABLE'] ?? 0, 'check', 'blue') ?>
  <?= statCard('Occupied', $counts['OCCUPIED'] ?? 0, 'guests.php', 'amber') ?>
  <?= statCard('Occupancy', $occPct . '%', 'dashboard.php', 'coral') ?>
</div>

<div class="toolbar">
  <?= chipsHtml(['ALL' => 'All', 'AVAILABLE' => 'Available', 'OCCUPIED' => 'Occupied', 'MAINTENANCE' => 'Maintenance', 'DISABLED' => 'Disabled'],
                '#rg', ['ALL' => count($rooms)] + $counts) ?>
  <div class="tools">
    <div class="search"><?= searchIcon() ?><input data-filter="#rg" placeholder="Search room or type" aria-label="Search rooms"></div>
    <?= rvToggleHtml() ?>
  </div>
</div>

<div id="rg">
  <div class="rgrid">
  <?php
  $items = [];
  foreach ($rooms as $r):
      $st = roomState($r);
      $a  = '<button type="button" class="btn sm" data-open="#d' . (int)$r['id'] . '">' . rmIcon('settings', 14) . 'Manage</button>';
      if ($st !== 'OCCUPIED' && $st !== 'DISABLED') {
          $a .= '<form method="post">' . csrfField() . '<input type="hidden" name="act" value="maintenance">'
              . '<input type="hidden" name="id" value="' . (int)$r['id'] . '"><input type="hidden" name="value" value="' . ($r['isMaintenance'] ? '0' : '1') . '">'
              . '<button class="btn sm ghost">' . navIcon('tool', 14) . ($r['isMaintenance'] ? 'Mark available' : 'Maintenance') . '</button></form>';
      }
      $a .= '<form method="post"' . confirmAttr($r['isActive'] ? 'Disable Room ' . $r['roomNo'] . '? Guests will not see it.' : 'Enable Room ' . $r['roomNo'] . '?',
                  $r['isActive'] ? 'Disable room' : 'Enable room', $r['isActive'] ? 'Disable' : 'Enable', $r['isActive'] ? 'red' : 'green') . '>'
          . csrfField() . '<input type="hidden" name="act" value="toggle_active">'
          . '<input type="hidden" name="id" value="' . (int)$r['id'] . '"><input type="hidden" name="value" value="' . ($r['isActive'] ? '0' : '1') . '">'
          . '<button class="btn sm ' . ($r['isActive'] ? 'ghost' : 'green') . '">' . rmIcon($r['isActive'] ? 'eye-off' : 'eye', 14) . ($r['isActive'] ? 'Disable' : 'Enable') . '</button></form>';
      $items[] = ['room' => $r, 'actions' => $a];
      echo roomCard($r, $a);
  endforeach; ?>
  </div>
  <?= rvTableHtml($items) ?>
  <?php if (!$rooms): ?><?= emptyBlock('rooms.php', 'No rooms yet', 'Use "Add room" to create the first one.') ?><?php endif; ?>
  <?= noMatchBlock() ?>
</div>

<?php /* ---------- Add room ---------- */ modalStart('d-new', 'Add room', 'lg'); ?>
<div class="rm">
  <div class="rm-tabs" role="tablist">
    <button type="button" role="tab" data-tab="details">Details</button>
    <button type="button" role="tab" data-tab="photos">Photos</button>
  </div>
  <form method="post" enctype="multipart/form-data"><?= csrfField() ?><input type="hidden" name="act" value="add_room">
    <div data-pane="details"><?= roomFormFields() ?></div>
    <div data-pane="photos" hidden><?= photoManager([]) ?></div>
    <p class="muted" style="margin:.9rem 0 0">Add its rates right after creating the room.</p>
    <div class="rm-foot" data-foot>
      <button type="button" class="btn ghost" data-close>Cancel</button>
      <button class="btn coral">Create room</button>
    </div>
  </form>
</div>
<?php modalEnd(); ?>

<?php /* ---------- Manage room ---------- */
foreach ($rooms as $r):
    $rid   = (int)$r['id'];
    $rates = array_filter($r['rates'], fn($x) => $x['isActive']);
    $items = roomPhotoItems($r);
    $cover = $items[0]['url'] ?? null;
    modalStart('d' . $rid, 'Room ' . $r['roomNo'] . ' - ' . $r['title'], 'lg'); ?>
<div class="rm">
  <div class="rm-sum">
    <div class="rm-cover"><?= $cover ? '<img src="' . e($cover) . '" alt="Cover photo">' : 'No photo' ?></div>
    <div>
      <h4>Room <?= e($r['roomNo']) ?> &middot; <?= e($r['title']) ?></h4>
      <div class="rm-meta">
        <span class="rm-chip"><?= e(ucfirst(strtolower(roomState($r)))) ?></span>
        <span class="rm-chip">Sleeps <?= (int)$r['capacity'] ?></span>
        <span class="rm-chip"><?= count($items) ?> photo<?= count($items) === 1 ? '' : 's' ?></span>
        <span class="rm-chip"><?= count($rates) ?> rate<?= count($rates) === 1 ? '' : 's' ?></span>
      </div>
    </div>
  </div>

  <div class="rm-tabs" role="tablist">
    <button type="button" role="tab" data-tab="details">Details</button>
    <button type="button" role="tab" data-tab="photos">Photos (<?= count($items) ?>)</button>
    <button type="button" role="tab" data-tab="rates">Rates (<?= count($rates) ?>)</button>
  </div>

  <form method="post" enctype="multipart/form-data"><?= csrfField() ?><input type="hidden" name="act" value="update_room">
    <input type="hidden" name="id" value="<?= $rid ?>">
    <div data-pane="details"><?= roomFormFields($r) ?></div>
    <div data-pane="photos" hidden><?= photoManager($items) ?></div>
    <div class="rm-foot" data-foot>
      <button type="button" class="btn ghost" data-close>Close</button>
      <button class="btn coral">Save changes</button>
    </div>
  </form>

  <div data-pane="rates" hidden>
    <p class="muted" style="margin:0 0 .8rem">Guests can only book this room when it has at least one rate.</p>
    <?php foreach ($rates as $rt): ?>
      <div class="rate">
        <form method="post"><?= csrfField() ?><input type="hidden" name="act" value="update_rate">
          <input type="hidden" name="id" value="<?= (int)$rt['id'] ?>">
          <div><label>Label</label><input name="label" required value="<?= e($rt['label']) ?>" style="width:140px"></div>
          <div><label>Hours</label><input type="number" name="hours" min="1" required value="<?= (int)$rt['hours'] ?>" style="width:80px"></div>
          <div><label>Price</label><input type="number" name="price" min="1" step="0.01" required value="<?= e($rt['price']) ?>" style="width:110px"></div>
          <button class="btn sm">Save</button>
        </form>
        <form method="post"<?= confirmAttr('Remove the "' . $rt['label'] . '" rate from Room ' . $r['roomNo'] . '?', 'Remove rate', 'Remove', 'red') ?>>
          <?= csrfField() ?><input type="hidden" name="act" value="del_rate"><input type="hidden" name="id" value="<?= (int)$rt['id'] ?>">
          <button class="btn sm red"><?= navIcon('trash', 14) ?>Remove</button>
        </form>
      </div>
    <?php endforeach; ?>
    <?php if (!$rates): ?><p class="muted">No rates yet. Add the first one below.</p><?php endif; ?>

    <form method="post" class="rate" style="margin-top:.9rem;padding:.8rem;background:#faf8ef;border-radius:12px"><?= csrfField() ?>
      <input type="hidden" name="act" value="add_rate"><input type="hidden" name="id" value="<?= $rid ?>">
      <div><label>New rate</label><input name="label" required placeholder="e.g. 3 Hours" style="width:140px"></div>
      <div><label>Hours</label><input type="number" name="hours" min="1" required style="width:80px"></div>
      <div><label>Price</label><input type="number" name="price" min="1" step="0.01" required style="width:110px"></div>
      <button class="btn green sm"><?= navIcon('plus', 14) ?>Add rate</button>
    </form>
    <div class="rm-foot"><button type="button" class="btn ghost" data-close>Close</button></div>
  </div>
</div>
<?php modalEnd(); endforeach; ?>

<?= rvAssets() ?>

<style>
  .rm [hidden]{display:none!important}
  .rm-sum{display:flex;align-items:center;gap:.9rem;padding:.9rem 1rem;margin-bottom:1rem;background:#faf8ef;border:1px solid #ece7d6;border-radius:14px}
  .rm-cover{flex:0 0 auto;width:88px;height:62px;border-radius:10px;overflow:hidden;background:#e9e4d0;display:grid;place-items:center;color:#9a947c;font-size:.7rem}
  .rm-cover img{width:100%;height:100%;object-fit:cover;display:block}
  .rm-sum h4{margin:0;font-size:1rem;font-weight:600;color:#0f3d3a}
  .rm-meta{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.45rem}
  .rm-chip{padding:.18rem .6rem;border-radius:999px;background:#e4eee9;color:#123f3a;font-size:.74rem;font-weight:500}
  .rm-tabs{display:flex;gap:.2rem;margin-bottom:1.1rem;border-bottom:1px solid #ece7d6}
  .rm-tabs button{margin-bottom:-1px;padding:.65rem .95rem;border:0;border-bottom:2px solid transparent;background:none;font:inherit;font-size:.86rem;font-weight:500;color:#5f7772;cursor:pointer}
  .rm-tabs button:hover{color:#123f3a}
  .rm-tabs button.on{color:#123f3a;border-bottom-color:#e8583f}
  .rm-fields{display:grid;grid-template-columns:1fr 1fr;gap:.9rem}
  .rm-fields .full{grid-column:1/-1}
  .rm-fields label{display:block;margin-bottom:.3rem;font-size:.78rem;font-weight:500}
  .rm-fields input,.rm-fields textarea{width:100%;box-sizing:border-box}
  .rm-fields textarea{min-height:96px;resize:vertical;padding:.6rem .75rem;font:inherit;font-size:.88rem;border:1px solid #d8d2bd;border-radius:10px}
  .rm-fields textarea:focus{outline:none;border-color:#123f3a;box-shadow:0 0 0 3px rgba(18,63,58,.12)}
  .rm-foot{display:flex;justify-content:flex-end;gap:.6rem;margin-top:1.2rem;padding-top:1rem;border-top:1px solid #ece7d6}

  .pm-top{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;margin-bottom:.8rem}
  .pm-help{margin:.2rem 0 0;font-size:.78rem;color:#5f7772;max-width:46ch}
  .pm-count{flex:0 0 auto;padding:.2rem .65rem;border-radius:999px;background:#f1efe4;color:#5f7772;font-size:.75rem;font-weight:500}
  .pm-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:.7rem;padding:.7rem;border:2px dashed transparent;border-radius:14px;transition:border-color .15s,background .15s}
  .pm-grid.drag{border-color:#e8583f;background:#fff6f3}
  .pm-item{position:relative;aspect-ratio:4/3;overflow:hidden;border:1px solid #e5dfc9;border-radius:12px;background:#f4f1e4}
  .pm-item img{width:100%;height:100%;object-fit:cover;display:block}
  .pm-item.cover{box-shadow:0 0 0 2px #e8583f}
  .pm-x{position:absolute;top:6px;right:6px;display:grid;place-items:center;width:26px;height:26px;padding:0;border:0;border-radius:50%;background:rgba(15,61,58,.85);color:#fff;font-size:17px;line-height:1;cursor:pointer;transition:background .15s,transform .15s}
  .pm-x:hover{background:#d14a32;transform:scale(1.08)}
  .pm-badge,.pm-new{position:absolute;left:6px;padding:.12rem .5rem;border-radius:999px;font-size:.68rem;font-weight:600}
  .pm-badge{bottom:6px;background:#e8583f;color:#fff}
  .pm-new{top:6px;background:#fff;color:#123f3a}
  .pm-make{position:absolute;left:6px;bottom:6px;padding:.15rem .55rem;border:0;border-radius:999px;background:rgba(255,255,255,.92);color:#123f3a;font:inherit;font-size:.68rem;font-weight:600;cursor:pointer;opacity:0;transition:opacity .15s}
  .pm-item:hover .pm-make,.pm-make:focus-visible{opacity:1}
  @media (hover:none){.pm-make{opacity:1}}
  .pm-add{display:grid;place-items:center;align-content:center;gap:.2rem;aspect-ratio:4/3;padding:0;border:2px dashed #c9c2a6;border-radius:12px;background:#faf8ef;color:#5f7772;font:inherit;font-size:.78rem;font-weight:500;cursor:pointer;transition:border-color .15s,color .15s,background .15s}
  .pm-add:hover{border-color:#123f3a;color:#123f3a;background:#f3f1e4}
  .pm-add b{font-size:1.5rem;line-height:1;font-weight:400}
  .pm-msg{min-height:1.2em;margin:.6rem 0 0;font-size:.8rem;color:#b45309}
  @media(max-width:560px){.rm-fields{grid-template-columns:1fr}.rm-sum{align-items:flex-start}}
</style>

<script>
(function () {
  var MAX = <?= (int)ROOM_PHOTO_LIMIT ?>, MAX_DIM = 1600, MAX_BYTES = <?= (int)ROOM_PHOTO_MAX ?>;

  /* ---------- Tabs ---------- */
  document.querySelectorAll('.rm').forEach(function (root) {
    var bar = root.querySelector('.rm-tabs');
    function show(name) {
      bar.querySelectorAll('[data-tab]').forEach(function (t) {
        var on = t.getAttribute('data-tab') === name;
        t.classList.toggle('on', on);
        t.setAttribute('aria-selected', on);
      });
      root.querySelectorAll('[data-pane]').forEach(function (p) { p.hidden = p.getAttribute('data-pane') !== name; });
      var foot = root.querySelector('form [data-foot]');
      if (foot) foot.hidden = (name === 'rates');
    }
    bar.addEventListener('click', function (e) {
      var b = e.target.closest('[data-tab]');
      if (b) show(b.getAttribute('data-tab'));
    });
    // If a required field is empty while another tab is open, jump back to Details.
    root.querySelectorAll('form').forEach(function (f) {
      f.addEventListener('invalid', function () { show('details'); }, true);
    });
    show('details');
  });

  /* ---------- Photo manager ---------- */
  function shrink(file) {   // resize to max 1600px and re-encode as JPEG so uploads stay small
    return new Promise(function (resolve) {
      var url = URL.createObjectURL(file), img = new Image();
      img.onload = function () {
        var s = Math.min(1, MAX_DIM / Math.max(img.width, img.height)), c = document.createElement('canvas');
        c.width = Math.round(img.width * s); c.height = Math.round(img.height * s);
        var x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, c.width, c.height);
        x.drawImage(img, 0, 0, c.width, c.height);
        URL.revokeObjectURL(url);
        c.toBlob(function (b) {
          resolve(b ? new File([b], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : file);
        }, 'image/jpeg', 0.85);
      };
      img.onerror = function () { URL.revokeObjectURL(url); resolve(null); };
      img.src = url;
    });
  }

  document.querySelectorAll('.pm').forEach(function (box) {
    var items = JSON.parse(box.getAttribute('data-items') || '[]').map(function (x) {
      return { existing: true, key: x.key, url: x.url };
    });
    var grid = box.querySelector('.pm-grid'), input = box.querySelector('input[type=file]');
    var order = box.querySelector('input[name=photo_order]'), count = box.querySelector('.pm-count');
    var msg = box.querySelector('.pm-msg'), form = box.closest('form');

    function say(t) { msg.textContent = t || ''; }

    function el(tag, cls, text) {
      var n = document.createElement(tag);
      if (cls) n.className = cls;
      if (text) n.textContent = text;
      return n;
    }

    function sync() {   // keep the real file input and the order list in step with what is on screen
      var newFiles = items.filter(function (i) { return !i.existing; }).map(function (i) { return i.file; });
      var dt = new DataTransfer();
      newFiles.forEach(function (f) { dt.items.add(f); });
      input.files = dt.files;
      order.value = JSON.stringify(items.map(function (i) {
        return i.existing ? 'e:' + i.key : 'n:' + newFiles.indexOf(i.file);
      }));
      render();
    }

    function render() {
      grid.innerHTML = '';
      items.forEach(function (it, idx) {
        var d = el('div', 'pm-item' + (idx === 0 ? ' cover' : ''));
        var img = el('img'); img.src = it.url; img.alt = 'Room photo ' + (idx + 1); img.loading = 'lazy';
        d.appendChild(img);

        var x = el('button', 'pm-x', '\u00d7'); x.type = 'button'; x.setAttribute('aria-label', 'Remove photo');
        x.addEventListener('click', function () {
          if (!it.existing) URL.revokeObjectURL(it.url);
          items.splice(items.indexOf(it), 1);
          say(''); sync();
        });
        d.appendChild(x);

        if (!it.existing) d.appendChild(el('span', 'pm-new', 'New'));
        if (idx === 0) {
          d.appendChild(el('span', 'pm-badge', 'Cover'));
        } else {
          var m = el('button', 'pm-make', 'Make cover'); m.type = 'button';
          m.addEventListener('click', function () {
            items.splice(items.indexOf(it), 1);
            items.unshift(it);
            say(''); sync();
          });
          d.appendChild(m);
        }
        grid.appendChild(d);
      });

      if (items.length < MAX) {
        var add = el('button', 'pm-add'); add.type = 'button';
        add.appendChild(el('b', '', '+'));
        add.appendChild(el('span', '', 'Add photos'));
        add.addEventListener('click', function () { input.click(); });
        grid.appendChild(add);
      }
      count.textContent = items.length + ' / ' + MAX;
    }

    function addFiles(list) {
      var files = Array.prototype.slice.call(list), room = MAX - items.length, problems = [];
      if (files.length > room) { problems.push('Only ' + MAX + ' photos per room.'); files = files.slice(0, Math.max(room, 0)); }
      if (!files.length) { say(problems.join(' ')); return; }

      box.setAttribute('data-busy', '1');
      Promise.all(files.map(function (f) {
        if (!/^image\/(jpeg|png|webp)$/.test(f.type)) { problems.push('Only JPG, PNG or WebP images can be added.'); return null; }
        return shrink(f);
      })).then(function (out) {
        out.forEach(function (f) {
          if (!f) return;
          if (f.size > MAX_BYTES) { problems.push('A photo is still over 2 MB and was skipped.'); return; }
          items.push({ existing: false, file: f, url: URL.createObjectURL(f) });
        });
        box.removeAttribute('data-busy');
        say(problems.filter(function (p, i, a) { return a.indexOf(p) === i; }).join(' '));
        sync();
      });
    }

    input.addEventListener('change', function () { addFiles(input.files); });

    // Drag and drop onto the grid
    ['dragenter', 'dragover'].forEach(function (ev) {
      grid.addEventListener(ev, function (e) { e.preventDefault(); grid.classList.add('drag'); });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
      grid.addEventListener(ev, function (e) { e.preventDefault(); grid.classList.remove('drag'); });
    });
    grid.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files.length) addFiles(e.dataTransfer.files); });

    // Do not submit while photos are still being resized
    if (form) form.addEventListener('submit', function (e) {
      if (box.hasAttribute('data-busy')) { e.preventDefault(); say('Still preparing photos. Try again in a moment.'); }
    });

    sync();
  });
})();
</script>
<?php pageEnd();