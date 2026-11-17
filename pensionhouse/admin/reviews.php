<?php
require_once __DIR__ . '/../layout.php';
$user = requireRole('admin');

$res = apiAuth('reviews');
$apiFailed = !apiOk($res) || !is_array($res['data']);
$all = $apiFailed ? [] : $res['data'];
usort($all, fn($a, $b) => strcmp($b['createdAt'], $a['createdAt']));

// ---- Summary ----
$total  = count($all);
$avg    = $total ? array_sum(array_column($all, 'rating')) / $total : 0;
$counts = array_fill(1, 5, 0);
foreach ($all as $r) {
    $s = (int)$r['rating'];
    if (isset($counts[$s])) $counts[$s]++;
}
$maxCount = max($counts) ?: 1;

// ---- Filter by stars + paging ----
$stars    = (int)($_GET['stars'] ?? 0);
$filtered = ($stars >= 1 && $stars <= 5)
    ? array_values(array_filter($all, fn($r) => (int)$r['rating'] === $stars))
    : $all;
$perPage = 10;
$pages   = max(1, (int)ceil(count($filtered) / $perPage));
$page    = min(max(1, (int)($_GET['p'] ?? 1)), $pages);
$slice   = array_slice($filtered, ($page - 1) * $perPage, $perPage);

$link = function (int $p, int $s): string {
    $q = [];
    if ($s) $q['stars'] = $s;
    if ($p > 1) $q['p'] = $p;
    return '?' . http_build_query($q);
};

// ---- Average rating per month (last 6 months) as an SVG line chart ----
function trendChart(array $reviews): string
{
    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $months[date('Y-m', strtotime("first day of -$i month"))] = [];
    }
    foreach ($reviews as $r) {
        $k = substr((string)$r['createdAt'], 0, 7);
        if (isset($months[$k])) $months[$k][] = (int)$r['rating'];
    }

    $x0 = 28; $x1 = 288; $yTop = 12; $yBot = 100;
    $n = count($months);
    $step = ($x1 - $x0) / max($n - 1, 1);

    $svg = '<svg class="trend" viewBox="0 0 300 126" role="img" aria-label="Average rating per month">';
    for ($g = 1; $g <= 5; $g++) {
        $y = $yBot - ($g - 1) / 4 * ($yBot - $yTop);
        $svg .= '<line x1="' . $x0 . '" x2="' . $x1 . '" y1="' . round($y, 1) . '" y2="' . round($y, 1) . '" stroke="#e8e8e8"/>'
              . '<text x="' . ($x0 - 7) . '" y="' . round($y + 3, 1) . '" font-size="8" text-anchor="end" fill="#777">' . $g . '</text>';
    }

    $pts = []; $dots = ''; $i = 0; $hasData = false;
    foreach ($months as $key => $list) {
        $x = $x0 + $i * $step;
        $svg .= '<text x="' . round($x, 1) . '" y="118" font-size="8" text-anchor="middle" fill="#777">'
              . date('M', strtotime($key . '-01')) . '</text>';
        if ($list) {
            $hasData = true;
            $a = array_sum($list) / count($list);
            $y = $yBot - ($a - 1) / 4 * ($yBot - $yTop);
            $pts[] = round($x, 1) . ',' . round($y, 1);
            $dots .= '<circle cx="' . round($x, 1) . '" cy="' . round($y, 1) . '" r="3.2" fill="#e8583f"><title>'
                   . date('M Y', strtotime($key . '-01')) . ': ' . number_format($a, 1) . '</title></circle>';
        }
        $i++;
    }
    if (count($pts) > 1) {
        $svg .= '<polyline points="' . implode(' ', $pts) . '" fill="none" stroke="#e8583f" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
    }
    $svg .= $dots;
    if (!$hasData) {
        $svg .= '<text x="150" y="60" font-size="9" text-anchor="middle" fill="#999">No data yet</text>';
    }
    return $svg . '</svg>';
}

$subHtml = $total
    ? '<span class="stars" style="font-size:1rem"><i class="on">★</i></span> '
      . e(number_format($avg, 1)) . ' average · ' . $total . ' review' . ($total === 1 ? '' : 's')
    : 'No reviews yet';

