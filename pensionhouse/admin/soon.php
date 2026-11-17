<?php
require_once __DIR__ . '/layout.php';
$user = requireStaffOrAdmin();

$page = ucfirst(preg_replace('/[^a-z]/', '', strtolower($_GET['page'] ?? 'page')));

pageStart($page, $user, 'This section is not available yet.');
?>
<div class="panel" style="max-width:520px">
  <?= emptyBlock('clock', 'Coming soon', $page . ' is still being built. Check back later.') ?>
  <p style="text-align:center;margin:0 0 1rem">
    <a class="btn" href="<?= e(dashboardFor($user['role'])) ?>">Back to Overview</a>
  </p>
</div>
<?php pageEnd();