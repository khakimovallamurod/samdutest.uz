<?php
// Talaba index sahifasi — quiz.php ga yo'naltirish
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'talaba') {
    header("Location: ../login.php");
    exit;
}
header("Location: quiz.php");
exit;
?>