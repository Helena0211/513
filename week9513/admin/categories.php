<?php
session_start();
require_once '../config/config.php';

// 只保留一个会话验证
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// 获取当前文件名用于激活状态
$current_page = basename($_SERVER['PHP_SELF']);

// 加载产品数据以提取分类
$products_file = '../data/products.json';
$categories = [];

if (file_exists($products_file)) {
    $products_data = json_decode(file_get_contents($products_file), true);
    $products = $products_data['products'] ?? [];
    
    // 从产品中提取唯一分类
    foreach ($products as $product) {
        $category = $product['category'];
        if (!in_array($category, $categories)) {
            $categories[] = $category;
        }
    }
}

// 计算每个分类的产品数量
$category_counts = [];
foreach ($categories as $category) {
    $count = 0;
    foreach ($products as $product) {
        if ($product['category'] === $category) {
            $count++;
        }
    }
    $category_counts[$category] = $count;
}

$action = $_GET['action'] ?? 'list';
$category_name = $_GET['name'] ?? null;

// 处理分类操作
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_category'])) {
        $new_category = trim($_POST['category_name']);
        
        if (!in_array($new_category, $categories)) {
            $categories[] = $new_category;
            header('Location: categories.php?success=Category added successfully');
            exit();
        } else {
            $error = 'Category already exists';
        }
    }
    
    if (isset($_POST['update_category'])) {
        $old_category = $category_name;
        $new_category = trim($_POST['category_name']);
        
        if ($old_category && $new_category && $old_category !== $new_category) {
            // 更新产品中的分类引用
            foreach ($products as &$product) {
                if ($product['category'] === $old_category) {
                    $product['category'] = $new_category;
                }
            }
            
            // 保存更新后的产品数据
            $products_data['products'] = $products;
            if (file_put_contents($products_file, json_encode($products_data, JSON_PRETTY_PRINT))) {
                header('Location: categories.php?success=Category updated successfully');
                exit();
            } else {
                $error = 'Failed to update category';
            }
        }
    }
}

// 删除分类
if ($action == 'delete' && $category_name) {
    // 检查分类是否被产品使用
    if (isset($category_counts[$category_name]) && $category_counts[$category_name] > 0) {
        $error = 'Cannot delete category that has workshops. Please reassign workshops first.';
    } else {
        $categories = array_filter($categories, function($cat) use ($category_name) {
            return $cat !== $category_name;
        });
        header('Location: categories.php?success=Category deleted successfully');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - SkillCraft Admin</title>
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
                <h1>
                    <?php echo $action == 'add' ? 'Add New Category' : ($action == 'edit' ? 'Edit Category' : 'Manage Categories'); ?>
                </h1>
                <p>Organize workshops into categories</p>
            </header>

            <div class="admin-content">
                <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <?php if ($action == 'add' || $action == 'edit'): ?>
                <!-- 分类表单 -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h2 class="admin-card-title">
                            <?php echo $action == 'add' ? 'Add New Category' : 'Edit Category'; ?>
                        </h2>
                    </div>
                    <div class="admin-card-body">
                        <form method="post" class="admin-form">
                            <div class="form-group">
                                <label for="category_name">Category Name *</label>
                                <input type="text" id="category_name" name="category_name" class="form-control" required
                                       value="<?php echo htmlspecialchars($category_name ?? ''); ?>">
                            </div>

                            <?php if ($action == 'edit' && $category_name): ?>
                            <div class="form-group">
                                <label>Workshops in this category:</label>
                                <div class="workshop-count">
                                    <strong><?php echo $category_counts[$category_name] ?? 0; ?></strong> workshops
                                </div>
                            </div>
                            <?php endif; ?>

                            <div style="display: flex; gap: 15px; margin-top: 30px;">
                                <?php if ($action == 'add'): ?>
                                <button type="submit" name="add_category" class="btn-admin btn-admin-primary">
                                    <i class="fas fa-plus me-2"></i>Add Category
                                </button>
                                <?php else: ?>
                                <button type="submit" name="update_category" class="btn-admin btn-admin-primary">
                                    <i class="fas fa-save me-2"></i>Update Category
                                </button>
                                <?php endif; ?>
                                <a href="categories.php" class="btn-admin btn-admin-outline">
                                    <i class="fas fa-arrow-left me-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <?php else: ?>
                <!-- 分类列表 -->
                <div class="admin-card">
                    <div class="admin-card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h2 class="admin-card-title">All Categories</h2>
                        <a href="categories.php?action=add" class="btn-admin btn-admin-primary">
                            <i class="fas fa-plus me-2"></i>Add New Category
                        </a>
                    </div>
                    <div class="admin-card-body">
                        <?php if (count($categories) > 0): ?>
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Category Name</th>
                                        <th>Workshop Count</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($category); ?></strong>
                                        </td>
                                        <td>
                                            <span class="admin-badge badge-published">
                                                <?php echo $category_counts[$category] ?? 0; ?> workshops
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="categories.php?action=edit&name=<?php echo urlencode($category); ?>" 
                                                   class="btn-action btn-edit">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <a href="categories.php?action=delete&name=<?php echo urlencode($category); ?>" 
                                                   class="btn-action btn-delete"
                                                   onclick="return confirm('Are you sure you want to delete this category?')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-tags"></i>
                                <h3>No Categories Found</h3>
                                <p>Get started by creating your first category.</p>
                                <a href="categories.php?action=add" class="btn-admin btn-admin-primary">
                                    Add New Category
                                </a>
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