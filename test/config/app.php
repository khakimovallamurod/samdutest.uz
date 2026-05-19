<?php
return [
    'session_name' => 'samdu_test_session',
    'session_ttl' => 7200,
    'remember_me_days' => 30,
    'default_redirect' => '/test/login.php',
    'role_redirects' => [
        'admin' => '/test/admin/dashboard.php',
        'teacher' => '/test/teacher/dashboard.php',
        'student' => '/test/student/dashboard.php',
        'user' => '/test/student/dashboard.php',
    ],
];
