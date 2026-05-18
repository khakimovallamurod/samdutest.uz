<?php
include_once 'ximoya.php';

// qdelete.php - category o'chirish
// Bu funksiya endi quiz.php ichida ishlanadi
// Lekin eski linklar uchun qo'llab-quvvatlaymiz

if (isset($_GET['id'])) {
    $id   = (int)$_GET['id'];
    $stmt = mysqli_prepare($link, "DELETE FROM category WHERE id=?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
}
header("Location: quiz.php?msg=deleted");
exit;
?>