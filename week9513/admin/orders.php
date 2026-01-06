<?php
session_start();
require_once '../config/config.php';

// Admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Get current filename for active status
$current_page = basename($_SERVER['PHP_SELF']);

// Use database connection from config.php
global $db;

// Get current action
$action = $_GET['action'] ?? 'list';
$order_id = $_GET['id'] ?? null;
$success_msg = '';
$error_msg = '';

try {
    // Handle delete order action
    if ($action == 'delete' && $order_id) {
        $stmt = $db->prepare("DELETE FROM wpej_order WHERE id = ?");
        $stmt->execute([$order_id]);
        $success_msg = 'Order deleted successfully';
        header('Location: orders.php?success=' . urlencode($success_msg));
        exit();
    }
    
    // Handle update order status
    if ($action == 'update_status' && $order_id && isset($_GET['status'])) {
        $status = $_GET['status'];
        $valid_statuses = ['placed', 'processing', 'shipped', 'delivered', 'cancelled'];
        
        if (in_array($status, $valid_statuses)) {
            $stmt = $db->prepare("UPDATE wpej_order SET status = ? WHERE id = ?");
            $stmt->execute([$status, $order_id]);
            $success_msg = 'Order status updated successfully';
            header('Location: orders.php?success=' . urlencode($success_msg));
            exit();
        }
    }
    
    // Get specific order for view/edit
    $current_order = null;
    if (($action == 'view' || $action == 'edit') && $order_id) {
        $stmt = $db->prepare("SELECT * FROM wpej_order WHERE id = ?");
        $stmt->execute([$order_id]);
        $current_order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($current_order) {
            $current_order['items'] = json_decode($current_order['items_json'], true);
        }
    }
    
    // Handle edit form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order'])) {
        $customer_name = trim($_POST['customer_name'] ?? '');
        $customer_email = trim($_POST['customer_email'] ?? '');
        $customer_phone = trim($_POST['customer_phone'] ?? '');
        $shipping_address = trim($_POST['shipping_address'] ?? '');
        $payment_method = trim($_POST['payment_method'] ?? '');
        $additional_notes = trim($_POST['additional_notes'] ?? '');
        $subtotal = floatval($_POST['subtotal'] ?? 0);
        $shipping_fee = floatval($_POST['shipping_fee'] ?? 0);
        $tax_amount = floatval($_POST['tax_amount'] ?? 0);
        $total_amount = floatval($_POST['total_amount'] ?? 0);
        $status = trim($_POST['status'] ?? 'placed');
        
        if (!empty($customer_name) && !empty($customer_email) && $order_id) {
            $stmt = $db->prepare("
                UPDATE wpej_order 
                SET customer_name = ?, customer_email = ?, customer_phone = ?, 
                    shipping_address = ?, payment_method = ?, additional_notes = ?,
                    subtotal = ?, shipping_fee = ?, tax_amount = ?, total_amount = ?,
                    status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $customer_name, $customer_email, $customer_phone,
                $shipping_address, $payment_method, $additional_notes,
                $subtotal, $shipping_fee, $tax_amount, $total_amount,
                $status, $order_id
            ]);
            
            $success_msg = 'Order updated successfully';
            header('Location: orders.php?success=' . urlencode($success_msg));
            exit();
        } else {
            $error_msg = 'Please fill in all required fields';
        }
    }
    
    // Build query to get orders
    $search = $_GET['search'] ?? '';
    $status_filter = $_GET['status_filter'] ?? '';
    $order_date = $_GET['order_date'] ?? '';
    
    $query = "SELECT * FROM wpej_order WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (order_number LIKE ? OR customer_name LIKE ? OR customer_email LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if (!empty($status_filter)) {
        $query .= " AND status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($order_date)) {
        $query .= " AND DATE(order_date) = ?";
        $params[] = $order_date;
    }
    
    $query .= " ORDER BY id DESC";
    
    // Pagination
    $perPage = 20;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($page - 1) * $perPage;
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM wpej_order WHERE 1=1";
    $countParams = [];
    
    if (!empty($search)) {
        $countQuery .= " AND (order_number LIKE ? OR customer_name LIKE ? OR customer_email LIKE ?)";
        $countParams[] = "%$search%";
        $countParams[] = "%$search%";
        $countParams[] = "%$search%";
    }
    
    if (!empty($status_filter)) {
        $countQuery .= " AND status = ?";
        $countParams[] = $status_filter;
    }
    
    if (!empty($order_date)) {
        $countQuery .= " AND DATE(order_date) = ?";
        $countParams[] = $order_date;
    }
    
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($countParams);
    $totalOrders = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalOrders / $perPage);
    
    // Get data
    $query .= " LIMIT $perPage OFFSET $offset";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $statsStmt = $db->query("
        SELECT 
            COUNT(*) as total_orders,
            SUM(total_amount) as total_revenue,
            AVG(total_amount) as avg_order_value,
            COUNT(DISTINCT customer_email) as unique_customers
        FROM wpej_order
        WHERE status != 'cancelled'
    ");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_msg = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - SkillCraft Admin</title>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
               <aside class="admin-sidebar">
            <nav>
                <ul class="admin-nav">
                    <li class="admin-nav-item">
                        <a href="index.php" class="admin-nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
                            Dashboard
                        </a>
                    </li>
                    <li class="admin-nav-item">
                        <a href="products.php" class="admin-nav-link <?php echo ($current_page == 'products.php') ? 'active' : ''; ?>">
                            Workshops
                        </a>
                    </li>
                    <li class="admin-nav-item">
                        <a href="categories.php" class="admin-nav-link <?php echo ($current_page == 'categories.php') ? 'active' : ''; ?>">
                            Categories
                        </a>
                    </li>
                    <li class="admin-nav-item">
                        <a href="orders.php" class="admin-nav-link <?php echo ($current_page == 'orders.php') ? 'active' : ''; ?>">
                            Orders
                        </a>
                    </li>
                    <li class="admin-nav-item">
                        <a href="feedback.php" class="admin-nav-link <?php echo ($current_page == 'feedback.php') ? 'active' : ''; ?>">
                          Feedback
                        </a>
                    </li>
                    <li class="admin-nav-item">
                        <a href="recruitment.php" class="admin-nav-link <?php echo ($current_page == 'recruitment.php') ? 'active' : ''; ?>">
                         Applications
                        </a>
                    </li>
                    <li class="admin-nav-item">
                        <a href="forum.php" class="admin-nav-link <?php echo ($current_page == 'forum.php') ? 'active' : ''; ?>">
                            Forum Posts
                        </a>
                    </li>
                    <li class="admin-nav-item">
                        <a href="subscribers.php" class="admin-nav-link <?php echo ($current_page == 'subscribers.php') ? 'active' : ''; ?>">
                            Subscribers
                        </a>
                    </li>
                    <li class="admin-nav-item">
                        <a href="../auth/logout.php" class="admin-nav-link">
                            Logout
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main content area -->
        <main class="admin-main">
            <header class="admin-header">
                <h1>
                    <?php echo $action == 'view' ? 'View Order' : ($action == 'edit' ? 'Edit Order' : 'Manage Orders'); ?>
                </h1>
                <p>Manage customer orders and order processing</p>
            </header>

            <div class="admin-content">
                <?php if (isset($_GET['success'])): ?>
                <div style="background: #d1f7c4; color: #0e5b27; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #b1e19a;">
                    <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($error_msg)): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
                    <?php echo htmlspecialchars($error_msg); ?>
                </div>
                <?php endif; ?>

                <?php if ($action == 'view' && $current_order): ?>
                <!-- View Order Details -->
                <div class="admin-card">
                    <div class="admin-card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h2 class="admin-card-title" style="margin: 0;">
                            Order: <?php echo htmlspecialchars($current_order['order_number']); ?>
                        </h2>
                        <div style="display: flex; gap: 10px;">
                            <a href="orders.php?action=edit&id=<?php echo $current_order['id']; ?>" class="btn-admin btn-admin-primary">
                                Edit Order
                            </a>
                            <a href="orders.php" class="btn-admin btn-admin-outline">
                                Back to Orders
                            </a>
                        </div>
                    </div>
                    
                    <div class="admin-card-body">
                        <!-- Order Information -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">
                            <div class="order-detail-section">
                                <h4>Order Information</h4>
                                <div style="display: grid; grid-template-columns: 120px 1fr; gap: 10px;">
                                    <div style="font-weight: bold;">Order Number:</div>
                                    <div><?php echo htmlspecialchars($current_order['order_number']); ?></div>
                                    
                                    <div style="font-weight: bold;">Order Date:</div>
                                    <div><?php echo date('F d, Y H:i:s', strtotime($current_order['order_date'])); ?></div>
                                    
                                    <div style="font-weight: bold;">Status:</div>
                                    <div>
                                        <span class="status-badge status-<?php echo htmlspecialchars($current_order['status']); ?>">
                                            <?php echo ucfirst(htmlspecialchars($current_order['status'])); ?>
                                        </span>
                                    </div>
                                    
                                    <div style="font-weight: bold;">Payment Method:</div>
                                    <div><?php echo htmlspecialchars($current_order['payment_method']); ?></div>
                                </div>
                            </div>
                            
                            <div class="order-detail-section">
                                <h4>Customer Information</h4>
                                <div style="display: grid; grid-template-columns: 120px 1fr; gap: 10px;">
                                    <div style="font-weight: bold;">Name:</div>
                                    <div><?php echo htmlspecialchars($current_order['customer_name']); ?></div>
                                    
                                    <div style="font-weight: bold;">Email:</div>
                                    <div><a href="mailto:<?php echo htmlspecialchars($current_order['customer_email']); ?>">
                                        <?php echo htmlspecialchars($current_order['customer_email']); ?>
                                    </a></div>
                                    
                                    <div style="font-weight: bold;">Phone:</div>
                                    <div><?php echo htmlspecialchars($current_order['customer_phone']); ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Shipping Address -->
                        <div class="order-detail-section">
                            <h4>Shipping Address</h4>
                            <div style="white-space: pre-wrap; background: white; padding: 15px; border-radius: 4px; border: 1px solid #dee2e6;">
                                <?php echo htmlspecialchars($current_order['shipping_address']); ?>
                            </div>
                        </div>
                        
                        <!-- Order Items -->
                        <div class="order-detail-section">
                            <h4>Order Items</h4>
                            <?php if (!empty($current_order['items']) && is_array($current_order['items'])): ?>
                            <table class="order-items-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Price</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($current_order['items'] as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['title'] ?? 'Unknown Product'); ?></td>
                                        <td><?php echo $item['quantity'] ?? 1; ?></td>
                                        <td>$<?php echo number_format($item['price'] ?? 0, 2); ?></td>
                                        <td>$<?php echo number_format(($item['price'] ?? 0) * ($item['quantity'] ?? 1), 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <div style="text-align: center; padding: 20px; color: #666;">
                                No items found in this order
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Price Summary -->
                        <div class="order-detail-section">
                            <h4>Price Summary</h4>
                            <div class="price-summary">
                                <div class="summary-row">
                                    <span>Subtotal:</span>
                                    <span>$<?php echo number_format($current_order['subtotal'], 2); ?></span>
                                </div>
                                <div class="summary-row">
                                    <span>Shipping Fee:</span>
                                    <span>$<?php echo number_format($current_order['shipping_fee'], 2); ?></span>
                                </div>
                                <div class="summary-row">
                                    <span>Tax Amount:</span>
                                    <span>$<?php echo number_format($current_order['tax_amount'], 2); ?></span>
                                </div>
                                <div class="total-row">
                                    <span>Total Amount:</span>
                                    <span>$<?php echo number_format($current_order['total_amount'], 2); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Additional Notes -->
                        <?php if (!empty($current_order['additional_notes'])): ?>
                        <div class="order-detail-section">
                            <h4>Additional Notes</h4>
                            <div style="white-space: pre-wrap; background: white; padding: 15px; border-radius: 4px; border: 1px solid #dee2e6;">
                                <?php echo htmlspecialchars($current_order['additional_notes']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php elseif ($action == 'edit' && $current_order): ?>
                <!-- Edit Order Form -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h2 class="admin-card-title">Edit Order: <?php echo htmlspecialchars($current_order['order_number']); ?></h2>
                    </div>
                    <div class="admin-card-body">
                        <form method="post" class="admin-form">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div>
                                    <div class="form-group">
                                        <label for="order_number">Order Number</label>
                                        <input type="text" id="order_number" class="form-control" 
                                               value="<?php echo htmlspecialchars($current_order['order_number']); ?>" disabled>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="customer_name">Customer Name *</label>
                                        <input type="text" id="customer_name" name="customer_name" class="form-control" required
                                               value="<?php echo htmlspecialchars($current_order['customer_name']); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="customer_email">Customer Email *</label>
                                        <input type="email" id="customer_email" name="customer_email" class="form-control" required
                                               value="<?php echo htmlspecialchars($current_order['customer_email']); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="customer_phone">Customer Phone</label>
                                        <input type="text" id="customer_phone" name="customer_phone" class="form-control"
                                               value="<?php echo htmlspecialchars($current_order['customer_phone']); ?>">
                                    </div>
                                </div>
                                
                                <div>
                                    <div class="form-group">
                                        <label for="order_date">Order Date</label>
                                        <input type="datetime-local" id="order_date" class="form-control" 
                                               value="<?php echo date('Y-m-d\TH:i', strtotime($current_order['order_date'])); ?>" disabled>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="status">Order Status</label>
                                        <select id="status" name="status" class="form-control">
                                            <option value="placed" <?php echo $current_order['status'] == 'placed' ? 'selected' : ''; ?>>Placed</option>
                                            <option value="processing" <?php echo $current_order['status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                            <option value="shipped" <?php echo $current_order['status'] == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                            <option value="delivered" <?php echo $current_order['status'] == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                            <option value="cancelled" <?php echo $current_order['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="payment_method">Payment Method</label>
                                        <select id="payment_method" name="payment_method" class="form-control">
                                            <option value="credit_card" <?php echo $current_order['payment_method'] == 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                                            <option value="paypal" <?php echo $current_order['payment_method'] == 'paypal' ? 'selected' : ''; ?>>PayPal</option>
                                            <option value="bank_transfer" <?php echo $current_order['payment_method'] == 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                            <option value="cash" <?php echo $current_order['payment_method'] == 'cash' ? 'selected' : ''; ?>>Cash</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="shipping_address">Shipping Address *</label>
                                <textarea id="shipping_address" name="shipping_address" class="form-control" rows="4" required><?php echo htmlspecialchars($current_order['shipping_address']); ?></textarea>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div>
                                    <div class="form-group">
                                        <label for="subtotal">Subtotal ($)</label>
                                        <input type="number" id="subtotal" name="subtotal" class="form-control" step="0.01" required
                                               value="<?php echo $current_order['subtotal']; ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="shipping_fee">Shipping Fee ($)</label>
                                        <input type="number" id="shipping_fee" name="shipping_fee" class="form-control" step="0.01" required
                                               value="<?php echo $current_order['shipping_fee']; ?>">
                                    </div>
                                </div>
                                
                                <div>
                                    <div class="form-group">
                                        <label for="tax_amount">Tax Amount ($)</label>
                                        <input type="number" id="tax_amount" name="tax_amount" class="form-control" step="0.01" required
                                               value="<?php echo $current_order['tax_amount']; ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="total_amount">Total Amount ($)</label>
                                        <input type="number" id="total_amount" name="total_amount" class="form-control" step="0.01" required
                                               value="<?php echo $current_order['total_amount']; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="additional_notes">Additional Notes</label>
                                <textarea id="additional_notes" name="additional_notes" class="form-control" rows="3"><?php echo htmlspecialchars($current_order['additional_notes']); ?></textarea>
                            </div>
                            
                            <!-- Order Items (Read Only) -->
                            <div class="form-group">
                                <label>Order Items (Read Only)</label>
                                <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; border: 1px solid #dee2e6; margin-top: 10px;">
                                    <?php if (!empty($current_order['items']) && is_array($current_order['items'])): ?>
                                        <table style="width: 100%; border-collapse: collapse;">
                                            <thead>
                                                <tr style="border-bottom: 2px solid #dee2e6;">
                                                    <th style="padding: 8px; text-align: left;">Product</th>
                                                    <th style="padding: 8px; text-align: left;">Quantity</th>
                                                    <th style="padding: 8px; text-align: left;">Price</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($current_order['items'] as $item): ?>
                                                <tr style="border-bottom: 1px solid #dee2e6;">
                                                    <td style="padding: 8px;"><?php echo htmlspecialchars($item['title'] ?? 'Unknown Product'); ?></td>
                                                    <td style="padding: 8px;"><?php echo $item['quantity'] ?? 1; ?></td>
                                                    <td style="padding: 8px;">$<?php echo number_format($item['price'] ?? 0, 2); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php else: ?>
                                        <div style="text-align: center; color: #666; padding: 10px;">No items found</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 15px; margin-top: 30px;">
                                <button type="submit" name="update_order" class="btn-admin btn-admin-primary">
                                    Update Order
                                </button>
                                <a href="orders.php?action=view&id=<?php echo $current_order['id']; ?>" class="btn-admin btn-admin-outline">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <?php else: ?>
                <!-- Order List -->
                <!-- Statistics Cards -->
                <?php if ($stats): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <div class="stat-card">
                        <div class="stat-label">Total Orders</div>
                        <div class="stat-value"><?php echo number_format($stats['total_orders'] ?? 0); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Total Revenue</div>
                        <div class="stat-value">$<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Avg Order Value</div>
                        <div class="stat-value">$<?php echo number_format($stats['avg_order_value'] ?? 0, 2); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Unique Customers</div>
                        <div class="stat-value"><?php echo number_format($stats['unique_customers'] ?? 0); ?></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="admin-card">
                    <div class="admin-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <h2 class="admin-card-title" style="margin: 0;">All Orders</h2>
                        
                        <!-- Search and Filter Form -->
                        <form method="GET" action="" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <input type="text" name="search" placeholder="Search orders..." 
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   style="padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 4px; min-width: 200px;">
                            
                            <select name="status_filter" style="padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 4px;">
                                <option value="">All Status</option>
                                <option value="placed" <?php echo $status_filter == 'placed' ? 'selected' : ''; ?>>Placed</option>
                                <option value="processing" <?php echo $status_filter == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="shipped" <?php echo $status_filter == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                <option value="delivered" <?php echo $status_filter == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                            
                            <!-- Single date filter -->
                            <div style="display: flex; align-items: center; gap: 5px;">
                                <span style="font-size: 0.9rem; color: #666;">Date:</span>
                                <input type="date" name="order_date" 
                                       value="<?php echo htmlspecialchars($order_date); ?>"
                                       style="padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 4px; width: 150px;">
                            </div>
                            
                            <button type="submit" class="btn-admin btn-admin-primary" style="padding: 8px 16px;">
                                Search
                            </button>
                            
                            <a href="orders.php" class="btn-admin btn-admin-outline" style="padding: 8px 16px;">
                                Reset
                            </a>
                        </form>
                    </div>
                    
                    <div class="admin-card-body" style="overflow-x: auto;">
                        <?php if (count($orders) > 0): ?>
                            <table class="admin-table" style="width: 100%; font-size: 0.9rem;">
                                <thead>
                                    <tr>
                                        <th style="width: 60px;">ID</th>
                                        <th style="width: 120px;">Order Number</th>
                                        <th style="width: 150px;">Customer</th>
                                        <th style="width: 150px;">Email</th>
                                        <th style="width: 100px;">Status</th>
                                        <th style="width: 120px;">Total Amount</th>
                                        <th style="width: 120px;">Order Date</th>
                                        <th style="width: 150px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><?php echo $order['id']; ?></td>
                                        <td style="overflow: hidden; text-overflow: ellipsis;">
                                            <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                                        </td>
                                        <td style="overflow: hidden; text-overflow: ellipsis;">
                                            <?php echo htmlspecialchars($order['customer_name']); ?>
                                        </td>
                                        <td style="overflow: hidden; text-overflow: ellipsis;">
                                            <a href="mailto:<?php echo htmlspecialchars($order['customer_email']); ?>" title="<?php echo htmlspecialchars($order['customer_email']); ?>">
                                                <?php echo htmlspecialchars(substr($order['customer_email'], 0, 18)); ?><?php echo strlen($order['customer_email']) > 18 ? '...' : ''; ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo htmlspecialchars($order['status']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($order['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong>$<?php echo number_format($order['total_amount'], 2); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo date('M d, Y', strtotime($order['order_date'])); ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons" style="display: flex; gap: 4px; flex-wrap: wrap;">
                                                <a href="orders.php?action=view&id=<?php echo $order['id']; ?>" 
                                                   class="btn-action btn-edit" style="padding: 4px 8px; font-size: 0.8rem;">
                                                    View
                                                </a>
                                                
                                                <a href="orders.php?action=edit&id=<?php echo $order['id']; ?>" 
                                                   class="btn-action" style="color: #00a32a; border-color: #00a32a; padding: 4px 8px; font-size: 0.8rem;">
                                                    Edit
                                                </a>
                                                
                                                <a href="orders.php?action=delete&id=<?php echo $order['id']; ?>" 
                                                   class="btn-action btn-delete" style="padding: 4px 8px; font-size: 0.8rem;"
                                                   onclick="return confirm('Delete this order? This action cannot be undone.')">
                                                    Delete
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                            <div style="display: flex; justify-content: center; margin-top: 20px; gap: 5px;">
                                <?php if ($page > 1): ?>
                                <a href="orders.php?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status_filter=' . urlencode($status_filter) : ''; ?><?php echo !empty($order_date) ? '&order_date=' . urlencode($order_date) : ''; ?>"
                                   class="btn-admin btn-admin-outline" style="padding: 6px 12px;">
                                    Previous
                                </a>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                    <a href="orders.php?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status_filter=' . urlencode($status_filter) : ''; ?><?php echo !empty($order_date) ? '&order_date=' . urlencode($order_date) : ''; ?>"
                                       class="btn-admin <?php echo $i == $page ? 'btn-admin-primary' : 'btn-admin-outline'; ?>" 
                                       style="padding: 6px 12px;">
                                        <?php echo $i; ?>
                                    </a>
                                    <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                    <span style="padding: 6px 12px;">...</span>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                <a href="orders.php?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status_filter=' . urlencode($status_filter) : ''; ?><?php echo !empty($order_date) ? '&order_date=' . urlencode($order_date) : ''; ?>"
                                   class="btn-admin btn-admin-outline" style="padding: 6px 12px;">
                                    Next
                                </a>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div style="text-align: center; margin-top: 15px; color: #666;">
                                Showing <?php echo count($orders); ?> of <?php echo $totalOrders; ?> orders
                            </div>
                            
                        <?php else: ?>
                            <div class="empty-state">
                                <h3>No Orders Found</h3>
                                <p><?php echo !empty($search) || !empty($status_filter) || !empty($order_date) ? 'Try adjusting your search or filters.' : 'There are no orders yet.'; ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="assets/js/admin.js"></script>
</body>
</html>