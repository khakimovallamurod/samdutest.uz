<?php
    $url = "http://olimp.samdu.uz";
    $host = "localhost";
    $user_db = "root";
    $password = "";
    $db = "samdutestdb";

    // Xatolarni foydalanuvchiga ko'rsatmaslik
  

    $link = mysqli_connect($host, $user_db, $password, $db);
    if (!$link) {
        // Xatoni logga yoz, foydalanuvchiga emas
        error_log("MySQL ulanish xatosi: " . mysqli_connect_error());
        die("Tizimda xatolik yuz berdi. Iltimos keyinroq urinib ko'ring.");
    }
    mysqli_set_charset($link, "utf8");

    /**
     * XSS dan himoya: foydalanuvchi kiritgan ma'lumotni tozalash
     */
    function filter($s) {
        $s = trim($s);
        $s = htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        return $s;
    }

    /**
     * Foydalanuvchi IP manzilini aniqlash
     * Eslatma: HTTP_CLIENT_IP va HTTP_X_FORWARDED_FOR soxtalashtirish mumkin,
     * faqat REMOTE_ADDR ishonchli.
     */
    function get_ip() {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Prepared statement bilan xavfsiz SELECT so'rovini bajarish
     */
    function db_query($link, $sql, $types = '', ...$params) {
        $stmt = mysqli_prepare($link, $sql);
        if (!$stmt) {
            error_log("DB xatosi: " . mysqli_error($link) . " | SQL: " . $sql);
            return false;
        }
        if ($types && $params) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        return mysqli_stmt_get_result($stmt);
    }

    /**
     * Prepared statement bilan xavfsiz INSERT/UPDATE/DELETE bajarish
     */
    function db_execute($link, $sql, $types = '', ...$params) {
        $stmt = mysqli_prepare($link, $sql);
        if (!$stmt) {
            error_log("DB xatosi: " . mysqli_error($link) . " | SQL: " . $sql);
            return false;
        }
        if ($types && $params) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        return mysqli_stmt_execute($stmt);
    }
?>