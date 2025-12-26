<?php
session_start();
require_once '../config/config.php';

if (!isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit();
}

$workshop_id = $_POST['workshop_id'] ?? null;
$quantity = intval($_POST['quantity'] ?? 1);

if ($quantity < 1) $quantity = 1;
if ($quantity > 10) $quantity = 10;

if ($workshop_id && isset($_SESSION['cart'][$workshop_id])) {
    $_SESSION['cart'][$workshop_id]['quantity'] = $quantity;
}

// Redirect back to products page
header('Location: ../products.php');
exit();
?>