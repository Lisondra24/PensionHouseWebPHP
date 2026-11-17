<?php
require_once __DIR__ . '/../layout.php';
$user = requireRole('admin');
renderDashboard($user);