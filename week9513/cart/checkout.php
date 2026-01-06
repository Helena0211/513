<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit();
}

// 获取购物车数据
$cart_items = $_SESSION['cart'] ?? [];

// 处理订单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    // 获取表单数据
    $shipping_address = $_POST['shipping_address'] ?? '';
    $payment_method = $_POST['payment_method'] ?? 'credit_card';
    $additional_notes = $_POST['additional_notes'] ?? '';
    
    if (empty($cart_items)) {
        $_SESSION['error'] = 'Your cart is empty';
        header('Location: checkout.php');
        exit();
    }
    
    // 计算总金额
    $total_amount = 0;
    foreach ($cart_items as $item) {
        $total_amount += $item['price'] * $item['quantity'];
    }
    
    // 生成订单信息
    $order_number = 'ORD-' . date('Ymd') . '-' . strtoupper(uniqid());
    $order_date = date('Y-m-d H:i:s');
    $booking_fee = 5.00;
    $tax_rate = 0.10; // 10% tax
    $tax_amount = $total_amount * $tax_rate;
    $final_total = $total_amount + $booking_fee + $tax_amount;
    
    // 获取客户信息
    $customer_name = ($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '');
    $customer_email = $_SESSION['subscriber_email'] ?? '';
    $customer_phone = $_SESSION['subscriber_phone'] ?? '';
    
    try {
        // 开始事务
        $db->beginTransaction();
        
        // 准备SQL插入订单
        $sql = "INSERT INTO wpej_order (
            order_number, 
            customer_name, 
            customer_email, 
            customer_phone, 
            shipping_address, 
            payment_method, 
            additional_notes, 
            items_json, 
            subtotal, 
            shipping_fee, 
            tax_amount, 
            total_amount, 
            status, 
            order_date, 
            created_at, 
            updated_at
        ) VALUES (
            :order_number, 
            :customer_name, 
            :customer_email, 
            :customer_phone, 
            :shipping_address, 
            :payment_method, 
            :additional_notes, 
            :items_json, 
            :subtotal, 
            :shipping_fee, 
            :tax_amount, 
            :total_amount, 
            :status, 
            :order_date, 
            NOW(), 
            NOW()
        )";
        
        $stmt = $db->prepare($sql);
        
        // 绑定参数
        $stmt->bindParam(':order_number', $order_number);
        $stmt->bindParam(':customer_name', $customer_name);
        $stmt->bindParam(':customer_email', $customer_email);
        $stmt->bindParam(':customer_phone', $customer_phone);
        $stmt->bindParam(':shipping_address', $shipping_address);
        $stmt->bindParam(':payment_method', $payment_method);
        $stmt->bindParam(':additional_notes', $additional_notes);
        
        $items_json = json_encode($cart_items);
        $stmt->bindParam(':items_json', $items_json);
        
        $stmt->bindParam(':subtotal', $total_amount);
        $stmt->bindParam(':shipping_fee', $booking_fee);
        $stmt->bindParam(':tax_amount', $tax_amount);
        $stmt->bindParam(':total_amount', $final_total);
        
        $status = 'placed';
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':order_date', $order_date);
        
        // 执行插入
        if ($stmt->execute()) {
            // 提交事务
            $db->commit();
            
            // 保存成功订单数据到session（在清除购物车之前）
            $_SESSION['order_success'] = true;
            $_SESSION['order_success_data'] = [
                'order_number' => $order_number,
                'order_total' => $final_total,
                'order_date' => $order_date
            ];
            
            // 清除购物车
            $_SESSION['cart'] = [];
            
            // 重定向回同一页面，避免表单重复提交
            header('Location: checkout.php?success=1');
            exit();
        } else {
            throw new Exception('Failed to save order to database');
        }
        
    } catch (Exception $e) {
        // 回滚事务
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        $_SESSION['error'] = 'Failed to process order: ' . $e->getMessage();
        header('Location: checkout.php');
        exit();
    }
}

// 如果是GET请求，检查是否有成功订单
$show_success_modal = false;
$order_success_data = [];

if (isset($_SESSION['order_success']) && $_SESSION['order_success'] && isset($_SESSION['order_success_data'])) {
    $show_success_modal = true;
    $order_success_data = $_SESSION['order_success_data'];
    
    // 清空成功状态，避免重复显示
    unset($_SESSION['order_success']);
    unset($_SESSION['order_success_data']);
}

// 计算总金额（用于显示）
$total_amount = 0;
foreach ($cart_items as $item) {
    $total_amount += $item['price'] * $item['quantity'];
}

