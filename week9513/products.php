<?php
require_once 'config/config.php';

// 获取基础路径
$base_path = dirname($_SERVER['PHP_SELF']);
if ($base_path == '/') {
    $base_path = '';
} else {
    $base_path .= '/';
}
// 使用绝对路径（根据你的实际路径调整）
$absolute_base_path = '/513/week9513/';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: auth/login.php');
    exit();
}

// Load product data from JSON file
$products_json = file_get_contents('data/products.json');
$products_data = json_decode($products_json, true);
$products = $products_data['products'] ?? [];

// Group products by category
$categories = [];
foreach ($products as $product) {
    $category = $product['category'];
    if (!isset($categories[$category])) {
        $categories[$category] = [];
    }
    $categories[$category][] = $product;
}

// Handle category filtering
$selected_category = $_GET['category'] ?? null;
if ($selected_category && isset($categories[$selected_category])) {
    $filtered_categories = [$selected_category => $categories[$selected_category]];
} else {
    $filtered_categories = $categories;
}

// Get cart items for display
$cart_items = $_SESSION['cart'] ?? [];
$cart_total = 0;
foreach ($cart_items as $item) {
    $cart_total += $item['price'] * $item['quantity'];
}
?>

<?php include 'includes/header.php'; ?>

<!-- Clear Cart Modal -->
<div class="modal fade" id="clearCartModal" tabindex="-1" aria-labelledby="clearCartModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="clearCartModalLabel">Clear Shopping Cart</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to clear your shopping cart? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="post" action="<?php echo $absolute_base_path; ?>cart/clear_cart.php" class="d-inline">
                    <button type="submit" class="btn btn-danger">Confirm Clear</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="workshopDetailsModal" tabindex="-1" aria-labelledby="workshopDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-3">
                <h5 class="modal-title fw-bold" id="workshopDetailsModalLabel">Workshop Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <!-- Left Column: Image and Basic Info -->
                    <div class="col-md-5">
                        <div class="workshop-detail-image mb-3">
                            <img id="detailImage" src="" alt="" class="img-fluid rounded" style="height: 180px; object-fit: cover; width: 100%;">
                        </div>
                        
                        <div class="workshop-basic-info">
                            <h4 id="detailTitle" class="mb-2"></h4>
                            
                            <div class="d-flex gap-2 mb-3">
                                <span id="detailCategory" class="badge bg-secondary"></span>
                                <span id="detailLevel" class="badge bg-primary"></span>
                            </div>
                            
                            <div class="price-section mb-4">
                                <div id="detailPrice" class="h3 text-primary mb-1"></div>
                                <div id="detailOriginalPrice" class="text-muted"></div>
                            </div>
                            
                            <div class="workshop-meta mb-4">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-user text-primary me-2" style="width: 20px;"></i>
                                    <div>
                                        <small class="text-muted d-block">Instructor</small>
                                        <strong id="detailInstructor"></strong>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-clock text-primary me-2" style="width: 20px;"></i>
                                    <div>
                                        <small class="text-muted d-block">Duration</small>
                                        <strong id="detailDuration"></strong>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-users text-primary me-2" style="width: 20px;"></i>
                                    <div>
                                        <small class="text-muted d-block">Max Participants</small>
                                        <strong id="detailParticipants"></strong>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-store text-primary me-2" style="width: 20px;"></i>
                                    <div>
                                        <small class="text-muted d-block">Supplier</small>
                                        <strong id="detailSupplier"></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column: Description and Features -->
                    <div class="col-md-7">
                        <div class="workshop-description-section">
                            <h6 class="fw-bold mb-2">Description</h6>
                            <p id="detailDescription" class="mb-4 text-muted" style="font-size: 0.95rem;"></p>
                            
                            <h6 class="fw-bold mb-2">Features</h6>
                            <div class="features-section">
                                <ul class="list-unstyled mb-4">
                                    <li id="detailFeature1" class="mb-2">
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                    </li>
                                    <li id="detailFeature2">
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-3">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <form method="post" action="<?php echo $absolute_base_path; ?>cart/add_to_cart.php" class="d-inline" id="addToCartForm">
                    <input type="hidden" name="workshop_id" id="modalWorkshopId" value="">
                    <input type="hidden" name="workshop_title" id="modalWorkshopTitle" value="">
                    <input type="hidden" name="price" id="modalWorkshopPrice" value="">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-cart-plus me-1"></i>Add to Cart
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="container py-5">
    <div class="row">
        <!-- Main Workshops Content -->
        <div class="col-lg-8">
            <div class="row mb-4">
                <div class="col-md-8">
                    <h1 class="mb-2">Our Workshops</h1>
                    <p class="lead text-muted">Discover hands-on learning experiences across various creative disciplines.</p>
                    <div class="d-flex align-items-center mt-3">
                        <i class="fas fa-user-circle text-primary me-2"></i>
                        <small class="text-muted">Welcome back, <?php echo htmlspecialchars(getCurrentUserName()); ?>!</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-center h-100">
                        <select class="form-select" onchange="if(this.value) window.location.href=this.value">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category_name => $category_products): ?>
                            <option value="?category=<?php echo urlencode($category_name); ?>" 
                                    <?php echo $selected_category === $category_name ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category_name); ?> (<?php echo count($category_products); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <?php if ($selected_category): ?>
            <div class="alert alert-info">
                Showing workshops in: <strong><?php echo htmlspecialchars($selected_category); ?></strong>
                <a href="products.php" class="float-end">Show all categories</a>
            </div>
            <?php endif; ?>

            <?php foreach ($filtered_categories as $category_name => $category_products): ?>
            <section class="category-section mb-5">
                <h2 class="category-title mb-4 pb-2 border-bottom"><?php echo htmlspecialchars($category_name); ?></h2>
                <div class="row">
                    <?php foreach ($category_products as $product): ?>
                    <div class="col-lg-6 col-md-12 mb-4">
                        <div class="card workshop-card h-100" data-workshop-id="<?php echo $product['id']; ?>">
                            <div class="workshop-image-placeholder position-relative workshop-image-clickable" 
                                 data-bs-toggle="modal" 
                                 data-bs-target="#workshopDetailsModal"
                                 data-workshop='<?php echo htmlspecialchars(json_encode($product), ENT_QUOTES, 'UTF-8'); ?>'>
                                <?php if ($product['discount_percent'] > 0): ?>
                                    <span class="position-absolute top-0 start-0 badge bg-danger m-2">
                                        <?php echo $product['discount_percent']; ?>% OFF
                                    </span>
                                <?php endif; ?>
                                
                                <?php if (!empty($product['picture']) && file_exists($product['picture'])): ?>
                                    <img src="<?php echo htmlspecialchars($product['picture']); ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                         class="img-fluid rounded-top" 
                                         style="height: 200px; object-fit: cover; width: 100%;">
                                <?php else: ?>
                                    <div class="d-flex flex-column justify-content-center align-items-center h-100 bg-light">
                                        <i class="fas fa-graduation-cap fa-3x text-muted mb-2"></i>
                                        <p class="text-muted mb-0">Workshop Image</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-body d-flex flex-column">
                                <div class="mb-2">
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($product['skill_level'] ?? 'All Levels'); ?></span>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($product['duration']); ?></span>
                                </div>
                                
                                <h5 class="card-title workshop-title-clickable" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#workshopDetailsModal"
                                    data-workshop='<?php echo htmlspecialchars(json_encode($product), ENT_QUOTES, 'UTF-8'); ?>'
                                    style="cursor: pointer;">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </h5>
                                
                                <p class="card-text flex-grow-1 workshop-desc-clickable" 
                                   data-bs-toggle="modal" 
                                   data-bs-target="#workshopDetailsModal"
                                   data-workshop='<?php echo htmlspecialchars(json_encode($product), ENT_QUOTES, 'UTF-8'); ?>'
                                   style="cursor: pointer;">
                                    <?php echo htmlspecialchars($product['description']); ?>
                                </p>
                                
                                <div class="workshop-meta mb-3">
                                    <small class="text-muted d-block">
                                        <i class="fas fa-user me-1"></i>
                                        Instructor: <?php echo htmlspecialchars($product['instructor']); ?>
                                    </small>
                                    <small class="text-muted d-block">
                                        <i class="fas fa-users me-1"></i>
                                        Max: <?php echo htmlspecialchars($product['max_participants']); ?> students
                                    </small>
                                    <small class="text-muted d-block">
                                        <i class="fas fa-store me-1"></i>
                                        Supplier: <?php echo htmlspecialchars($product['supplier']); ?>
                                    </small>
                                </div>
                                
                                <div class="price-section mt-auto">
                                    <?php if ($product['discount_percent'] > 0): ?>
                                        <?php
                                        $original_price = $product['price'];
                                        $discounted_price = $original_price * (1 - $product['discount_percent'] / 100);
                                        ?>
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <span class="h5 text-primary mb-0">$<?php echo number_format($discounted_price, 2); ?></span>
                                                <span class="text-muted text-decoration-line-through ms-2">$<?php echo number_format($original_price, 2); ?></span>
                                            </div>
                                            <span class="badge bg-success">Save $<?php echo number_format($original_price - $discounted_price, 2); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="h5 text-primary">$<?php echo number_format($product['price'], 2); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="card-footer bg-transparent">
                                <div class="d-grid gap-2">
                                    <!-- View Details Button (纯文字) -->
                                    <button type="button" 
                                            class="btn btn-outline-info w-100 view-details-btn"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#workshopDetailsModal"
                                            data-workshop='<?php echo htmlspecialchars(json_encode($product), ENT_QUOTES, 'UTF-8'); ?>'>
                                        View Details
                                    </button>
                                    
                                    <!-- Add to Cart Button -->
                                    <form method="post" action="<?php echo $absolute_base_path; ?>cart/add_to_cart.php" class="d-inline">
                                        <input type="hidden" name="workshop_id" value="<?php echo $product['id']; ?>">
                                        <input type="hidden" name="workshop_title" value="<?php echo htmlspecialchars($product['name']); ?>">
                                        <input type="hidden" name="price" value="<?php echo isset($discounted_price) ? $discounted_price : $product['price']; ?>">
                                        <button type="submit" class="btn btn-outline-primary w-100">
                                            Add to Cart
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endforeach; ?>
            
            <?php if (empty($filtered_categories)): ?>
            <div class="text-center py-5">
                <i class="fas fa-graduation-cap fa-3x text-muted mb-3"></i>
                <h3>No Workshops Found</h3>
                <p class="text-muted">No workshops available in this category. Please check other categories.</p>
                <a href="products.php" class="btn btn-primary">View All Workshops</a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Shopping Cart Sidebar -->
        <div class="col-lg-4">
            <div class="sticky-top" style="top: 20px;">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Your Workshop Cart</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($cart_items)): ?>
                        <!-- Empty Cart State -->
                        <div class="text-center py-4">
                            <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                            <h5>Your cart is empty</h5>
                            <p class="text-muted">Add some workshops to get started!</p>
                        </div>
                        <?php else: ?>
                        <!-- Cart Items -->
                        <div class="cart-items">
                            <?php foreach ($cart_items as $item): ?>
                            <div class="cart-item border-bottom pb-3 mb-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($item['title']); ?></h6>
                                    <span class="text-primary fw-bold">$<?php echo number_format($item['price'], 2); ?></span>
                                </div>
                                
                                <div class="quantity-controls d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <form method="post" action="<?php echo $absolute_base_path; ?>cart/update_quantity.php" class="d-inline">
                                            <input type="hidden" name="workshop_id" value="<?php echo $item['id']; ?>">
                                            <input type="hidden" name="change" value="-1">
                                            <button type="submit" class="btn btn-outline-secondary btn-sm">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                        </form>
                                        <form method="post" action="<?php echo $absolute_base_path; ?>cart/set_quantity.php" class="d-inline" id="quantity-form-<?php echo $item['id']; ?>">
                                            <input type="hidden" name="workshop_id" value="<?php echo $item['id']; ?>">
                                            <input type="number" 
                                                   name="quantity"
                                                   class="form-control quantity-input mx-2 text-center" 
                                                   value="<?php echo $item['quantity']; ?>" 
                                                   min="1" 
                                                   max="10"
                                                   style="width: 60px;"
                                                   onchange="document.getElementById('quantity-form-<?php echo $item['id']; ?>').submit()">
                                        </form>
                                        <form method="post" action="<?php echo $absolute_base_path; ?>cart/update_quantity.php" class="d-inline">
                                            <input type="hidden" name="workshop_id" value="<?php echo $item['id']; ?>">
                                            <input type="hidden" name="change" value="1">
                                            <button type="submit" class="btn btn-outline-secondary btn-sm">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </form>
                                    </div>
                                    <form method="post" action="<?php echo $absolute_base_path; ?>cart/remove_from_cart.php" class="d-inline">
                                        <input type="hidden" name="workshop_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="btn btn-link text-danger p-0">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                                <div class="text-end mt-1">
                                    <small class="text-muted">Subtotal: $<?php echo number_format($item['price'] * $item['quantity'], 2); ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Order Summary -->
                        <div class="order-summary mt-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal (<?php echo count($cart_items); ?> items):</span>
                                <span>$<?php echo number_format($cart_total, 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Booking Fee:</span>
                                <span>$5.00</span>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span>Tax (10%):</span>
                                <span>$<?php echo number_format($cart_total * 0.1, 2); ?></span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-4">
                                <strong>Total:</strong>
                                <strong class="text-primary fs-5">$<?php echo number_format($cart_total + 5 + ($cart_total * 0.1), 2); ?></strong>
                            </div>
                            
                            <a href="<?php echo $absolute_base_path; ?>cart/checkout.php" class="btn btn-success w-100 mb-2">
                                <i class="fas fa-credit-card me-2"></i>Proceed to Checkout
                            </a>
                            <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#clearCartModal">
                                <i class="fas fa-trash me-2"></i>Clear Cart
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.workshop-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    cursor: default;
}

