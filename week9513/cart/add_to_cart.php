<?php
session_start();
require_once '../config/config.php';

if (!isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $workshop_id = $_POST['workshop_id'] ?? null;
    $workshop_title = $_POST['workshop_title'] ?? '';
    $price = floatval($_POST['price'] ?? 0);
    
    if ($workshop_id && $workshop_title && $price > 0) {
        // Initialize cart if not exists
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        // Check if item already in cart
        if (isset($_SESSION['cart'][$workshop_id])) {
            // Update quantity if already exists
            $_SESSION['cart'][$workshop_id]['quantity'] += 1;
        } else {
            // Add new item to cart
            $_SESSION['cart'][$workshop_id] = [
                'id' => $workshop_id,
                'title' => $workshop_title,
                'price' => $price,
                'quantity' => 1
            ];
        }
        
        // Redirect back to products page with success message
        $_SESSION['success_message'] = 'Workshop added to cart successfully!';
        header('Location: ../products.php');
        exit();
    }
}

// If something went wrong, redirect back
header('Location: ../products.php');
exit();
?>