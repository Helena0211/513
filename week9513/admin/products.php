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

// 产品数据文件路径
$products_file = '../data/products.json';
// 图片上传目录
$upload_dir = '../uploads/workshops/';

// 确保上传目录存在
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// 加载产品数据
$products_data = [];
if (file_exists($products_file)) {
    $products_data = json_decode(file_get_contents($products_file), true);
}
$products = $products_data['products'] ?? [];

$action = $_GET['action'] ?? 'list';
$product_id = $_GET['id'] ?? null;

// 处理图片上传
function uploadImage($file, $product_id) {
    global $upload_dir;
    
    if ($file['error'] == UPLOAD_ERR_NO_FILE) {
        return null; // 没有上传文件
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    // 检查文件类型
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $file_type = mime_content_type($file['tmp_name']);
    
    if (!in_array($file_type, $allowed_types)) {
        return false;
    }
    
    // 生成唯一文件名
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $file_name = 'workshop_' . $product_id . '_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . $file_name;
    
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        return 'uploads/workshops/' . $file_name;
    }
    
    return false;
}

// 处理产品操作
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_product'])) {
        // 添加新产品
        $new_product = [
            'id' => count($products) + 1,
            'name' => trim($_POST['name']),
            'description' => trim($_POST['description']),
            'price' => floatval($_POST['price']),
            'discount_percent' => floatval($_POST['discount_percent']),
            'category' => trim($_POST['category']),
            'instructor' => trim($_POST['instructor']),
            'duration' => trim($_POST['duration']),
            'max_participants' => intval($_POST['max_participants']),
            'skill_level' => trim($_POST['skill_level']),
            'picture' => '', // 初始为空
            'supplier' => trim($_POST['supplier']),
            'custom_field1' => trim($_POST['custom_field1']),
            'custom_field2' => trim($_POST['custom_field2'])
        ];
        
        // 处理图片上传
        if (isset($_FILES['picture']) && $_FILES['picture']['error'] == UPLOAD_ERR_OK) {
            $picture_path = uploadImage($_FILES['picture'], $new_product['id']);
            if ($picture_path) {
                $new_product['picture'] = $picture_path;
            }
        }
        
        $products[] = $new_product;
        $products_data['products'] = $products;
        
        if (file_put_contents($products_file, json_encode($products_data, JSON_PRETTY_PRINT))) {
            header('Location: products.php?success=Product added successfully');
            exit();
        } else {
            $error = 'Failed to save product';
        }
    }
    
    if (isset($_POST['update_product'])) {
        // 更新现有产品
        foreach ($products as &$product) {
            if ($product['id'] == $product_id) {
                $product['name'] = trim($_POST['name']);
                $product['description'] = trim($_POST['description']);
                $product['price'] = floatval($_POST['price']);
                $product['discount_percent'] = floatval($_POST['discount_percent']);
                $product['category'] = trim($_POST['category']);
                $product['instructor'] = trim($_POST['instructor']);
                $product['duration'] = trim($_POST['duration']);
                $product['max_participants'] = intval($_POST['max_participants']);
                $product['skill_level'] = trim($_POST['skill_level']);
                $product['supplier'] = trim($_POST['supplier']);
                $product['custom_field1'] = trim($_POST['custom_field1']);
                $product['custom_field2'] = trim($_POST['custom_field2']);
                
                // 只在上传了新图片时更新
                if (isset($_FILES['picture']) && $_FILES['picture']['error'] == UPLOAD_ERR_OK) {
                    $picture_path = uploadImage($_FILES['picture'], $product_id);
                    if ($picture_path) {
                        $product['picture'] = $picture_path;
                    }
                }
                break;
            }
        }
        
        $products_data['products'] = $products;
        
        if (file_put_contents($products_file, json_encode($products_data, JSON_PRETTY_PRINT))) {
            header('Location: products.php?success=Product updated successfully');
            exit();
        } else {
            $error = 'Failed to update product';
        }
    }
}

