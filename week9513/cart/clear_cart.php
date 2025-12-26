<?php
session_start();
require_once '../config/config.php';

if (!isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit();
}

// Clear the cart
$_SESSION['cart'] = [];

// Redirect back to products page
header('Location: ../products.php');
exit();
?>