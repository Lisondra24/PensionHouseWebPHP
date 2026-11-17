<?php
/**
 * Shared by admin/rooms.php and staff/rooms.php:
 * the Tiles / Table view switch, the table itself, and its CSS + JavaScript.
 */

/** First photo of a room (cover), or null. */
function rvThumb(array $r): ?string
{
    $p = array_values(array_filter($r['photos'] ?? [], 'is_string'));
    if ($p) return $p[0];
    return !empty($r['imageUrl']) ? (string)$r['imageUrl'] : null;
}

function rvIcon(string $name, int $size = 15): string
{
    $paths = [
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/>',
        'list' => '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>',
    ];
    return '<svg class="ico" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . ($paths[$name] ?? '') . '</svg>';
}

/** The Tiles / Table switch. */
function rvToggleHtml(): string
{
    return '<div class="vsw" role="group" aria-label="Room view">'
         . '<button type="button" data-view="tiles" class="on" aria-pressed="true" title="Show rooms as tiles">' . rvIcon('grid') . 'Tiles</button>'
         . '<button type="button" data-view="table" aria-pressed="false" title="Show rooms as a table">' . rvIcon('list') . 'Table</button>'
         . '</div>';
}

/**
 * The table view.
 * $items = list of ['room' => <room array from the API>, 'actions' => <button/form HTML, same as the tile>]
 */
function rvTableHtml(array $items): string
{
    if (!$items) return '';

    $pill = ['AVAILABLE' => 'p-ok', 'OCCUPIED' => 'p-info', 'MAINTENANCE' => 'p-warn', 'DISABLED' => 'p-off'];
    $th = fn(string $key, string $label) =>
        '<th><button type="button" class="vsort" data-sort="' . e($key) . '">' . e($label) . '<span class="vs-a" aria-hidden="true"></span></button></th>';

    $out = '<div class="vtable" id="vTable" hidden><div class="scroll"><table>'
         . '<colgroup>'
         . '<col class="c-room"><col class="c-type"><col class="c-sleeps"><col class="c-status">'
         . '<col class="c-price"><col class="c-rates"><col class="c-acts">'
         . '</colgroup>'
         . '<thead><tr>' . $th('room', 'Room') . $th('type', 'Type') . $th('sleeps', 'Sleeps') . $th('status', 'Status') . $th('price', 'From')
         . '<th class="vh">Rates</th><th class="vh tright">Actions</th></tr></thead><tbody>';

    foreach ($items as $it) {
        $r     = $it['room'];
        $st    = roomState($r);
        $no    = (string)($r['roomNo'] ?? '');
        $title = (string)($r['title'] ?? '');
        $rates = array_values(array_filter($r['rates'] ?? [], fn($x) => !empty($x['isActive'])));
        $min   = $rates ? min(array_map(fn($x) => (float)($x['price'] ?? 0), $rates)) : null;
        $thumb = rvThumb($r);
        $hay   = mb_strtolower('room ' . $no . ' ' . $title . ' ' . $st);

        $chips = '';
        foreach (array_slice($rates, 0, 2) as $x) {
            $chips .= '<span>' . e($x['label'] ?? '') . ' · ' . e(peso($x['price'] ?? 0)) . '</span>';
        }
        if (count($rates) > 2) $chips .= '<span class="more">+' . (count($rates) - 2) . ' more</span>';
        $ratesHtml = $chips !== '' ? '<div class="rrates">' . $chips . '</div>' : '<span class="muted">No rates yet</span>';

        $out .= '<tr class="' . ($st === 'DISABLED' ? 'dim' : '') . '" data-text="' . e($hay) . '" data-status="' . e($st) . '"'
              . ' data-k-room="' . e($no) . '" data-k-type="' . e($title) . '" data-k-sleeps="' . (int)($r['capacity'] ?? 0) . '"'
              . ' data-k-status="' . e($st) . '" data-k-price="' . ($min === null ? '999999999' : number_format($min, 2, '.', '')) . '">'
              . '<td><span class="vroom"><span class="vth">' . ($thumb ? '<img src="' . e($thumb) . '" alt="" loading="lazy">' : e($no)) . '</span><b>Room ' . e($no) . '</b></span></td>'
              . '<td class="ell" title="' . e($title) . '">' . e($title) . '</td>'
              . '<td>' . (int)($r['capacity'] ?? 0) . '</td>'
              . '<td><span class="pill ' . ($pill[$st] ?? 'p-off') . '">' . e(ucfirst(strtolower($st))) . '</span></td>'
              . '<td><b>' . ($min === null ? '-' : e(peso($min))) . '</b></td>'
              . '<td>' . $ratesHtml . '</td>'
              . '<td><div class="vacts">' . ($it['actions'] !== '' ? $it['actions'] : '<span class="muted">No actions</span>') . '</div></td>'
              . '</tr>';
    }

    return $out . '</tbody></table></div></div>';
}

