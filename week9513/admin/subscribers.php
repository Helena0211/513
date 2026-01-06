<?php
session_start();
require_once '../config/config.php';

// 管理员认证
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// 获取当前文件名用于激活状态
$current_page = basename($_SERVER['PHP_SELF']);

// 数据库连接配置
$host = 'sql210.infinityfree.com';
$dbname = 'if0_40378146_wp579';
$username = 'if0_40378146';
$password = 'nQuyY3nfXVA';

// 获取当前操作
$action = $_GET['action'] ?? 'list';
$user_id = $_GET['id'] ?? null;
$success_msg = '';
$error_msg = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 处理删除操作
    if ($action == 'delete' && $user_id) {
        $stmt = $pdo->prepare("DELETE FROM wpej_fc_subscribers WHERE id = ?");
        $stmt->execute([$user_id]);
        $success_msg = 'Subscriber deleted successfully';
        header('Location: subscribers.php?success=' . urlencode($success_msg));
        exit();
    }
    
    // 处理确认状态切换
    if ($action == 'toggle_confirm' && $user_id) {
        $stmt = $pdo->prepare("SELECT is_confirmed FROM wpej_fc_subscribers WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $new_status = $user['is_confirmed'] ? 0 : 1;
            $updateStmt = $pdo->prepare("UPDATE wpej_fc_subscribers SET is_confirmed = ? WHERE id = ?");
            $updateStmt->execute([$new_status, $user_id]);
            
            $success_msg = 'Confirmation status updated';
            header('Location: subscribers.php?success=' . urlencode($success_msg));
            exit();
        }
    }
    
    // 获取特定用户用于编辑
    $current_user = null;
    if ($action == 'edit' && $user_id) {
        $stmt = $pdo->prepare("SELECT * FROM wpej_fc_subscribers WHERE id = ?");
        $stmt->execute([$user_id]);
        $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // 处理编辑表单提交
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_subscriber'])) {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $date_of_birth = trim($_POST['date_of_birth'] ?? '');
        $is_confirmed = isset($_POST['is_confirmed']) ? 1 : 0;
        
        if (!empty($first_name) && !empty($last_name) && !empty($email) && $user_id) {
            $stmt = $pdo->prepare("
                UPDATE wpej_fc_subscribers 
                SET first_name = ?, last_name = ?, email = ?, city = ?, state = ?, 
                    country = ?, phone = ?, date_of_birth = ?, is_confirmed = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $first_name, $last_name, $email, $city, $state, 
                $country, $phone, $date_of_birth, $is_confirmed, $user_id
            ]);
            
            $success_msg = 'Subscriber updated successfully';
            header('Location: subscribers.php?success=' . urlencode($success_msg));
            exit();
        } else {
            $error_msg = 'Please fill in all required fields';
        }
    }
    
    // 构建查询获取订阅者 - 按ID排序
    $search = $_GET['search'] ?? '';
    $status_filter = $_GET['status_filter'] ?? '';
    $country_filter = $_GET['country_filter'] ?? '';
    
    $query = "SELECT * FROM wpej_fc_subscribers WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR city LIKE ? OR phone LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if (!empty($status_filter)) {
        if ($status_filter === 'confirmed') {
            $query .= " AND is_confirmed = 1";
        } elseif ($status_filter === 'pending') {
            $query .= " AND is_confirmed = 0";
        }
    }
    
    if (!empty($country_filter)) {
        $query .= " AND country LIKE ?";
        $params[] = "%$country_filter%";
    }
    
    $query .= " ORDER BY id DESC"; // 按ID降序排序
    
    // 分页
    $perPage = 20;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($page - 1) * $perPage;
    
    // 获取总数
    $countQuery = "SELECT COUNT(*) as total FROM wpej_fc_subscribers WHERE 1=1" .
                  (!empty($search) ? " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR city LIKE ? OR phone LIKE ?)" : "") .
                  (!empty($status_filter) ? ($status_filter === 'confirmed' ? " AND is_confirmed = 1" : " AND is_confirmed = 0") : "") .
                  (!empty($country_filter) ? " AND country LIKE ?" : "");
    
    $countStmt = $pdo->prepare($countQuery);
    if (!empty($params)) {
        $countStmt->execute($params);
    } else {
        $countStmt->execute();
    }
    $totalSubscribers = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalSubscribers / $perPage);
    
    // 获取数据
    $query .= " LIMIT $perPage OFFSET $offset";
    $stmt = $pdo->prepare($query);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 获取所有国家
    $countryStmt = $pdo->query("SELECT DISTINCT country FROM wpej_fc_subscribers WHERE country IS NOT NULL AND country != '' ORDER BY country");
    $countries = $countryStmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $error_msg = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subscribers - SkillCraft Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
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
                    <?php echo $action == 'edit' ? 'Edit Subscriber' : 'Manage Subscribers'; ?>
                </h1>
                <p>Manage newsletter subscribers and user information</p>
            </header>

            <div class="admin-content">
                <?php if (isset($_GET['success'])): ?>
                <div style="background: #d1f7c4; color: #0e5b27; padding: 10px; border-radius: 3px; margin-bottom: 15px; border: 1px solid #b1e19a; font-size: 0.85rem;">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($error_msg)): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 3px; margin-bottom: 15px; border: 1px solid #f5c6cb; font-size: 0.85rem;">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error_msg); ?>
                </div>
                <?php endif; ?>

                <?php if ($action == 'edit' && $current_user): ?>
                <!-- 编辑用户表单 -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h2 class="admin-card-title">Edit Subscriber</h2>
                    </div>
                    <div class="admin-card-body">
                        <form method="post" class="admin-form">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div class="form-group">
                                    <label for="first_name">First Name *</label>
                                    <input type="text" id="first_name" name="first_name" class="form-control" required
                                           value="<?php echo htmlspecialchars($current_user['first_name']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="last_name">Last Name *</label>
                                    <input type="text" id="last_name" name="last_name" class="form-control" required
                                           value="<?php echo htmlspecialchars($current_user['last_name']); ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" class="form-control" required
                                       value="<?php echo htmlspecialchars($current_user['email']); ?>">
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div class="form-group">
                                    <label for="city">City</label>
                                    <input type="text" id="city" name="city" class="form-control"
                                           value="<?php echo htmlspecialchars($current_user['city']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="state">State/Province</label>
                                    <input type="text" id="state" name="state" class="form-control"
                                           value="<?php echo htmlspecialchars($current_user['state']); ?>">
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div class="form-group">
                                    <label for="country">Country</label>
                                    <input type="text" id="country" name="country" class="form-control"
                                           value="<?php echo htmlspecialchars($current_user['country']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="phone">Phone</label>
                                    <input type="text" id="phone" name="phone" class="form-control"
                                           value="<?php echo htmlspecialchars($current_user['phone']); ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="date_of_birth">Date of Birth</label>
                                <input type="date" id="date_of_birth" name="date_of_birth" class="form-control"
                                       value="<?php echo htmlspecialchars($current_user['date_of_birth']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label style="display: flex; align-items: center; gap: 8px;">
                                    <input type="checkbox" name="is_confirmed" value="1" 
                                           <?php echo $current_user['is_confirmed'] ? 'checked' : ''; ?>>
                                    <span>Confirmed Subscriber</span>
                                </label>
                            </div>
                            
                            <div style="display: flex; gap: 10px; margin-top: 20px;">
                                <button type="submit" name="update_subscriber" class="btn-admin btn-admin-primary">
                                    <i class="fas fa-save me-2"></i>Update Subscriber
                                </button>
                                <a href="subscribers.php" class="btn-admin btn-admin-outline">
                                    <i class="fas fa-arrow-left me-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <?php else: ?>
                <!-- 用户列表 -->
                <div class="admin-card">
                    <div class="admin-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <h2 class="admin-card-title" style="margin: 0;">All Subscribers</h2>
                        
                        <!-- 搜索和过滤表单 -->
                        <form method="GET" action="" class="search-form">
                            <input type="text" name="search" placeholder="Search..." 
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   class="search-input">
                            
                            <select name="status_filter" class="search-select">
                                <option value="">All Status</option>
                                <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            </select>
                            
                            <select name="country_filter" class="search-select">
                                <option value="">All Countries</option>
                                <?php foreach ($countries as $country): ?>
                                <option value="<?php echo htmlspecialchars($country); ?>" 
                                    <?php echo $country_filter == $country ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($country); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <button type="submit" class="btn-admin btn-admin-primary" style="padding: 6px 12px; font-size: 0.8rem;">
                                <i class="fas fa-search"></i>
                            </button>
                            
                            <a href="subscribers.php" class="btn-admin btn-admin-outline" style="padding: 6px 12px; font-size: 0.8rem;">
                                Reset
                            </a>
                        </form>
                    </div>
                    
                    <div class="admin-card-body" style="overflow-x: auto; padding: 10px;">
                        <?php if (count($subscribers) > 0): ?>
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Location</th>
                                        <th>Phone</th>
                                        <th>Status</th>
                                        <th>DOB</th>
                                        <th>Registered</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subscribers as $subscriber): ?>
                                    <tr>
                                        <td><?php echo $subscriber['id']; ?></td>
                                        <td class="tooltip-cell" data-tooltip="<?php echo htmlspecialchars($subscriber['first_name'] . ' ' . $subscriber['last_name']); ?>">
                                            <strong><?php echo htmlspecialchars(substr($subscriber['first_name'] . ' ' . $subscriber['last_name'], 0, 12)) . (strlen($subscriber['first_name'] . ' ' . $subscriber['last_name']) > 12 ? '...' : ''); ?></strong>
                                        </td>
                                        <td class="tooltip-cell" data-tooltip="<?php echo htmlspecialchars($subscriber['email']); ?>">
                                            <?php echo htmlspecialchars(substr($subscriber['email'], 0, 15)) . (strlen($subscriber['email']) > 15 ? '...' : ''); ?>
                                        </td>
                                       <!-- 替换原有的 Location 显示代码 -->