// 删除产品
if ($action == 'delete' && $product_id) {
    $products = array_filter($products, function($product) use ($product_id) {
        return $product['id'] != $product_id;
    });
    
    $products_data['products'] = array_values($products);
    
    if (file_put_contents($products_file, json_encode($products_data, JSON_PRETTY_PRINT))) {
        header('Location: products.php?success=Product deleted successfully');
        exit();
    } else {
        $error = 'Failed to delete product';
    }
}

// 获取特定产品用于编辑
$current_product = null;
if ($action == 'edit' && $product_id) {
    foreach ($products as $product) {
        if ($product['id'] == $product_id) {
            $current_product = $product;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Workshops - SkillCraft Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        /* 简约按钮样式 */
        .simple-btn {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            font-size: 12px;
            border-radius: 3px;
            text-decoration: none;
            border: 1px solid;
            background: transparent;
            transition: all 0.2s;
            white-space: nowrap;
        }
        
        .simple-btn i {
            font-size: 11px;
            margin-right: 4px;
        }
        
        .simple-btn:hover {
            background: rgba(0,0,0,0.03);
        }
        
        /* 编辑按钮 */
        .btn-edit {
            color: #0073aa;
            border-color: #0073aa;
        }
        
        .btn-edit:hover {
            background: rgba(0, 115, 170, 0.05);
        }
        
        /* 删除按钮 */
        .btn-delete {
            color: #dc3232;
            border-color: #dc3232;
        }
        
        .btn-delete:hover {
            background: rgba(220, 50, 50, 0.05);
        }
        
        /* 主按钮样式 */
        .main-btn {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            font-size: 14px;
            border-radius: 3px;
            text-decoration: none;
            border: 1px solid #0073aa;
            background: #0073aa;
            color: white;
            transition: all 0.2s;
            white-space: nowrap;
        }
        
        .main-btn:hover {
            background: #005a87;
            border-color: #005a87;
        }
        
        .main-btn i {
            font-size: 13px;
            margin-right: 6px;
        }
        
        /* 表格容器 */
        .table-container {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            background: white;
        }
        
        /* 紧凑表格 */
        .compact-table {
            width: 100%;
            min-width: 650px;
            border-collapse: collapse;
            font-size: 13px;
            table-layout: fixed;
        }
        
        .compact-table th {
            background: #f6f7f7;
            padding: 10px 8px;
            border-bottom: 2px solid #c3c4c7;
            font-weight: 600;
            text-align: left;
            color: #2c3338;
        }
        
        .compact-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
            color: #50575e;
        }
        
        .compact-table tr:hover td {
            background-color: #f6f7f7;
        }
        
        /* 徽章样式 */
        .discount-badge {
            display: inline-block;
            background: #d1f7c4;
            color: #0e5b27;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .text-muted {
            color: #6c757d;
            font-size: 11px;
        }
        
        /* 图片上传样式 */
        .image-upload-area {
            border: 2px dashed #ddd;
            border-radius: 4px;
            padding: 15px;
            text-align: center;
            background: #f9f9f9;
            margin-top: 5px;
            cursor: pointer;
        }
        
        .image-upload-area:hover {
            border-color: #0073aa;
            background: #f0f7ff;
        }
        
        .image-preview {
            max-width: 150px;
            max-height: 150px;
            margin-bottom: 10px;
            border-radius: 3px;
        }
        
        .current-image {
            margin-top: 10px;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 3px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <!-- 侧边栏 -->
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
                <h1>
                    <?php echo $action == 'add' ? 'Add New Workshop' : ($action == 'edit' ? 'Edit Workshop' : 'Manage Workshops'); ?>
                </h1>
                <p>
                    <?php echo $action == 'add' ? 'Create a new workshop' : ($action == 'edit' ? 'Update workshop details' : 'Manage all workshops'); ?>
                </p>
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
                <!-- 产品表单 -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h2 class="admin-card-title">
                            <?php echo $action == 'add' ? 'Add New Workshop' : 'Edit Workshop'; ?>
                        </h2>
                    </div>
                    <div class="admin-card-body">
                        <!-- 修改表单：添加enctype属性支持文件上传 -->
                        <form method="post" class="admin-form" enctype="multipart/form-data">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div>
                                    <div class="form-group">
                                        <label for="name">Workshop Name *</label>
                                        <input type="text" id="name" name="name" class="form-control" required
                                               value="<?php echo htmlspecialchars($current_product['name'] ?? ''); ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="price">Price ($) *</label>
                                        <input type="number" id="price" name="price" class="form-control" step="0.01" required
                                               value="<?php echo $current_product['price'] ?? ''; ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="discount_percent">Discount Percent (%)</label>
                                        <input type="number" id="discount_percent" name="discount_percent" class="form-control" step="0.01"
                                               value="<?php echo $current_product['discount_percent'] ?? 0; ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="category">Category *</label>
                                        <input type="text" id="category" name="category" class="form-control" required
                                               value="<?php echo htmlspecialchars($current_product['category'] ?? ''); ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="instructor">Instructor *</label>
                                        <input type="text" id="instructor" name="instructor" class="form-control" required
                                               value="<?php echo htmlspecialchars($current_product['instructor'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div>
                                    <div class="form-group">
                                        <label for="duration">Duration</label>
                                        <input type="text" id="duration" name="duration" class="form-control"
                                               value="<?php echo htmlspecialchars($current_product['duration'] ?? '2 hours'); ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="max_participants">Max Participants</label>
                                        <input type="number" id="max_participants" name="max_participants" class="form-control"
                                               value="<?php echo $current_product['max_participants'] ?? 12; ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="skill_level">Skill Level</label>
                                        <select id="skill_level" name="skill_level" class="form-control">
                                            <option value="Beginner" <?php echo ($current_product['skill_level'] ?? '') == 'Beginner' ? 'selected' : ''; ?>>Beginner</option>
                                            <option value="Intermediate" <?php echo ($current_product['skill_level'] ?? '') == 'Intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                            <option value="Advanced" <?php echo ($current_product['skill_level'] ?? '') == 'Advanced' ? 'selected' : ''; ?>>Advanced</option>
                                        </select>
                                    </div>

                                    <!-- 修改：将原来的图片URL字段改为文件上传 -->
                                    <div class="form-group">
                                        <label for="picture">Workshop Image</label>
                                        <?php if ($action == 'edit' && !empty($current_product['picture'])): ?>
                                            <div class="current-image">
                                                <p style="margin: 0 0 5px 0; font-weight: 500;">Current Image:</p>
                                                <?php if (file_exists('../' . $current_product['picture'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($current_product['picture']); ?>" 
                                                         alt="Current Workshop Image" 
                                                         style="max-width: 150px; max-height: 100px; border-radius: 3px; margin-bottom: 5px;">
                                                <?php endif; ?>
                                                <p style="margin: 0; font-size: 11px; color: #666;">
                                                    Upload new image only if you want to change it.
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="image-upload-area" onclick="document.getElementById('picture').click()">
                                            <div style="font-size: 36px; color: #999; margin-bottom: 8px;">
                                                <i class="fas fa-cloud-upload-alt"></i>
                                            </div>
                                            <p style="margin: 0; color: #666;">
                                                Click to <?php echo ($action == 'edit' && !empty($current_product['picture'])) ? 'change' : 'upload'; ?> image
                                            </p>
                                            <p style="margin: 5px 0 0 0; font-size: 11px; color: #888;">
                                                JPG, PNG, GIF, WebP (Max: 2MB)
                                            </p>
                                        </div>
                                        <input type="file" id="picture" name="picture" accept="image/*" 
                                               style="display: none;" onchange="previewImage(this)">
                                        <small class="text-muted">Leave empty to keep current image</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="supplier">Supplier</label>
                                        <input type="text" id="supplier" name="supplier" class="form-control"
                                               value="<?php echo htmlspecialchars($current_product['supplier'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="description">Description *</label>
                                <textarea id="description" name="description" class="form-control" rows="4" required><?php echo htmlspecialchars($current_product['description'] ?? ''); ?></textarea>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label for="custom_field1">Custom Field 1</label>
                                    <input type="text" id="custom_field1" name="custom_field1" class="form-control"
                                           value="<?php echo htmlspecialchars($current_product['custom_field1'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="custom_field2">Custom Field 2</label>
                                    <input type="text" id="custom_field2" name="custom_field2" class="form-control"
                                           value="<?php echo htmlspecialchars($current_product['custom_field2'] ?? ''); ?>">
                                </div>
                            </div>

                            <div style="display: flex; gap: 15px; margin-top: 30px;">
                                <?php if ($action == 'add'): ?>
                                <button type="submit" name="add_product" class="btn-admin btn-admin-primary">
                                    <i class="fas fa-plus me-2"></i>Add Workshop
                                </button>
                                <?php else: ?>
                                <button type="submit" name="update_product" class="btn-admin btn-admin-primary">
                                    <i class="fas fa-save me-2"></i>Update Workshop
                                </button>
                                <?php endif; ?>
                                <a href="products.php" class="btn-admin btn-admin-outline">
                                    <i class="fas fa-arrow-left me-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <?php else: ?>
                <!-- 产品列表（保持原样不变） -->
                <div class="admin-card">
                    <div class="admin-card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h2 class="admin-card-title">All Workshops</h2>
                        <a href="products.php?action=add" class="main-btn">
                            <i class="fas fa-plus"></i> Add New Workshop
                        </a>
                    </div>
                    <div class="admin-card-body" style="padding: 0;">
                        <div class="table-container">
                            <?php if (count($products) > 0): ?>
                                <table class="compact-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 5%;">ID</th>
                                            <th style="width: 25%;">Name</th>
                                            <th style="width: 12%;">Category</th>
                                            <th style="width: 8%;">Price</th>
                                            <th style="width: 10%;">Discount</th>
                                            <th style="width: 20%;">Instructor</th>
                                            <th style="width: 20%;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td style="text-align: center;"><?php echo $product['id']; ?></td>
                                            <td>
                                                <div style="font-weight: 500; color: #2271b1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                    <?php echo htmlspecialchars($product['name']); ?>
                                                </div>
                                                <?php if (!empty($product['picture'])): ?>
                                                <div style="font-size: 11px; color: #787c82;">Has image</div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($product['category']); ?></td>
                                            <td style="font-weight: 500;">$<?php echo number_format($product['price'], 2); ?></td>
                                            <td>
                                                <?php if ($product['discount_percent'] > 0): ?>
                                                    <span class="discount-badge"><?php echo $product['discount_percent']; ?>% OFF</span>
                                                <?php else: ?>
                                                    <span style="color: #787c82;">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                <?php echo htmlspecialchars($product['instructor']); ?>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 5px; flex-wrap: nowrap;">
                                                    <a href="products.php?action=edit&id=<?php echo $product['id']; ?>" 
                                                       class="simple-btn btn-edit">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                    <a href="products.php?action=delete&id=<?php echo $product['id']; ?>" 
                                                       class="simple-btn btn-delete"
                                                       onclick="return confirm('Are you sure you want to delete this workshop?')">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div style="text-align: center; padding: 40px;">
                                    <div style="font-size: 48px; color: #c3c4c7; margin-bottom: 20px;">
                                        <i class="fas fa-graduation-cap"></i>
                                    </div>
                                    <h3 style="color: #1d2327; margin-bottom: 10px;">No Workshops Found</h3>
                                    <p style="color: #646970; margin-bottom: 20px;">Get started by creating your first workshop.</p>
                                    <a href="products.php?action=add" class="main-btn">
                                        <i class="fas fa-plus"></i> Add New Workshop
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="assets/js/admin.js"></script>
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    // 更新上传区域显示预览
                    var uploadArea = input.previousElementSibling;
                    uploadArea.innerHTML = '<img src="' + e.target.result + '" class="image-preview"><p style="margin: 0; color: #666;">Click to change image</p><p style="margin: 5px 0 0 0; font-size: 11px; color: #888;">JPG, PNG, GIF, WebP (Max: 2MB)</p>';
                    
                    // 重新绑定点击事件
                    uploadArea.onclick = function() {
                        document.getElementById('picture').click();
                    };
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>