/** CSS + JavaScript for the switch, the table and column sorting. Print once, after the room list. */
function rvAssets(): string
{
    return <<<'HTML'
<style>
  .tools{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}
  .vsw{display:inline-flex;gap:2px;padding:3px;border:1px solid var(--line);border-radius:999px;background:#fff}
  .vsw button{display:inline-flex;align-items:center;gap:.4rem;border:0;background:none;padding:.3rem .85rem;border-radius:999px;font:inherit;font-size:.72rem;font-weight:500;color:var(--soft);cursor:pointer;transition:background .15s,color .15s}
  .vsw button:hover{color:var(--g)}
  .vsw button.on{background:var(--g);color:#fff}
  .vsw button:focus-visible{outline:2px solid var(--coral);outline-offset:2px}

  .vtable{background:#fff;border:1px solid var(--line);border-radius:16px;overflow:hidden;box-shadow:0 4px 14px rgba(15,61,58,.07)}
  .vtable .scroll{overflow-x:auto}
  .vtable table{width:100%;min-width:1180px;table-layout:fixed;border:0;border-radius:0;border-collapse:collapse;font-size:.8rem}

  /* fixed column widths = everything lines up */
  .vtable .c-room{width:190px}
  .vtable .c-type{width:150px}
  .vtable .c-sleeps{width:80px}
  .vtable .c-status{width:140px}
  .vtable .c-price{width:110px}
  .vtable .c-rates{width:auto}
  .vtable .c-acts{width:372px}

  /* header */
  .vtable th{padding:0;text-align:left;vertical-align:middle}
  .vtable th.vh{padding:.65rem .85rem}
  .vtable th.tright{text-align:right;padding-right:1.1rem}
  .vsort{display:flex;align-items:center;gap:.35rem;width:100%;padding:.65rem .85rem;border:0;background:none;font:inherit;letter-spacing:inherit;text-transform:inherit;color:inherit;text-align:left;cursor:pointer}
  .vsort:hover{color:var(--g);background:#efebd9}
  .vsort:focus-visible{outline:2px solid var(--coral);outline-offset:-2px}
  .vs-a{font-size:.55rem;color:var(--coral)}
  th[aria-sort=ascending] .vs-a::after{content:"\25B2"}
  th[aria-sort=descending] .vs-a::after{content:"\25BC"}

  /* body: every row same height, everything vertically centered */
  .vtable td{height:68px;padding:.6rem .85rem;vertical-align:middle;white-space:nowrap;overflow:hidden}
  .vtable td:last-child{padding-right:1.1rem;overflow:visible}
  .vtable td.ell{overflow:hidden;text-overflow:ellipsis}
  .vtable tbody tr{border-top:1px solid var(--line)}
  .vtable tbody tr:first-child{border-top:0}
  .vtable tbody tr:hover{background:#fbfaf3}
  .vtable tr.dim td:not(:last-child){opacity:.55}

  .vroom{display:flex;align-items:center;gap:.7rem}
  .vroom b{font-weight:600}
  .vth{width:52px;height:38px;border-radius:8px;overflow:hidden;flex-shrink:0;display:grid;place-items:center;background:linear-gradient(160deg,#1e6b62,#0d2f2b);color:#fff;font-size:.9rem;font-weight:600}
  .vth img{width:100%;height:100%;object-fit:cover;display:block}

  /* rates: one line */
  .vtable .rrates{display:flex;flex-wrap:nowrap;gap:.35rem;overflow:hidden}
  .vtable .rrates span{flex:0 0 auto;font-size:.68rem;white-space:nowrap}

  /* actions: one line, Manage always left, Disable/Enable always right */
  .vacts{display:flex;flex-wrap:nowrap;align-items:center;gap:.4rem;width:100%}
  .vacts form{display:contents}
  .vacts .btn{display:inline-flex;align-items:center;justify-content:center;gap:.35rem;width:118px;white-space:nowrap}
  .vacts > .btn:first-child{margin-right:auto}
  .vacts > form:last-child .btn{margin-left:auto}
  .vacts > form:only-child .btn{margin-left:auto}
</style>
<script>
(function () {
  var KEY = 'ph_rooms_view';
  var tiles = document.querySelector('#rg .rgrid');
  var table = document.getElementById('vTable');
  var sw = document.querySelector('.vsw');
  if (!tiles || !table || !sw) return;

  // Give each table row the same search text and status as its tile, so search and chips behave the same in both views.
  var cards = tiles.querySelectorAll('[data-text]');
  var rows = table.querySelectorAll('tbody tr');
  if (cards.length === rows.length) {
    rows.forEach(function (r, i) {
      var c = cards[i];
      if (c.hasAttribute('data-text'))   r.setAttribute('data-text', c.getAttribute('data-text'));
      if (c.hasAttribute('data-status')) r.setAttribute('data-status', c.getAttribute('data-status'));
    });
  }

  function setView(v, save) {
    var t = v === 'table';
    tiles.hidden = t;
    table.hidden = !t;
    sw.querySelectorAll('[data-view]').forEach(function (b) {
      var on = b.getAttribute('data-view') === v;
      b.classList.toggle('on', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    if (save) { try { localStorage.setItem(KEY, v); } catch (e) {} }
  }
  sw.addEventListener('click', function (e) {
    var b = e.target.closest('[data-view]');
    if (b) setView(b.getAttribute('data-view'), true);
  });

  // Column sorting: click = ascending, click again = descending, third click = back to the original order
  var tbody = table.querySelector('tbody');
  var orig = Array.prototype.slice.call(tbody.rows);
  var cur = null, dir = 1;
  table.querySelectorAll('.vsort').forEach(function (b) {
    b.addEventListener('click', function () {
      var k = b.getAttribute('data-sort');
      if (cur === k) { if (dir === 1) dir = -1; else cur = null; }
      else { cur = k; dir = 1; }

      table.querySelectorAll('th').forEach(function (x) { x.removeAttribute('aria-sort'); });
      var list = orig;
      if (cur) {
        b.parentNode.setAttribute('aria-sort', dir === 1 ? 'ascending' : 'descending');
        list = orig.slice().sort(function (a, c) {
          return dir * a.getAttribute('data-k-' + cur).localeCompare(c.getAttribute('data-k-' + cur), undefined, { numeric: true, sensitivity: 'base' });
        });
      }
      list.forEach(function (r) { tbody.appendChild(r); });
    });
  });

  var saved = null;
  try { saved = localStorage.getItem(KEY); } catch (e) {}
  setView(saved === 'table' ? 'table' : 'tiles', false);
})();
</script>
HTML;
}