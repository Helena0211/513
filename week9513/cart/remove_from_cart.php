<?php
session_start();
require_once '../config/config.php';

if (!isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit();
}

$workshop_id = $_POST['workshop_id'] ?? null;

if ($workshop_id && isset($_SESSION['cart'][$workshop_id])) {
    // Remove the specific workshop from cart
    unset($_SESSION['cart'][$workshop_id]);
    
    // If cart becomes empty after removal, remove the entire cart session
    if (empty($_SESSION['cart'])) {
        unset($_SESSION['cart']);
    }
    
    // Set success message
    $_SESSION['success_message'] = 'Workshop removed from cart successfully!';
}

// Redirect back to products page
header('Location: ../products.php');
exit();
?>