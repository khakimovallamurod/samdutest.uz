<?php
require_once __DIR__ . '/helpers.php';

logout_user();
start_secure_session();
flash_set('success', 'Tizimdan muvaffaqiyatli chiqdingiz.');
redirect('/test/login.php');
