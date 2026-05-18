<?php
return [
    'session_name' => 'samdu_test_session',
    'session_ttl' => 7200,
    'remember_me_days' => 30,
    'default_redirect' => '/olimpiada.uz/test/login.php',
    'role_redirects' => [
        'admin' => '/olimpiada.uz/test/admin/dashboard.php',
        'teacher' => '/olimpiada.uz/test/teacher/dashboard.php',
        'student' => '/olimpiada.uz/test/student/dashboard.php',
        'user' => '/olimpiada.uz/test/student/dashboard.php',
    ],
];
