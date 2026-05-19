<?php

final class Database
{
    private static $connection = null;

    public static function connection()
    {
        if (self::$connection instanceof mysqli) {
            return self::$connection;
        }

        require __DIR__ . '/../../config.php';

        if (!isset($link) || !($link instanceof mysqli)) {
            throw new RuntimeException('Database connection is not available.');
        }

        if (!$link->set_charset('utf8mb4')) {
            throw new RuntimeException('Failed setting utf8mb4 charset.');
        }

        self::$connection = $link;

        return self::$connection;
    }
}
