<?php
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../shared/dashboard_repository.php';
require_once __DIR__ . '/../layouts/role_dashboard.php';
require_once __DIR__ . '/../database/Database.php';

require_auth(['student']);

function student_menu(): array
{
    return [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'href' => '/test/student/dashboard.php'],
        ['key' => 'tests', 'label' => 'Testlar', 'icon' => 'tests', 'href' => '/test/student/tests.php'],
        ['key' => 'my-results', 'label' => 'Mening natijalarim', 'icon' => 'results', 'href' => '/test/student/my-results.php'],
        ['key' => 'profile', 'label' => 'Profil', 'icon' => 'profile', 'href' => '/test/student/profile.php'],
        ['key' => 'settings', 'label' => 'Sozlamalar', 'icon' => 'settings', 'href' => '/test/student/settings.php'],
    ];
}
