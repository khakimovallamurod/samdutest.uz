<?php
require_once __DIR__ . '/../middleware/auth.php';
require_auth(['teacher']);
redirect('/test/teacher/tests/index.php');
