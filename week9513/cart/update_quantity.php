<?php
session_start();
require_once '../config/config.php';

if (!isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit();
}

$workshop_id = $_POST['workshop_id'] ?? null;
$change = intval($_POST['change'] ?? 0);

if ($workshop_id && isset($_SESSION['cart'][$workshop_id])) {
    $new_quantity = $_SESSION['cart'][$workshop_id]['quantity'] + $change;
    
    if ($new_quantity < 1) {
        $new_quantity = 1;
    }
    if ($new_quantity > 10) {
        $new_quantity = 10;
    }
    
    $_SESSION['cart'][$workshop_id]['quantity'] = $new_quantity;
}

// Redirect back to products page
header('Location: ../products.php');
exit();
?>