<td class="tooltip-cell" data-tooltip="<?php echo htmlspecialchars(($subscriber['city'] ? $subscriber['city'] . ', ' : '') . $subscriber['country']); ?>">
    <?php 
    $city = $subscriber['city'] ?? '';
    $country = $subscriber['country'] ?? '';
    $location = '';
    
    if ($city && $country) {
        $location = substr($city, 0, 8) . ', ' . substr($country, 0, 6);
    } elseif ($city) {
        $location = substr($city, 0, 12);
    } elseif ($country) {
        $location = substr($country, 0, 12);
    }
    
    echo htmlspecialchars($location ? $location : '-');
    ?>
</td>
                                        <td>
                                            <?php if ($subscriber['phone']): ?>
                                                <?php echo htmlspecialchars(substr($subscriber['phone'], 0, 10)) . (strlen($subscriber['phone']) > 10 ? '...' : ''); ?>
                                            <?php else: ?>
                                                <span style="color: #999;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $subscriber['is_confirmed'] ? 'status-confirmed' : 'status-pending'; ?>">
                                                <?php echo $subscriber['is_confirmed'] ? 'Yes' : 'No'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($subscriber['date_of_birth'] && $subscriber['date_of_birth'] != '0000-00-00'): ?>
                                                <?php echo date('m/d/y', strtotime($subscriber['date_of_birth'])); ?>
                                            <?php else: ?>
                                                <span style="color: #999;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo date('m/d/y', strtotime($subscriber['created_at'])); ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons-vertical">
                                                <button class="btn-vertical btn-edit-vertical" onclick="window.location.href='subscribers.php?action=edit&id=<?php echo $subscriber['id']; ?>'" title="Edit">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <button class="btn-vertical btn-toggle-vertical" onclick="if(confirm('<?php echo $subscriber['is_confirmed'] ? 'Mark as pending?' : 'Confirm this subscriber?'; ?>')) window.location.href='subscribers.php?action=toggle_confirm&id=<?php echo $subscriber['id']; ?>'" title="<?php echo $subscriber['is_confirmed'] ? 'Mark pending' : 'Confirm'; ?>">
                                                    <i class="fas fa-<?php echo $subscriber['is_confirmed'] ? 'clock' : 'check'; ?>"></i> <?php echo $subscriber['is_confirmed'] ? 'Pending' : 'Confirm'; ?>
                                                </button>
                                                <button class="btn-vertical btn-delete-vertical" onclick="if(confirm('Delete this subscriber? This action cannot be undone.')) window.location.href='subscribers.php?action=delete&id=<?php echo $subscriber['id']; ?>'" title="Delete">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <!-- 分页 -->
                            <?php if ($totalPages > 1): ?>
                                <div class="pagination-compact">
                                    <?php if ($page > 1): ?>
                                    <a href="subscribers.php?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status_filter=' . urlencode($status_filter) : ''; ?><?php echo !empty($country_filter) ? '&country_filter=' . urlencode($country_filter) : ''; ?>"
                                       class="page-btn">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                        <a href="subscribers.php?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status_filter=' . urlencode($status_filter) : ''; ?><?php echo !empty($country_filter) ? '&country_filter=' . urlencode($country_filter) : ''; ?>"
                                           class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                        <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                        <span style="padding: 4px 8px; color: #999;">...</span>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                    <a href="subscribers.php?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status_filter=' . urlencode($status_filter) : ''; ?><?php echo !empty($country_filter) ? '&country_filter=' . urlencode($country_filter) : ''; ?>"
                                       class="page-btn">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div style="text-align: center; margin-top: 10px; color: #666; font-size: 0.8rem;">
                                Showing <?php echo count($subscribers); ?> of <?php echo $totalSubscribers; ?> subscribers
                            </div>
                            
                        <?php else: ?>
                           <div class="empty-state" style="text-align: center; padding: 40px; color: #999;">
                               <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 15px;"></i>
                               <h3>No Subscribers Found</h3>
                               <p style="font-size: 0.9rem;">
                                   <?php echo !empty($search) || !empty($status_filter) || !empty($country_filter) ? 'Try adjusting your search or filters.' : 'There are no subscribers yet.'; ?>
                               </p>
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