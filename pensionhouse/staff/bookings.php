<?php
require_once __DIR__ . '/../views.php';
renderBookings(requireRole('staff'));