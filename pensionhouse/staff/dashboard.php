<?php
require_once __DIR__ . '/../layout.php';
$user = requireRole('staff');
renderDashboard($user);