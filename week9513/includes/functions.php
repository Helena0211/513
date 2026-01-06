<?php
// 会话管理函数
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']) || 
               isset($_SESSION['subscriber_id']) && !empty($_SESSION['subscriber_id']);
    }
}

if (!function_exists('isSubscriber')) {
    function isSubscriber() {
        return isset($_SESSION['subscriber_id']) && !empty($_SESSION['subscriber_id']);
    }
}

if (!function_exists('isInstructor')) {
    function isInstructor() {
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'instructor';
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
    }
}

if (!function_exists('getCurrentUserType')) {
    function getCurrentUserType() {
        if (isset($_SESSION['user_type'])) {
            return $_SESSION['user_type'];
        } elseif (isset($_SESSION['subscriber_id'])) {
            return 'subscriber';
        }
        return 'guest';
    }
}

if (!function_exists('getCurrentUserName')) {
    function getCurrentUserName() {
        if (isset($_SESSION['username'])) {
            return $_SESSION['username'];
        } elseif (isset($_SESSION['subscriber_name'])) {
            return $_SESSION['subscriber_name'];
        }
        return 'Guest';
    }
}

if (!function_exists('getCurrentUserEmail')) {
    function getCurrentUserEmail() {
        if (isset($_SESSION['user_email'])) {
            return $_SESSION['user_email'];
        } elseif (isset($_SESSION['subscriber_email'])) {
            return $_SESSION['subscriber_email'];
        }
        return '';
    }
}

// 购物车相关函数
function getCategories($db) {
    $query = "SELECT * FROM categories ORDER BY category_name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getWorkshopById($db, $id) {
    $query = "SELECT w.*, u.first_name, u.last_name, u.bio as instructor_bio, c.category_name 
              FROM workshops w 
              JOIN users u ON w.instructor_id = u.user_id 
              JOIN categories c ON w.category_id = c.category_id 
              WHERE w.workshop_id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function addToCart($workshop_id, $workshop_title, $price) {
    // 确保购物车会话存在
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    $cart_item = [
        'id' => $workshop_id,
        'title' => $workshop_title,
        'price' => $price,
        'quantity' => 1
    ];
    
    // Check if item already in cart
    $found = false;
    foreach ($_SESSION['cart'] as &$item) {
        if ($item['id'] == $workshop_id) {
            $item['quantity'] += 1;
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        $_SESSION['cart'][] = $cart_item;
    }
}

function removeFromCart($workshop_id) {
    if (!isset($_SESSION['cart'])) {
        return;
    }
    
    foreach ($_SESSION['cart'] as $key => $item) {
        if ($item['id'] == $workshop_id) {
            unset($_SESSION['cart'][$key]);
            $_SESSION['cart'] = array_values($_SESSION['cart']); // Reindex array
            break;
        }
    }
}

function updateCartQuantity($workshop_id, $quantity) {
    if (!isset($_SESSION['cart'])) {
        return;
    }
    
    foreach ($_SESSION['cart'] as &$item) {
        if ($item['id'] == $workshop_id) {
            if ($quantity <= 0) {
                removeFromCart($workshop_id);
            } else {
                $item['quantity'] = $quantity;
            }
            break;
        }
    }
}

function getCartTotal() {
    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        return 0;
    }
    
    $total = 0;
    foreach ($_SESSION['cart'] as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    return $total;
}

function getCartCount() {
    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        return 0;
    }
    
    $count = 0;
    foreach ($_SESSION['cart'] as $item) {
        $count += $item['quantity'];
    }
    return $count;
}

function clearCart() {
    $_SESSION['cart'] = [];
}

// 订阅者相关函数
function getSubscriberById($db, $subscriber_id) {
    $query = "SELECT * FROM wpej_fc_subscribers WHERE subscriber_id = :subscriber_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':subscriber_id', $subscriber_id);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function validateSubscriber($db, $email, $phone) {
    $query = "SELECT subscriber_id, first_name, last_name, email, phone, status 
              FROM wpej_fc_subscribers 
              WHERE email = :email AND phone = :phone AND status = 'subscribed'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':phone', $phone);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function updateSubscriberLastLogin($db, $subscriber_id) {
    $query = "UPDATE wpej_fc_subscribers SET last_login = NOW() WHERE subscriber_id = :subscriber_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':subscriber_id', $subscriber_id);
    return $stmt->execute();
}

// 订单相关函数
function createOrder($db, $user_data, $cart_items, $total_amount) {
    try {
        $db->beginTransaction();
        
        // 插入订单
        $order_query = "INSERT INTO orders (user_id, subscriber_id, total_amount, status, created_at) 
                        VALUES (:user_id, :subscriber_id, :total_amount, 'pending', NOW())";
        $order_stmt = $db->prepare($order_query);
        
        $user_id = isset($user_data['user_id']) ? $user_data['user_id'] : null;
        $subscriber_id = isset($user_data['subscriber_id']) ? $user_data['subscriber_id'] : null;
        
        $order_stmt->bindParam(':user_id', $user_id);
        $order_stmt->bindParam(':subscriber_id', $subscriber_id);
        $order_stmt->bindParam(':total_amount', $total_amount);
        $order_stmt->execute();
        
        $order_id = $db->lastInsertId();
        
        // 插入订单项
        $item_query = "INSERT INTO order_items (order_id, workshop_id, workshop_title, price, quantity) 
                       VALUES (:order_id, :workshop_id, :workshop_title, :price, :quantity)";
        $item_stmt = $db->prepare($item_query);
        
        foreach ($cart_items as $item) {
            $item_stmt->bindParam(':order_id', $order_id);
            $item_stmt->bindParam(':workshop_id', $item['id']);
            $item_stmt->bindParam(':workshop_title', $item['title']);
            $item_stmt->bindParam(':price', $item['price']);
            $item_stmt->bindParam(':quantity', $item['quantity']);
            $item_stmt->execute();
        }
        
        $db->commit();
        return $order_id;
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

// 移除重复的工具函数
// 注意：formatPrice() 和 redirect() 函数已经在 config/config.php 中定义了
// displayMessage() 函数可能也存在重复定义，如果 config.php 中已经有，也需要移除

function displayMessage($message, $type = 'info') {
    $class = '';
    switch ($type) {
        case 'success':
            $class = 'alert alert-success';
            break;
        case 'error':
            $class = 'alert alert-danger';
            break;
        case 'warning':
            $class = 'alert alert-warning';
            break;
        default:
            $class = 'alert alert-info';
    }
    
    return "<div class='$class'>$message</div>";
}
?>