// 生成订单信息（用于显示）
$order_number = 'ORD-' . date('Ymd') . '-' . strtoupper(uniqid());
$order_date = date('Y-m-d H:i:s');
$booking_fee = 5.00;
$tax_rate = 0.10;
$tax_amount = $total_amount * $tax_rate;
$final_total = $total_amount + $booking_fee + $tax_amount;
?>

<?php include '../includes/header.php'; ?>

<!-- Clear Storage Modal -->
<div class="modal fade" id="clearStorageModal" tabindex="-1" aria-labelledby="clearStorageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="clearStorageModalLabel">Clear Shopping Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>This will permanently clear your shopping cart and order history from your local storage.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmClearStorage()">Confirm Clear</button>
            </div>
        </div>
    </div>
</div>

<!-- Order Success Modal -->
<?php if ($show_success_modal): ?>
<div class="modal fade show" id="orderSuccessModal" tabindex="-1" aria-labelledby="orderSuccessModalLabel" aria-modal="true" role="dialog" style="display: block; background-color: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="orderSuccessModalLabel">Order Successful</h5>
                <button type="button" class="btn-close" onclick="closeSuccessModal()" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="order-success-content">
                    <p>Thank You for Your Order!</p>
                    <p>Your order has been received and is being processed.</p>
                    
                    <div class="order-details mt-4">
                        <p><strong>Order Details</strong></p>
                        <div class="row">
                            <div class="col-12 mb-2">
                                <strong>Order Number:</strong><br>
                                <span class="text-primary"><?php echo htmlspecialchars($order_success_data['order_number']); ?></span>
                            </div>
                            <div class="col-12 mb-2">
                                <strong>Total Amount:</strong><br>
                                <span class="text-primary">$<?php echo number_format($order_success_data['order_total'], 2); ?></span>
                            </div>
                            <div class="col-12 mb-2">
                                <strong>Order Date:</strong><br>
                                <?php echo date('F d, Y H:i:s', strtotime($order_success_data['order_date'])); ?>
                            </div>
                            <div class="col-12 mb-2">
                                <strong>Order Status:</strong> <span>Processing</span>
                            </div>
                        </div>
                    </div>
                    
                    <p class="mt-4">An order confirmation has been sent to your email address.</p>
                    
                    <div class="text-center mt-4">
                        <a href="../products.php" class="btn btn-primary me-2">Continue Shopping</a>
                        <button type="button" class="btn btn-secondary" onclick="closeSuccessModal()">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Hero Section -->
<section class="checkout-hero bg-primary text-white py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-12 text-center">
                <h1 class="display-5 fw-bold mb-3">Checkout</h1>
                <p class="lead mb-0">Review your order and complete your purchase</p>
            </div>
        </div>
    </div>
</section>

