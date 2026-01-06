<?php
// config/config.php - 修复版

// 基础错误处理
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 会话安全启动
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 基础URL配置
define('BASE_URL', 'https://helena1201.free.nf/513/week9513');

// 数据库配置常量
define('DB_HOST', 'sql210.infinityfree.com');
define('DB_NAME', 'if0_40378146_wp579');
define('DB_USER', 'if0_40378146');
define('DB_PASS', 'nQuyY3nfXVA');
define('SITE_NAME', 'SkillCraft Workshops');

// 基础函数定义
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) || 
               (isset($_SESSION['subscriber_id']) && !empty($_SESSION['subscriber_id']));
    }
}

if (!function_exists('isSubscriber')) {
    function isSubscriber() {
        return isset($_SESSION['subscriber_id']) && !empty($_SESSION['subscriber_id']);
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
    }
}

if (!function_exists('isInstructor')) {
    function isInstructor() {
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'instructor';
    }
}

if (!function_exists('getCurrentUserName')) {
    function getCurrentUserName() {
        if (isset($_SESSION['username']) && !empty($_SESSION['username'])) {
            return $_SESSION['username'];
        } elseif (isset($_SESSION['subscriber_name']) && !empty($_SESSION['subscriber_name'])) {
            return $_SESSION['subscriber_name'];
        }
        return 'Guest';
    }
}

if (!function_exists('formatPrice')) {
    function formatPrice($amount) {
        return '$' . number_format((float)$amount, 2);
    }
}

// 数据库连接
try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// 初始化购物车
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// 管理员会话检查（用于独立的管理员登录）
if (!isset($_SESSION['admin_logged_in'])) {
    $_SESSION['admin_logged_in'] = false;
}
function checkUserSession() {
    // 如果任何会话变量存在，都认为用户已登录
    if (isset($_SESSION['subscriber_id']) || isset($_SESSION['user_id'])) {
        return true;
    }
    return false;
}

function getCurrentUserId() {
    if (isset($_SESSION['subscriber_id'])) {
        return $_SESSION['subscriber_id'];
    }
    if (isset($_SESSION['user_id'])) {
        return $_SESSION['user_id'];
    }
    return null;
}

function getCurrentUserName() {
    if (isset($_SESSION['username'])) {
        return $_SESSION['username'];
    }
    if (isset($_SESSION['first_name'])) {
        $name = $_SESSION['first_name'];
        if (isset($_SESSION['last_name'])) {
            $name .= ' ' . $_SESSION['last_name'];
        }
        return $name;
    }
    return 'User';
}
?>