pageStart('Reviews', $user, '', ['subHtml' => $subHtml]);
?>
<style>
  .rv-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1.25rem;max-width:860px;margin-bottom:1.25rem}
  .rv-box{border:1px solid #d4d4d4;border-radius:24px;padding:1rem 1.4rem;box-shadow:0 3px 6px rgba(0,0,0,.18);background:#fff;min-height:150px}
  .rv-box h3{margin:0 0 .7rem;font-size:.75rem;font-weight:500}
  .bd{display:flex;gap:1.2rem;align-items:center}
  .bd-score{text-align:center;min-width:72px}
  .bd-score b{display:block;font-size:2.1rem;line-height:1.1;font-weight:600}
  .bd-score small{display:block;color:var(--soft);font-size:.65rem;margin-top:.25rem}
  .bd-bars{flex:1;min-width:0}
  .bd-row{display:flex;align-items:center;gap:.5rem;text-decoration:none;color:var(--ink);font-size:.7rem;padding:2px 0}
  .bd-row .trk{flex:1;height:7px;border-radius:9px;background:#eee;overflow:hidden}
  .bd-row .trk i{display:block;height:100%;background:#dc2626;border-radius:9px}
  .bd-row em{font-style:normal;width:1.8rem;text-align:right;color:var(--soft)}
  .bd-row.on{font-weight:600}
  .bd-row:hover .trk{background:#e2e2e2}
  .trend{width:100%;height:auto;display:block}
  .filter-note{max-width:860px;margin:0 0 .8rem;font-size:.75rem}
  .filter-note a{color:var(--coral);text-decoration:none;margin-left:.5rem}
  .rv{border:1px solid #d4d4d4;border-radius:30px;padding:.9rem 1.6rem;margin-bottom:.9rem;box-shadow:0 3px 6px rgba(0,0,0,.18);background:#fff;max-width:860px}
  .rv-head{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start}
  .rv-head b{font-weight:500;font-size:.9rem}
  .rv p{margin:.25rem 0 .7rem;font-size:.74rem;color:#555;line-height:1.55}
  .rv-foot{font-size:.72rem;font-weight:600}
  .rv-foot span{font-weight:400;color:var(--soft)}
  .pager{display:flex;gap:1rem;align-items:center;max-width:860px;margin-top:1rem}
</style>

<?php if ($apiFailed): ?>
  <div class="err"><?= e(apiMessage($res, 'Could not load reviews.')) ?></div>
<?php endif; ?>

<div class="rv-grid">
  <div class="rv-box">
    <h3>Rating breakdown</h3>
    <div class="bd">
      <div class="bd-score">
        <b><?= e(number_format($avg, 1)) ?></b>
        <?= stars($avg, '.95rem') ?>
        <small><?= $total ?> review<?= $total === 1 ? '' : 's' ?></small>
      </div>
      <div class="bd-bars">
        <?php for ($s = 5; $s >= 1; $s--): ?>
          <a class="bd-row <?= $stars === $s ? 'on' : '' ?>" href="<?= e($link(1, $stars === $s ? 0 : $s)) ?>"
             title="Show <?= $s ?>-star reviews">
            <span><?= $s ?></span>
            <span class="trk"><i style="width:<?= round($counts[$s] / $maxCount * 100, 1) ?>%"></i></span>
            <em><?= $counts[$s] ?></em>
          </a>
        <?php endfor; ?>
      </div>
    </div>
  </div>

  <div class="rv-box">
    <h3>Average rating trend</h3>
    <?= trendChart($all) ?>
  </div>
</div>

<?php if ($stars): ?>
  <p class="filter-note muted">Showing <?= $stars ?>-star reviews only<a href="<?= e($link(1, 0)) ?>">Clear filter</a></p>
<?php endif; ?>

<?php foreach ($slice as $r): ?>
  <article class="rv">
    <div class="rv-head">
      <b><?= e($r['guestName']) ?></b>
      <?= stars((int)$r['rating'], '.95rem') ?>
    </div>
    <?php if (!empty($r['comment'])): ?>
      <p><?= nl2br(e($r['comment'])) ?></p>
    <?php else: ?>
      <p class="muted">No written review.</p>
    <?php endif; ?>
    <div class="rv-foot">
      <?= e($r['roomTitle']) ?> · <?= e(fmtDay($r['createdAt'])) ?>
      <?php if (!empty($r['updatedAt'])): ?><span>· Edited</span><?php endif; ?>
    </div>
  </article>
<?php endforeach; ?>

<?php if (!$slice && !$apiFailed): ?>
  <p class="muted">No reviews to show yet.</p>
<?php endif; ?>

<?php if ($pages > 1): ?>
  <div class="pager">
    <?php if ($page > 1): ?><a class="btn gray" href="<?= e($link($page - 1, $stars)) ?>">Previous</a><?php endif; ?>
    <span class="muted">Page <?= $page ?> of <?= $pages ?></span>
    <?php if ($page < $pages): ?><a class="btn gray" href="<?= e($link($page + 1, $stars)) ?>">Next</a><?php endif; ?>
  </div>
<?php endif; ?>
<?php
pageEnd();