<div class="container my-5">

    <?php if (empty($cart_items) && !$show_success_modal): ?>
    <!-- 空购物车（只有在没有成功订单时才显示） -->
    <div class="empty-cart-card text-center py-5">
        <i class="fas fa-shopping-cart fa-4x text-muted mb-4"></i>
        <h3 class="mb-3">Your cart is empty</h3>
        <p class="text-muted mb-4 lead">Browse our workshops and start learning something new!</p>
        <a href="../products.php" class="btn btn-primary px-5">Continue Shopping</a>
    </div>

    <?php elseif (!$show_success_modal): ?>
    <!-- 结账表单（只有在没有成功订单时才显示） -->
    <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['error']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['error']); endif; ?>

    <form method="POST" action="" id="checkoutForm">
        <div class="row">
            <div class="col-lg-8">
                <!-- Order Information Section -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h4 class="mb-0">Order Information</h4>
                    </div>
                    <div class="card-body">
                        <!-- Customer Details -->
                        <div class="mb-4">
                            <h5 class="mb-3">Customer Details</h5>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <strong>Name:</strong><br>
                                    <?php echo htmlspecialchars($_SESSION['first_name'] ?? '') . ' ' . htmlspecialchars($_SESSION['last_name'] ?? 'N/A'); ?>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <strong>Email:</strong><br>
                                    <?php echo htmlspecialchars($_SESSION['subscriber_email'] ?? 'N/A'); ?>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <strong>Phone:</strong><br>
                                    <?php echo htmlspecialchars($_SESSION['subscriber_phone'] ?? 'N/A'); ?>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Shipping Address -->
                        <div class="mb-4">
                            <h5 class="mb-3">Shipping Address *</h5>
                            <div class="form-floating">
                                <textarea class="form-control" placeholder="Enter your complete shipping address" id="shippingAddress" name="shipping_address" style="height: 100px" required></textarea>
                                <label for="shippingAddress">Enter your complete shipping address</label>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Payment Method -->
                        <div class="mb-4">
                            <h5 class="mb-3">Payment Method</h5>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="paymentMethod" id="creditCard" value="credit_card" checked>
                                    <label class="form-check-label" for="creditCard">
                                        Credit Card
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="paymentMethod" id="paypal" value="paypal">
                                    <label class="form-check-label" for="paypal">
                                        PayPal
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="paymentMethod" id="bankTransfer" value="bank_transfer">
                                    <label class="form-check-label" for="bankTransfer">
                                        Bank Transfer
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Additional Notes -->
                            <div class="mb-3">
                                <label for="additionalNotes" class="form-label">Additional Notes (Optional)</label>
                                <textarea class="form-control" id="additionalNotes" name="additional_notes" rows="3" placeholder="Any special instructions for your order..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Order Summary Card -->
                <div class="card sticky-top" style="top: 20px;">
                    <div class="card-header bg-light">
                        <h4 class="mb-0">Order Summary</h4>
                    </div>
                    <div class="card-body">
                        <!-- Workshop Items -->
                        <div class="mb-4">
                            <?php foreach ($cart_items as $item): ?>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <strong><?php echo htmlspecialchars($item['title']); ?></strong><br>
                                    <small class="text-muted">Quantity: <?php echo $item['quantity']; ?></small>
                                </div>
                                <div class="text-end">
                                    <div class="text-primary fw-bold">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                                    <small class="text-muted">$<?php echo number_format($item['price'], 2); ?> each</small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <hr class="my-3">

                        <!-- Price Breakdown -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal:</span>
                                <span>$<?php echo number_format($total_amount, 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Shipping:</span>
                                <span>$<?php echo number_format($booking_fee, 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Tax:</span>
                                <span>$<?php echo number_format($tax_amount, 2); ?></span>
                            </div>
                        </div>

                        <hr class="my-3">

                        <!-- Total -->
                        <div class="d-flex justify-content-between mb-4">
                            <strong class="fs-5">Total:</strong>
                            <strong class="text-primary fs-4">$<?php echo number_format($final_total, 2); ?></strong>
                        </div>

                        <!-- Hidden fields for form submission -->
                        <input type="hidden" name="place_order" value="1">
                        <input type="hidden" name="payment_method" id="paymentMethodInput" value="credit_card">

                        <!-- Place Order Button -->
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg" id="placeOrderBtn">
                                Place Order - $<?php echo number_format($final_total, 2); ?>
                            </button>
                            
                            <!-- Action Buttons (移除了Download Order按钮) -->
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#clearStorageModal">
                                <i class="fas fa-trash me-2"></i>Clear Storage
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
// 更新支付方式到隐藏字段
document.querySelectorAll('input[name="paymentMethod"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.getElementById('paymentMethodInput').value = this.value;
    });
});

// 表单提交处理
document.getElementById('checkoutForm')?.addEventListener('submit', function(e) {
    const shippingAddress = document.getElementById('shippingAddress').value;
    
    if (!shippingAddress.trim()) {
        e.preventDefault();
        alert('Please enter your shipping address');
        document.getElementById('shippingAddress').focus();
        return;
    }
    
    // 显示加载状态
    const placeOrderBtn = document.getElementById('placeOrderBtn');
    placeOrderBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
    placeOrderBtn.disabled = true;
});

function confirmClearStorage() {
    // 关闭模态框
    const modal = bootstrap.Modal.getInstance(document.getElementById('clearStorageModal'));
    modal.hide();

    // 发送AJAX请求清除购物车
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '../cart/clear_cart.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status === 200) {
            window.location.href = '../products.php';
        }
    };
    xhr.send();
}

function closeSuccessModal() {
    const modal = document.getElementById('orderSuccessModal');
    if (modal) {
        modal.style.display = 'none';
        // 简单刷新页面，显示空购物车内容
        location.reload();
    }
}
</script>

<style>
.checkout-hero {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.card {
    border: 1px solid #e0e0e0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #e0e0e0;
}

.form-check-input:checked {
    background-color: #667eea;
    border-color: #667eea;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #5a6fd8 0%, #6b3f9d 100%);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

textarea.form-control {
    resize: none;
}

.sticky-top {
    position: sticky;
    z-index: 1020;
}

.order-success-content {
    text-align: left;
}

.order-success-content .order-details {
    background-color: #f8f9fa;
    padding: 1rem;
    border-radius: 0.25rem;
    border: 1px solid #dee2e6;
}

.order-success-content p {
    margin-bottom: 1rem;
}

.order-success-content .btn {
    min-width: 150px;
}

/* 模态框样式 */
#orderSuccessModal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1050;
}

#orderSuccessModal .modal-dialog {
    margin: 1.75rem auto;
    max-width: 500px;
}

#orderSuccessModal .modal-content {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

/* 空购物车样式 */
.empty-cart-card {
    background-color: #fff;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}
</style>

<?php include '../includes/footer.php'; ?>