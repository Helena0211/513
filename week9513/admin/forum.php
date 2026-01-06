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
$post_id = $_GET['id'] ?? null;
$category_action = $_GET['cat_action'] ?? '';
$category_name = $_GET['cat_name'] ?? '';
$success_msg = '';
$error_msg = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 处理分类管理
    if ($category_action == 'delete_category' && $category_name) {
        // 检查是否有帖子使用该分类
        $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM wpej_forum_posts WHERE category = ?");
        $checkStmt->execute([$category_name]);
        $categoryUsage = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($categoryUsage > 0) {
            $error_msg = "Cannot delete category: There are $categoryUsage posts using this category.";
        } else {
            $success_msg = "Category '$category_name' deleted successfully.";
        }
    } elseif ($category_action == 'add_category' && isset($_POST['new_category'])) {
        $new_category = trim($_POST['new_category']);
        if (!empty($new_category)) {
            $success_msg = "Category '$new_category' added successfully.";
        }
    }
    
    // 处理删除帖子操作
    if ($action == 'delete' && $post_id) {
        $stmt = $pdo->prepare("UPDATE wpej_forum_posts SET status = 'deleted' WHERE id = ?");
        $stmt->execute([$post_id]);
        $success_msg = 'Forum post deleted successfully';
        header('Location: forum.php?success=' . urlencode($success_msg));
        exit();
    }
    
    // 处理更新状态
    if ($action == 'update_status' && $post_id && isset($_GET['status'])) {
        $status = $_GET['status'];
        $valid_statuses = ['active', 'pending', 'deleted'];
        
        if (in_array($status, $valid_statuses)) {
            $stmt = $pdo->prepare("UPDATE wpej_forum_posts SET status = ? WHERE id = ?");
            $stmt->execute([$status, $post_id]);
            $success_msg = 'Status updated successfully';
            header('Location: forum.php?success=' . urlencode($success_msg));
            exit();
        }
    }
    
    // 获取特定帖子用于编辑
    $current_post = null;
    if ($action == 'edit' && $post_id) {
        $stmt = $pdo->prepare("
            SELECT fp.*, 
                   CONCAT(fs.first_name, ' ', fs.last_name) as author_name,
                   fs.email as author_email
            FROM wpej_forum_posts fp
            LEFT JOIN wpej_fc_subscribers fs ON fp.user_id = fs.id
            WHERE fp.id = ?
        ");
        $stmt->execute([$post_id]);
        $current_post = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // 处理编辑表单提交
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_post'])) {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $category = trim($_POST['category'] ?? 'General');
        $status = trim($_POST['status'] ?? 'active');
        
        if (!empty($title) && !empty($content) && $post_id) {
            $stmt = $pdo->prepare("
                UPDATE wpej_forum_posts 
                SET title = ?, content = ?, category = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([$title, $content, $category, $status, $post_id]);
            
            $success_msg = 'Forum post updated successfully';
            header('Location: forum.php?success=' . urlencode($success_msg));
            exit();
        } else {
            $error_msg = 'Please fill in all required fields';
        }
    }
    
    // 构建查询获取论坛帖子 - 按ID排序
    $search = $_GET['search'] ?? '';
    $status_filter = $_GET['status_filter'] ?? '';
    $category_filter = $_GET['category_filter'] ?? '';
    
    $query = "
        SELECT fp.*, 
               CONCAT(fs.first_name, ' ', fs.last_name) as author_name,
               fs.email as author_email
        FROM wpej_forum_posts fp
        LEFT JOIN wpej_fc_subscribers fs ON fp.user_id = fs.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (fp.title LIKE ? OR fp.content LIKE ? OR fs.first_name LIKE ? OR fs.last_name LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if (!empty($status_filter)) {
        $query .= " AND fp.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($category_filter)) {
        $query .= " AND fp.category = ?";
        $params[] = $category_filter;
    }
    
    $query .= " ORDER BY fp.id DESC"; // 按ID降序排序
    
    // 分页
    $perPage = 20;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($page - 1) * $perPage;
    
    // 获取总数
    $countQuery = "SELECT COUNT(*) as total FROM wpej_forum_posts fp
                  LEFT JOIN wpej_fc_subscribers fs ON fp.user_id = fs.id
                  WHERE 1=1" .
                  (!empty($search) ? " AND (fp.title LIKE ? OR fp.content LIKE ? OR fs.first_name LIKE ? OR fs.last_name LIKE ?)" : "") .
                  (!empty($status_filter) ? " AND fp.status = ?" : "") .
                  (!empty($category_filter) ? " AND fp.category = ?" : "");
    
    $countStmt = $pdo->prepare($countQuery);
    if (!empty($params)) {
        $countStmt->execute($params);
    } else {
        $countStmt->execute();
    }
    $totalPosts = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalPosts / $perPage);
    
    // 获取数据
    $query .= " LIMIT $perPage OFFSET $offset";
    $stmt = $pdo->prepare($query);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $forum_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 获取所有分类
    $categoryStmt = $pdo->query("SELECT DISTINCT category FROM wpej_forum_posts WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $error_msg = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Forum - SkillCraft Admin</title>
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
                <h1>
                    <?php echo $action == 'edit' ? 'Edit Forum Post' : 'Manage Forum Posts'; ?>
                </h1>
                <p>Manage forum discussions and user posts</p>
            </header>

            <div class="admin-content">
                <?php if (isset($_GET['success'])): ?>
                <div style="background: #d1f7c4; color: #0e5b27; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #b1e19a;">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($error_msg)): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error_msg); ?>
                </div>
                <?php endif; ?>

                <!-- 分类管理部分 -->
                <?php if ($action != 'edit'): ?>
                <div class="category-manage-section">
                    <h3 style="margin-bottom: 15px; color: #495057;">Manage Forum Categories</h3>
                    <div class="category-list">
                        <?php foreach ($categories as $category): ?>
                        <div class="category-badge">
                            <span><?php echo htmlspecialchars($category); ?></span>
                            <form method="GET" action="" style="display: inline;">
                                <input type="hidden" name="cat_action" value="delete_category">
                                <input type="hidden" name="cat_name" value="<?php echo urlencode($category); ?>">
                                <button type="submit" class="delete-btn" onclick="return confirm('Delete category <?php echo htmlspecialchars($category); ?>?')">
                                    <i class="fas fa-times"></i>
                                </button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <form method="POST" action="?cat_action=add_category" class="add-category-form">
                        <input type="text" name="new_category" placeholder="New category name" required>
                        <button type="submit" class="btn-admin btn-admin-primary" style="padding: 8px 16px;">
                            <i class="fas fa-plus me-2"></i>Add Category
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <?php if ($action == 'edit' && $current_post): ?>
                <!-- 编辑帖子表单 -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h2 class="admin-card-title">Edit Forum Post</h2>
                    </div>
                    <div class="admin-card-body">
                        <form method="post" class="admin-form">
                            <div class="form-group">
                                <label>Author</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($current_post['author_name'] . ' (' . $current_post['author_email'] . ')'); ?>" disabled>
                            </div>
                            
                            <div class="form-group">
                                <label for="title">Title *</label>
                                <input type="text" id="title" name="title" class="form-control" required
                                       value="<?php echo htmlspecialchars($current_post['title']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="category">Category</label>
                                <select id="category" name="category" class="form-control">
                                    <option value="">Select category</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>" 
                                        <?php echo $current_post['category'] == $category ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="content">Content *</label>
                                <textarea id="content" name="content" class="form-control" rows="8" required><?php echo htmlspecialchars($current_post['content']); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="active" <?php echo $current_post['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="pending" <?php echo $current_post['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="deleted" <?php echo $current_post['status'] == 'deleted' ? 'selected' : ''; ?>>Deleted</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Statistics</label>
                                <div style="display: flex; gap: 20px; margin-top: 10px;">
                                    <div class="stats-text">
                                        <strong>Views:</strong> <?php echo $current_post['views']; ?>
                                    </div>
                                    <div class="stats-text">
                                        <strong>Replies:</strong> <?php echo $current_post['replies']; ?>
                                    </div>
                                    <div class="stats-text">
                                        <strong>Likes:</strong> <?php echo $current_post['likes']; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 15px; margin-top: 30px;">
                                <button type="submit" name="update_post" class="btn-admin btn-admin-primary">
                                    <i class="fas fa-save me-2"></i>Update Post
                                </button>
                                <a href="forum.php" class="btn-admin btn-admin-outline">
                                    <i class="fas fa-arrow-left me-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <?php else: ?>
                <!-- 论坛帖子列表 -->
                <div class="admin-card">
                    <div class="admin-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <h2 class="admin-card-title" style="margin: 0;">All Forum Posts</h2>
                        
                        <!-- 搜索和过滤表单 -->
                        <form method="GET" action="" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <input type="text" name="search" placeholder="Search posts..." 
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   style="padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 4px; min-width: 200px;">
                            
                            <select name="status_filter" style="padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 4px;">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="deleted" <?php echo $status_filter == 'deleted' ? 'selected' : ''; ?>>Deleted</option>
                            </select>
                            
                            <select name="category_filter" style="padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 4px;">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>" 
                                    <?php echo $category_filter == $category ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <button type="submit" class="btn-admin btn-admin-primary" style="padding: 8px 16px;">
                                <i class="fas fa-search"></i>
                            </button>
                            
                            <a href="forum.php" class="btn-admin btn-admin-outline" style="padding: 8px 16px;">
                                Reset
                            </a>
                        </form>
                    </div>
                    
<div class="admin-card-body" style="overflow-x: auto;">
    <?php if (count($forum_posts) > 0): ?>
        <table class="admin-table" style="width: 100%; font-size: 0.9rem;">
            <thead>
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th style="min-width: 200px;">Title</th>
                    <th style="width: 120px;">Author</th>
                    <th style="width: 100px;">Category</th>
                    <th style="width: 80px;">Status</th>
                    <th style="width: 130px;">Stats</th>
                    <th style="width: 90px;">Date</th>
                    <th style="width: 220px;">Actions</th>   <!-- 仅这里加宽 -->
                </tr>
            </thead>
            <tbody>
                <?php foreach ($forum_posts as $post): ?>
                <tr>
                    <td><?php echo $post['id']; ?></td>
                    <td style="white-space: normal; word-wrap: break-word;">
                        <strong title="<?php echo htmlspecialchars($post['title']); ?>">
                            <?php echo htmlspecialchars(substr($post['title'], 0, 40)); ?><?php echo strlen($post['title']) > 40 ? '...' : ''; ?>
                        </strong>
                    </td>
                    <td style="overflow: hidden; text-overflow: ellipsis;">
                        <?php if ($post['author_name']): ?>
                        <?php echo htmlspecialchars($post['author_name']); ?>
                        <?php else: ?>
                        <span class="text-muted">Unknown</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="admin-badge" style="background: #e0f2fe; color: #0369a1; display: inline-block; padding: 3px 6px; border-radius: 3px; font-size: 0.75rem;">
                            <?php echo htmlspecialchars($post['category']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($post['status'] == 'active'): ?>
                        <span class="admin-badge badge-published" style="display: inline-block; padding: 3px 6px; border-radius: 3px; font-size: 0.75rem;">Active</span>
                        <?php elseif ($post['status'] == 'pending'): ?>
                        <span class="admin-badge badge-pending" style="display: inline-block; padding: 3px 6px; border-radius: 3px; font-size: 0.75rem;">Pending</span>
                        <?php else: ?>
                        <span class="admin-badge" style="background: #fee2e2; color: #dc2626; display: inline-block; padding: 3px 6px; border-radius: 3px; font-size: 0.75rem;">Deleted</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="stats-text" style="font-size: 0.75rem;">
                            <div>Views: <?php echo $post['views']; ?></div>
                            <div>Replies: <?php echo $post['replies']; ?></div>
                            <div>Likes: <?php echo $post['likes']; ?></div>
                        </div>
                    </td>
                    <td>
                        <?php echo date('M d, Y', strtotime($post['created_at'])); ?>
                    </td>
                    <td>
                        <div class="action-buttons" style="display: flex; gap: 4px; flex-wrap: wrap;">
                            <a href="forum.php?action=edit&id=<?php echo $post['id']; ?>" 
                               class="btn-action btn-edit" style="padding: 4px 8px; font-size: 0.8rem;">
                                <i class="fas fa-edit me-1"></i>Edit
                            </a>
                            
                            <?php if ($post['status'] != 'active'): ?>
                            <a href="forum.php?action=update_status&id=<?php echo $post['id']; ?>&status=active" 
                               class="btn-action" style="color: #00a32a; border-color: #00a32a; padding: 4px 8px; font-size: 0.8rem;" 
                               onclick="return confirm('Activate this post?')">
                                <i class="fas fa-check me-1"></i>Activate
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($post['status'] != 'pending'): ?>
                            <a href="forum.php?action=update_status&id=<?php echo $post['id']; ?>&status=pending" 
                               class="btn-action" style="color: #dba617; border-color: #dba617; padding: 4px 8px; font-size: 0.8rem;" 
                               onclick="return confirm('Mark as pending?')">
                                <i class="fas fa-clock me-1"></i>Pending
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($post['status'] != 'deleted'): ?>
                            <a href="forum.php?action=delete&id=<?php echo $post['id']; ?>" 
                               class="btn-action btn-delete" style="padding: 4px 8px; font-size: 0.8rem;"
                               onclick="return confirm('Delete this post? This action cannot be undone.')">
                                <i class="fas fa-trash me-1"></i>Delete
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
                            
                            <!-- 分页 -->
                            <?php if ($totalPages > 1): ?>
                            <div style="display: flex; justify-content: center; margin-top: 20px; gap: 5px;">
                                <?php if ($page > 1): ?>
                                <a href="forum.php?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status_filter=' . urlencode($status_filter) : ''; ?><?php echo !empty($category_filter) ? '&category_filter=' . urlencode($category_filter) : ''; ?>"
                                   class="btn-admin btn-admin-outline" style="padding: 6px 12px;">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                    <a href="forum.php?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status_filter=' . urlencode($status_filter) : ''; ?><?php echo !empty($category_filter) ? '&category_filter=' . urlencode($category_filter) : ''; ?>"
                                       class="btn-admin <?php echo $i == $page ? 'btn-admin-primary' : 'btn-admin-outline'; ?>" 
                                       style="padding: 6px 12px;">
                                        <?php echo $i; ?>
                                    </a>
                                    <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                    <span style="padding: 6px 12px;">...</span>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                <a href="forum.php?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status_filter=' . urlencode($status_filter) : ''; ?><?php echo !empty($category_filter) ? '&category_filter=' . urlencode($category_filter) : ''; ?>"
                                   class="btn-admin btn-admin-outline" style="padding: 6px 12px;">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div style="text-align: center; margin-top: 15px; color: #666;">
                                Showing <?php echo count($forum_posts); ?> of <?php echo $totalPosts; ?> posts
                            </div>
                            
                        <?php else: ?>
                             <div class="empty-state">
                              <i class="fas fa-comments"></i>
                               <h3>No Forum Posts Found</h3>
                                   <p><?php echo !empty($search) || !empty($status_filter) || !empty($category_filter) ? 'Try adjusting your search or filters.' : 'There are no forum posts yet.'; ?></p>
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