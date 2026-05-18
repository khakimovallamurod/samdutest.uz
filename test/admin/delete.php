<?php
include_once 'ximoya.php';

// Savolni o'chirish - SQL injection himoyasi
if (isset($_GET['q_id'])) {
    $id      = (int)$_GET['q_id'];
    $id_hash = '';

    // id_hash faqat harflar va raqamlardan iborat bo'lishi kerak
    if (isset($_GET['id_hash']) && preg_match('/^[a-z0-9]+$/i', $_GET['id_hash'])) {
        $id_hash = $_GET['id_hash'];
    } else {
        header("Location: question_add.php");
        exit;
    }

    $stmt = mysqli_prepare($link, "DELETE FROM savollar WHERE id=?");
    mysqli_stmt_bind_param($stmt, "i", $id);

    if (mysqli_stmt_execute($stmt)) {
        header("Location: question_add.php?id_hash=" . urlencode($id_hash) . "&msg=deleted");
    } else {
        header("Location: question_add.php?id_hash=" . urlencode($id_hash) . "&msg=error");
    }
    exit;
}
?>