.workshop-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.category-title {
    color: #2c3338;
    font-weight: 600;
}

.workshop-image-placeholder {
    height: 200px;
    overflow: hidden;
}

.workshop-image-clickable {
    cursor: pointer;
}

.workshop-title-clickable:hover,
.workshop-desc-clickable:hover {
    color: #0d6efd;
}

.quantity-controls .form-control {
    border: 1px solid #dee2e6;
}

.sticky-top {
    position: sticky;
    z-index: 1020;
}

.cart-item:last-child {
    border-bottom: none !important;
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
}

.view-details-btn {
    border-color: #6c757d;
    color: #6c757d;
}

.view-details-btn:hover {
    background-color: #6c757d;
    border-color: #6c757d;
    color: white;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const workshopDetailsModal = document.getElementById('workshopDetailsModal');
    
    if (workshopDetailsModal) {
        workshopDetailsModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const workshopData = JSON.parse(button.getAttribute('data-workshop'));
            
            // 计算折扣价格
            const originalPrice = workshopData.price;
            const discountPercent = workshopData.discount_percent;
            const discountedPrice = discountPercent > 0 
                ? originalPrice * (1 - discountPercent / 100) 
                : originalPrice;
            
            // 设置模态框内容
            document.getElementById('detailTitle').textContent = workshopData.name;
            document.getElementById('detailCategory').textContent = workshopData.category;
            document.getElementById('detailLevel').textContent = workshopData.skill_level;
            document.getElementById('detailDescription').textContent = workshopData.description;
            document.getElementById('detailInstructor').textContent = workshopData.instructor;
            document.getElementById('detailDuration').textContent = workshopData.duration;
            document.getElementById('detailParticipants').textContent = workshopData.max_participants + ' students';
            document.getElementById('detailSupplier').textContent = workshopData.supplier;
            document.getElementById('detailFeature1').innerHTML = '<i class="fas fa-check text-success me-2"></i>' + workshopData.custom_field1;
            document.getElementById('detailFeature2').innerHTML = '<i class="fas fa-check text-success me-2"></i>' + workshopData.custom_field2;
            
            // 设置图片
            // 设置图片
               const detailImage = document.getElementById('detailImage');
                if (workshopData.picture && workshopData.picture.trim() !== '') {
                   detailImage.src = workshopData.picture;
                   detailImage.alt = workshopData.name;
               } else {
                       detailImage.style.display = 'none';
                     document.querySelector('.workshop-detail-image').innerHTML = 
                     '<div class="d-flex flex-column justify-content-center align-items-center h-100 bg-light rounded" style="height: 180px;">' +
                       '<i class="fas fa-graduation-cap fa-4x text-muted mb-2"></i>' +
                         '<p class="text-muted mb-0" style="font-size: 0.85rem;">No Image Available</p>' +
                         '</div>';
                        }
            
            // 设置价格
            const detailPrice = document.getElementById('detailPrice');
            const detailOriginalPrice = document.getElementById('detailOriginalPrice');
            
            if (discountPercent > 0) {
                detailPrice.textContent = '$' + discountedPrice.toFixed(2);
                detailOriginalPrice.innerHTML = '<span class="text-decoration-line-through">$' + originalPrice.toFixed(2) + '</span>' +
                                               '<span class="badge bg-danger ms-2">' + discountPercent + '% OFF</span>';
            } else {
                detailPrice.textContent = '$' + originalPrice.toFixed(2);
                detailOriginalPrice.innerHTML = '&nbsp;'; // 非折扣商品留空
            }
            
            // 设置添加到购物车表单的隐藏字段
            document.getElementById('modalWorkshopId').value = workshopData.id;
            document.getElementById('modalWorkshopTitle').value = workshopData.name;
            document.getElementById('modalWorkshopPrice').value = discountedPrice;
            
            // 设置模态框标题
            document.getElementById('workshopDetailsModalLabel').textContent = workshopData.name;
        });
        
        // 重置模态框内容当关闭时
        workshopDetailsModal.addEventListener('hidden.bs.modal', function() {
            const detailImage = document.getElementById('detailImage');
            detailImage.style.display = 'block';
            
            // 恢复默认的图片容器
            const imageContainer = document.querySelector('.workshop-detail-image');
            if (imageContainer.innerHTML.includes('No Image Available')) {
                imageContainer.innerHTML = '<img id="detailImage" src="" alt="" class="img-fluid rounded" style="max-height: 300px; object-fit: cover; width: 100%;">';
            }
        });
    }
    
    // 使整个卡片可点击（除了按钮区域）
    document.querySelectorAll('.workshop-card').forEach(card => {
        const cardBody = card.querySelector('.card-body');
        const footer = card.querySelector('.card-footer');
        
        // 点击卡片主体（除按钮外）触发模态框
        cardBody.addEventListener('click', function(e) {
            // 如果点击的是按钮或者表单元素，不触发模态框
            if (e.target.tagName === 'BUTTON' || 
                e.target.tagName === 'INPUT' || 
                e.target.tagName === 'SELECT' ||
                e.target.closest('form') ||
                e.target.closest('.badge')) {
                return;
            }
            
            const clickableElement = card.querySelector('.workshop-title-clickable');
            if (clickableElement) {
                clickableElement.click();
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>