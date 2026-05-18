<?php
require_once __DIR__ . '/../middleware/auth.php';
require_auth(['student']);
header('Location: /olimpiada.uz/test/student/dashboard.php');
exit;
