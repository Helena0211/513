<?php
require_once '../config/config.php';

// Destroy all session data
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to home page
header('Location: ../index1.php');
exit();
?>