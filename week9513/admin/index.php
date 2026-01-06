<?php
session_start();
require_once '../config/config.php';

// 统一使用相同的验证逻辑
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// 获取当前文件名用于激活状态
$current_page = basename($_SERVER['PHP_SELF']);

// 获取统计数据
$total_products = 0;
$total_categories = 0;
$total_users = 0;
$recent_orders = 0;

// 从 JSON 文件获取产品数据
$products_file = '../data/products.json';
if (file_exists($products_file)) {
    $products_data = json_decode(file_get_contents($products_file), true);
    $total_products = count($products_data['products'] ?? []);
    
    // 计算分类数量
    $categories = [];
    foreach ($products_data['products'] ?? [] as $product) {
        $category = $product['category'];
        if (!in_array($category, $categories)) {
            $categories[] = $category;
        }
    }
    $total_categories = count($categories);
}

// 模拟用户数据
$total_users = 45;
$recent_orders = 12;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SkillCraft Workshops</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="admin-wrapper">
        <!-- 侧边栏 -->
       <!-- 修改所有管理页面的侧边栏，添加 Orders 链接 -->
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

        <!-- 主内容区 -->
        <main class="admin-main">
            <header class="admin-header">
                <h1>Admin Dashboard</h1>
                <p>Welcome back, <?php echo $_SESSION['first_name'] ?? 'Admin'; ?>!</p>
            </header>

            <div class="admin-content">
                <!-- 统计卡片 - 删除这部分 -->
                
                <!-- 快速操作 -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h2 class="admin-card-title">Quick Actions</h2>
                    </div>
                    <div class="admin-card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                            <a href="products.php?action=add" class="btn-admin btn-admin-primary">
                                <i class="fas fa-plus me-2"></i>Add New Workshop
                            </a>
                            <a href="products.php" class="btn-admin btn-admin-outline">
                                Manage Workshops
                            </a>
                            <a href="categories.php" class="btn-admin btn-admin-outline">
                                Manage Categories
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 最近产品 -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h2 class="admin-card-title">Recent Workshops</h2>
                    </div>
                    <div class="admin-card-body">
                        <?php if ($total_products > 0): ?>
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th style="width:220px">Actions</th>   <!-- 仅这里加宽 -->
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $recent_products = array_slice($products_data['products'] ?? [], 0, 5);
                                    foreach ($recent_products as $product): 
                                    ?>
                                    <tr>
                                        <td><?php echo $product['id']; ?></td>
                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td><?php echo htmlspecialchars($product['category']); ?></td>
                                        <td>$<?php echo number_format($product['price'], 2); ?></td>
                                        <td>
                                            <span class="admin-badge badge-published">Published</span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="products.php?action=edit&id=<?php echo $product['id']; ?>" 
                                                   class="btn-action btn-edit">
                                                    <i class="fas fa-edit me-1"></i> Edit
                                                </a>
                                                <a href="products.php?action=delete&id=<?php echo $product['id']; ?>" 
                                                   class="btn-action btn-delete"
                                                   onclick="return confirm('Are you sure you want to delete this workshop?')">
                                                    <i class="fas fa-trash me-1"></i> Delete
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <?php if ($total_products > 5): ?>
                            <div style="text-align: center; margin-top: 20px;">
                                <a href="products.php" class="btn-admin btn-admin-outline">
                                    View All Workshops
                                </a>
                            </div>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-graduation-cap"></i>
                                <h3>No Workshops Found</h3>
                                <p>Get started by creating your first workshop.</p>
                                <a href="products.php?action=add" class="btn-admin btn-admin-primary">
                                    Add New Workshop
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="assets/js/admin.js"></script>
</body>
</html>