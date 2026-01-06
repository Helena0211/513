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

// Database connection
global $db;

// Get current action
$action = $_GET['action'] ?? 'list';
$application_id = $_GET['id'] ?? null;
$success_msg = '';
$error_msg = '';

try {
    // Handle delete application
    if ($action == 'delete' && $application_id) {
        $stmt = $db->prepare("DELETE FROM wpej_recruitment WHERE id = ?");
        $stmt->execute([$application_id]);
        $success_msg = 'Application deleted successfully';
        header('Location: recruitment.php?success=' . urlencode($success_msg));
        exit();
    }
    
    // Handle update application status - 首先检查表中是否有status列
    if ($action == 'update_status' && $application_id && isset($_GET['status'])) {
        $status = $_GET['status'];
        $valid_statuses = ['pending', 'reviewed', 'shortlisted', 'interviewed', 'rejected', 'hired'];
        
        if (in_array($status, $valid_statuses)) {
            // 首先检查表结构
            $checkColumn = $db->query("SHOW COLUMNS FROM wpej_recruitment LIKE 'status'");
            if ($checkColumn->rowCount() > 0) {
                $stmt = $db->prepare("UPDATE wpej_recruitment SET status = ? WHERE id = ?");
                $stmt->execute([$status, $application_id]);
                $success_msg = 'Application status updated successfully';
            } else {
                // 如果没有status列，跳过更新
                $success_msg = 'Status column not found in table';
            }
            header('Location: recruitment.php?success=' . urlencode($success_msg));
            exit();
        }
    }
    
    // Handle edit application
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_application'])) {
        $application_id = $_POST['application_id'] ?? null;
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $cover_message = trim($_POST['cover_message'] ?? '');
        $status = $_POST['status'] ?? 'pending';
        
        if ($application_id && !empty($full_name) && !empty($email) && !empty($position)) {
            // 检查是否有status列
            $checkColumn = $db->query("SHOW COLUMNS FROM wpej_recruitment LIKE 'status'");
            $has_status_column = $checkColumn->rowCount() > 0;
            
            if ($has_status_column) {
                $stmt = $db->prepare("UPDATE wpej_recruitment SET full_name = ?, email = ?, phone = ?, position = ?, cover_message = ?, status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$full_name, $email, $phone, $position, $cover_message, $status, $application_id]);
            } else {
                $stmt = $db->prepare("UPDATE wpej_recruitment SET full_name = ?, email = ?, phone = ?, position = ?, cover_message = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$full_name, $email, $phone, $position, $cover_message, $application_id]);
            }
            
            $success_msg = 'Application updated successfully';
            header('Location: recruitment.php?action=view&id=' . $application_id . '&success=' . urlencode($success_msg));
            exit();
        } else {
            $error_msg = 'Please fill in all required fields';
        }
    }
    
    // Get specific application for view/edit
    $current_application = null;
    if (($action == 'view' || $action == 'edit') && $application_id) {
        $stmt = $db->prepare("SELECT * FROM wpej_recruitment WHERE id = ?");
        $stmt->execute([$application_id]);
        $current_application = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($current_application) {
            $current_application['file_names'] = json_decode($current_application['file_names'] ?? '[]', true);
        }
    }
    
    // Build query to get applications
    $search = $_GET['search'] ?? '';
    $status_filter = $_GET['status_filter'] ?? '';
    $position_filter = $_GET['position_filter'] ?? '';
    
    // 首先检查表中是否有status列
    $has_status_column = false;
    try {
        $checkColumn = $db->query("SHOW COLUMNS FROM wpej_recruitment LIKE 'status'");
        $has_status_column = $checkColumn->rowCount() > 0;
    } catch (Exception $e) {
        $has_status_column = false;
    }
    
    $query = "SELECT * FROM wpej_recruitment WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ? OR cover_message LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if ($has_status_column && !empty($status_filter)) {
        $query .= " AND status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($position_filter)) {
        $query .= " AND position LIKE ?";
        $params[] = "%$position_filter%";
    }
    
    $query .= " ORDER BY id DESC";
    
    // Pagination
    $perPage = 20;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($page - 1) * $perPage;
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM wpej_recruitment WHERE 1=1";
    $countParams = [];
    
    if (!empty($search)) {
        $countQuery .= " AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ? OR cover_message LIKE ?)";
        $countParams[] = "%$search%";
        $countParams[] = "%$search%";
        $countParams[] = "%$search%";
        $countParams[] = "%$search%";
    }
    
    if ($has_status_column && !empty($status_filter)) {
        $countQuery .= " AND status = ?";
        $countParams[] = $status_filter;
    }
    
    if (!empty($position_filter)) {
        $countQuery .= " AND position LIKE ?";
        $countParams[] = "%$position_filter%";
    }
    
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($countParams);
    $totalApplications = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalApplications / $perPage);
    
    // Get data
    $query .= " LIMIT $perPage OFFSET $offset";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics - 根据是否有status列调整查询
    try {
        if ($has_status_column) {
            $statsStmt = $db->query("
                SELECT 
                    COUNT(*) as total_applications,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN status = 'reviewed' THEN 1 ELSE 0 END) as reviewed_count,
                    SUM(CASE WHEN status = 'shortlisted' THEN 1 ELSE 0 END) as shortlisted_count,
                    SUM(CASE WHEN status = 'interviewed' THEN 1 ELSE 0 END) as interviewed_count,
                    SUM(CASE WHEN status = 'hired' THEN 1 ELSE 0 END) as hired_count,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                    COUNT(DISTINCT position) as unique_positions
                FROM wpej_recruitment
            ");
        } else {
            $statsStmt = $db->query("
                SELECT 
                    COUNT(*) as total_applications,
                    0 as pending_count,
                    0 as reviewed_count,
                    0 as shortlisted_count,
                    0 as interviewed_count,
                    0 as hired_count,
                    0 as rejected_count,
                    COUNT(DISTINCT position) as unique_positions
                FROM wpej_recruitment
            ");
        }
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // 如果统计查询失败，设置默认值
        $stats = [
            'total_applications' => count($applications),
            'pending_count' => 0,
            'reviewed_count' => 0,
            'shortlisted_count' => 0,
            'interviewed_count' => 0,
            'hired_count' => 0,
            'rejected_count' => 0,
            'unique_positions' => 0
        ];
    }
    
    // Get all positions
    $positionStmt = $db->query("SELECT DISTINCT position FROM wpej_recruitment WHERE position IS NOT NULL AND position != '' ORDER BY position");
    $positions = $positionStmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $error_msg = "Database error: " . $e->getMessage();
    // 设置默认值以避免未定义变量错误
    $stats = [
        'total_applications' => 0,
        'pending_count' => 0,
        'reviewed_count' => 0,
        'shortlisted_count' => 0,
        'interviewed_count' => 0,
        'hired_count' => 0,
        'rejected_count' => 0,
        'unique_positions' => 0
    ];
    $applications = [];
    $positions = [];
    $totalApplications = 0;
    $totalPages = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Job Applications - SkillCraft Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        .files-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 10px;
        }
        
        .file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }
        
        .file-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .file-icon {
            color: #6c757d;
        }
        
        .file-actions a {
            color: #007bff;
            text-decoration: none;
            margin-left: 10px;
        }
        
        .file-actions a:hover {
            text-decoration: underline;
        }
        
        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-reviewed { background: #cce5ff; color: #004085; }
        .status-shortlisted { background: #d1ecf1; color: #0c5460; }
        .status-interviewed { background: #d4edda; color: #155724; }
        .status-hired { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        
        /* Vertical button layout styles */
        .vertical-actions {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 80px;
            max-width: 120px;
        }
        
        .vertical-btn {
            padding: 4px 8px;
            font-size: 0.8rem;
            text-align: center;
            width: 100%;
            box-sizing: border-box;
            margin: 0;
            text-decoration: none;
            border: 1px solid;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn-view {
            background: #f8f9fa;
            color: #495057;
            border-color: #ced4da;
        }
        
        .btn-view:hover {
            background: #e9ecef;
        }
        
        .btn-download {
            background: #e7f5ff;
            color: #0066cc;
            border-color: #0066cc;
        }
        
        .btn-download:hover {
            background: #d0ebff;
        }
        
        .btn-edit {
            background: #fff9db;
            color: #e67700;
            border-color: #e67700;
        }
        
        .btn-edit:hover {
            background: #fff3bf;
        }
        
        .btn-delete {
            background: #fff5f5;
            color: #e53e3e;
            border-color: #e53e3e;
        }
        
        .btn-delete:hover {
            background: #fed7d7;
        }
        
        .table-container {
            overflow-x: auto;
            max-width: 100%;
        }
        
        .admin-table {
            min-width: 900px;
            width: 100%;
            border-collapse: collapse;
        }
        
        .admin-table th,
        .admin-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .admin-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .admin-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .admin-table th:nth-child(5),
        .admin-table td:nth-child(5) {
            text-align: center;
            width: 80px;
        }
        
        .admin-table th:nth-child(6),
        .admin-table td:nth-child(6) {
            min-width: 140px;
            max-width: 150px;
        }
        
        .no-status {
            color: #6c757d;
            font-style: italic;
        }
        
        .admin-form {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .form-control:focus {
            border-color: #2271b1;
            outline: none;
            box-shadow: 0 0 0 1px #2271b1;
        }
        
        .status-select {
            padding: 8px 12px;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            width: 100%;
            box-sizing: border-box;
        }
        
        .file-count {
            display: inline-block;
            min-width: 20px;
            text-align: center;
            padding: 2px 6px;
            background-color: #e9ecef;
            border-radius: 10px;
            font-size: 0.75rem;
            color: #495057;
        }
        
        .text-center {
            text-align: center !important;
        }
    </style>
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
                    <?php 
                    if ($action == 'view') {
                        echo 'View Job Application';
                    } elseif ($action == 'edit') {
                        echo 'Edit Job Application';
                    } else {
                        echo 'Manage Job Applications';
                    }
                    ?>
                </h1>
                <p>Manage recruitment applications and candidate information</p>
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

                <?php if ($action == 'view' && $current_application): ?>
                <!-- View Application Details -->
                <div class="admin-card">
                    <div class="admin-card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h2 class="admin-card-title" style="margin: 0;">
                            Application: #<?php echo $current_application['id']; ?>
                        </h2>
                        <div style="display: flex; gap: 10px;">
                            <a href="recruitment.php?action=edit&id=<?php echo $current_application['id']; ?>" class="btn-admin btn-admin-primary">
                                Edit
                            </a>
                            <a href="recruitment.php" class="btn-admin btn-admin-outline">
                                Back to Applications
                            </a>
                        </div>
                    </div>
                    
                    <div class="admin-card-body">
                        <!-- Application Information -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">
                            <div class="application-detail-section">
                                <h4>Candidate Information</h4>
                                <div style="display: grid; grid-template-columns: 120px 1fr; gap: 10px;">
                                    <div style="font-weight: bold;">Full Name:</div>
                                    <div><strong><?php echo htmlspecialchars($current_application['full_name']); ?></strong></div>
                                    
                                    <div style="font-weight: bold;">Email:</div>
                                    <div><a href="mailto:<?php echo htmlspecialchars($current_application['email']); ?>">
                                        <?php echo htmlspecialchars($current_application['email']); ?>
                                    </a></div>
                                    
                                    <div style="font-weight: bold;">Phone:</div>
                                    <div><?php echo htmlspecialchars($current_application['phone']); ?></div>
                                    
                                    <div style="font-weight: bold;">Application ID:</div>
                                    <div><?php echo $current_application['id']; ?></div>
                                </div>
                            </div>
                            
                            <div class="application-detail-section">
                                <h4>Application Details</h4>
                                <div style="display: grid; grid-template-columns: 120px 1fr; gap: 10px;">
                                    <div style="font-weight: bold;">Position:</div>
                                    <div><strong><?php echo htmlspecialchars($current_application['position']); ?></strong></div>
                                    
                                    <div style="font-weight: bold;">Status:</div>
                                    <div>
                                        <?php if (isset($current_application['status']) && !empty($current_application['status'])): ?>
                                        <span class="status-badge status-<?php echo htmlspecialchars($current_application['status']); ?>">
                                            <?php echo ucfirst(htmlspecialchars($current_application['status'])); ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="no-status">Not set</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Cover Message -->
                        <?php if (!empty($current_application['cover_message'])): ?>
                        <div class="application-detail-section">
                            <h4>Cover Letter / Message</h4>
                            <div style="white-space: pre-wrap; background: white; padding: 20px; border-radius: 4px; border: 1px solid #dee2e6; line-height: 1.6;">
                                <?php echo htmlspecialchars($current_application['cover_message']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Uploaded Files -->
                        <?php if (!empty($current_application['file_names']) && is_array($current_application['file_names'])): ?>
                        <div class="application-detail-section">
                            <h4>Uploaded Documents</h4>
                            <div class="files-list">
                                <?php foreach ($current_application['file_names'] as $file): ?>
                                <div class="file-item">
                                    <div class="file-info">
                                        <?php
                                        $extension = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));
                                        $icon = 'fa-file';
                                        if (in_array($extension, ['pdf'])) {
                                            $icon = 'fa-file-pdf';
                                        } elseif (in_array($extension, ['doc', 'docx'])) {
                                            $icon = 'fa-file-word';
                                        } elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                                            $icon = 'fa-file-image';
                                        } elseif (in_array($extension, ['txt'])) {
                                            $icon = 'fa-file-alt';
                                        }
                                        ?>
                                        <i class="fas <?php echo $icon; ?> file-icon"></i>
                                        <span><?php echo htmlspecialchars($file['original_name']); ?></span>
                                    </div>
                                    <div class="file-actions">
                                        <a href="../Recruitment/<?php echo htmlspecialchars($file['saved_name']); ?>" target="_blank">
                                            Download
                                        </a>
                                        <a href="../Recruitment/<?php echo htmlspecialchars($file['saved_name']); ?>" target="_blank">
                                            View
                                        </a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Quick Actions -->
                        <?php if ($has_status_column): ?>
                        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6;">
                            <h4>Application Status</h4>
                            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 15px;">
                                <?php 
                                $status_options = [
                                    'pending' => ['color' => '#ffc107', 'label' => 'Pending'],
                                    'reviewed' => ['color' => '#17a2b8', 'label' => 'Reviewed'],
                                    'shortlisted' => ['color' => '#6f42c1', 'label' => 'Shortlisted'],
                                    'interviewed' => ['color' => '#20c997', 'label' => 'Interviewed'],
                                    'hired' => ['color' => '#28a745', 'label' => 'Hired'],
                                    'rejected' => ['color' => '#dc3545', 'label' => 'Rejected']
                                ];
                                
                                $current_status = $current_application['status'] ?? '';
                                foreach ($status_options as $status_key => $status_info):
                                    if ($current_status != $status_key):
                                ?>
                                <a href="recruitment.php?action=update_status&id=<?php echo $current_application['id']; ?>&status=<?php echo $status_key; ?>" 
                                   class="btn-admin" style="background: <?php echo $status_info['color']; ?>; border-color: <?php echo $status_info['color']; ?>; color: white;">
                                    <?php echo $status_info['label']; ?>
                                </a>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                                
                                <a href="recruitment.php?action=delete&id=<?php echo $current_application['id']; ?>" 
                                   class="btn-admin btn-admin-danger"
                                   onclick="return confirm('Delete this application? This action cannot be undone.')">
                                    Delete Application
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php elseif ($action == 'edit' && $current_application): ?>
                <!-- Edit Application Form -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h2 class="admin-card-title">Edit Job Application: #<?php echo $current_application['id']; ?></h2>
                    </div>
                    <div class="admin-card-body">
                        <form method="post" class="admin-form">
                            <input type="hidden" name="application_id" value="<?php echo $current_application['id']; ?>">
                            
                            <div class="form-group">
                                <label for="full_name">Full Name *</label>
                                <input type="text" id="full_name" name="full_name" class="form-control" value="<?php echo htmlspecialchars($current_application['full_name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($current_application['email']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="text" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($current_application['phone']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="position">Position Applied For *</label>
                                <input type="text" id="position" name="position" class="form-control" value="<?php echo htmlspecialchars($current_application['position']); ?>" required>
                            </div>
                            
                            <?php if ($has_status_column): ?>
                            <div class="form-group">
                                <label for="status">Application Status</label>
                                <select id="status" name="status" class="status-select">
                                    <option value="pending" <?php echo ($current_application['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="reviewed" <?php echo ($current_application['status'] == 'reviewed') ? 'selected' : ''; ?>>Reviewed</option>
                                    <option value="shortlisted" <?php echo ($current_application['status'] == 'shortlisted') ? 'selected' : ''; ?>>Shortlisted</option>
                                    <option value="interviewed" <?php echo ($current_application['status'] == 'interviewed') ? 'selected' : ''; ?>>Interviewed</option>
                                    <option value="hired" <?php echo ($current_application['status'] == 'hired') ? 'selected' : ''; ?>>Hired</option>
                                    <option value="rejected" <?php echo ($current_application['status'] == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <div class="form-group">
                                <label for="cover_message">Cover Letter / Message</label>
                                <textarea id="cover_message" name="cover_message" class="form-control" rows="8"><?php echo htmlspecialchars($current_application['cover_message']); ?></textarea>
                            </div>
                            
                            <!-- Uploaded Files (Read-only) -->
                            <?php if (!empty($current_application['file_names']) && is_array($current_application['file_names'])): ?>
                            <div class="form-group">
                                <label>Uploaded Documents (Cannot be edited)</label>
                                <div class="files-list">
                                    <?php foreach ($current_application['file_names'] as $file): ?>
                                    <div class="file-item">
                                        <div class="file-info">
                                            <span><?php echo htmlspecialchars($file['original_name']); ?></span>
                                        </div>
                                        <div class="file-actions">
                                            <a href="../Recruitment/<?php echo htmlspecialchars($file['saved_name']); ?>" target="_blank">
                                                Download
                                            </a>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div style="display: flex; gap: 15px; margin-top: 30px;">
                                <button type="submit" name="update_application" class="btn-admin btn-admin-primary">
                                    Update Application
                                </button>
                                <a href="recruitment.php?action=view&id=<?php echo $current_application['id']; ?>" class="btn-admin btn-admin-outline">
                                    Cancel
                                </a>
                                <a href="recruitment.php" class="btn-admin btn-admin-outline">
                                    Back to List
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <?php else: ?>
                <!-- Applications List -->
                <!-- Statistics Cards -->
                <?php if (isset($stats) && $stats): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <div class="stat-card">
                        <div class="stat-label">Total Applications</div>
                        <div class="stat-value"><?php echo number_format($stats['total_applications'] ?? 0); ?></div>
                    </div>
                    <?php if ($has_status_column): ?>
                    <div class="stat-card">
                        <div class="stat-label">Pending Review</div>
                        <div class="stat-value" style="color: #ffc107;"><?php echo number_format($stats['pending_count'] ?? 0); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Shortlisted</div>
                        <div class="stat-value" style="color: #6f42c1;"><?php echo number_format($stats['shortlisted_count'] ?? 0); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Hired</div>
                        <div class="stat-value" style="color: #28a745;"><?php echo number_format($stats['hired_count'] ?? 0); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <div class="admin-card">
                    <div class="admin-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <h2 class="admin-card-title" style="margin: 0;">All Job Applications</h2>
                        
                        <!-- Search and Filter Form -->
                        <form method="GET" action="" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <input type="text" name="search" placeholder="Search applications..." 
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   style="padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 4px; min-width: 200px;">
                            
                            <?php if ($has_status_column): ?>
                            <select name="status_filter" style="padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 4px;">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="reviewed" <?php echo $status_filter == 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                <option value="shortlisted" <?php echo $status_filter == 'shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                                <option value="interviewed" <?php echo $status_filter == 'interviewed' ? 'selected' : ''; ?>>Interviewed</option>
                                <option value="hired" <?php echo $status_filter == 'hired' ? 'selected' : ''; ?>>Hired</option>
                                <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                            <?php endif; ?>
                            
                            <select name="position_filter" style="padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 4px;">
                                <option value="">All Positions</option>
                                <?php foreach ($positions as $pos): ?>
                                <option value="<?php echo htmlspecialchars($pos); ?>" 
                                    <?php echo $position_filter == $pos ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($pos); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <button type="submit" class="btn-admin btn-admin-primary" style="padding: 8px 16px;">
                                Search
                            </button>
                            
                            <a href="recruitment.php" class="btn-admin btn-admin-outline" style="padding: 8px 16px;">
                                Reset
                            </a>
                        </form>
                    </div>
                    
                    <div class="admin-card-body">
                        <div class="table-container">
                        <?php if (count($applications) > 0): ?>
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Candidate</th>
                                        <th>Position</th>
                                        <?php if ($has_status_column): ?>
                                        <th>Status</th>
                                        <?php endif; ?>
                                        <th class="text-center">Files</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($applications as $app): ?>
                                    <tr>
                                        <td><?php echo $app['id']; ?></td>
                                        <td style="overflow: hidden; text-overflow: ellipsis;">
                                            <div><strong><?php echo htmlspecialchars(substr($app['full_name'] ?? '', 0, 20)); ?><?php echo strlen($app['full_name'] ?? '') > 20 ? '...' : ''; ?></strong></div>
                                            <div style="font-size: 0.8rem; color: #666;"><?php echo htmlspecialchars(substr($app['email'] ?? '', 0, 25)); ?><?php echo strlen($app['email'] ?? '') > 25 ? '...' : ''; ?></div>
                                        </td>
                                        <td style="white-space: normal; word-wrap: break-word;">
                                            <?php echo htmlspecialchars(substr($app['position'] ?? '', 0, 25)); ?><?php echo strlen($app['position'] ?? '') > 25 ? '...' : ''; ?>
                                        </td>
                                        <?php if ($has_status_column): ?>
                                        <td>
                                            <?php if (!empty($app['status'])): ?>
                                            <span class="status-badge status-<?php echo htmlspecialchars($app['status']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($app['status'])); ?>
                                            </span>
                                            <?php else: ?>
                                            <span class="no-status">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php endif; ?>
                                        <td class="text-center">
                                            <?php 
                                            $files = json_decode($app['file_names'] ?? '[]', true);
                                            if (!empty($files) && is_array($files) && count($files) > 0) {
                                                echo '<span class="file-count">' . count($files) . '</span>';
                                            } else {
                                                echo '<span class="file-count">0</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="vertical-actions">
                                                <a href="recruitment.php?action=view&id=<?php echo $app['id']; ?>" 
                                                   class="vertical-btn btn-view">
                                                    View
                                                </a>
                                                
                                                <?php 
                                                $files = json_decode($app['file_names'] ?? '[]', true);
                                                if (!empty($files) && is_array($files) && count($files) > 0):
                                                    // 下载第一个文件
                                                    $firstFile = $files[0];
                                                ?>
                                                <a href="../Recruitment/<?php echo htmlspecialchars($firstFile['saved_name']); ?>" 
                                                   class="vertical-btn btn-download" target="_blank">
                                                    Download
                                                </a>
                                                <?php else: ?>
                                                <a href="#" 
                                                   class="vertical-btn btn-download" style="opacity: 0.5; cursor: not-allowed;" onclick="return false;">
                                                    No Files
                                                </a>
                                                <?php endif; ?>
                                                
                                                <a href="recruitment.php?action=edit&id=<?php echo $app['id']; ?>" 
                                                   class="vertical-btn btn-edit">
                                                    Edit
                                                </a>
                                                
                                                <a href="recruitment.php?action=delete&id=<?php echo $app['id']; ?>" 
                                                   class="vertical-btn btn-delete"
                                                   onclick="return confirm('Delete this application? This action cannot be undone.')">
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
                                <a href="recruitment.php?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) && $has_status_column ? '&status_filter=' . urlencode($status_filter) : ''; ?><?php echo !empty($position_filter) ? '&position_filter=' . urlencode($position_filter) : ''; ?>"
                                   class="btn-admin btn-admin-outline" style="padding: 6px 12px;">
                                    Previous
                                </a>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                    <a href="recruitment.php?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) && $has_status_column ? '&status_filter=' . urlencode($status_filter) : ''; ?><?php echo !empty($position_filter) ? '&position_filter=' . urlencode($position_filter) : ''; ?>"
                                       class="btn-admin <?php echo $i == $page ? 'btn-admin-primary' : 'btn-admin-outline'; ?>" 
                                       style="padding: 6px 12px;">
                                        <?php echo $i; ?>
                                    </a>
                                    <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                    <span style="padding: 6px 12px;">...</span>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                <a href="recruitment.php?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) && $has_status_column ? '&status_filter=' . urlencode($status_filter) : ''; ?><?php echo !empty($position_filter) ? '&position_filter=' . urlencode($position_filter) : ''; ?>"
                                   class="btn-admin btn-admin-outline" style="padding: 6px 12px;">
                                    Next
                                </a>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div style="text-align: center; margin-top: 15px; color: #666;">
                                Showing <?php echo count($applications); ?> of <?php echo $totalApplications; ?> applications
                            </div>
                            
                        <?php else: ?>
                            <div class="empty-state">
                                <h3>No Applications Found</h3>
                                <p><?php echo !empty($search) || !empty($status_filter) || !empty($position_filter) ? 'Try adjusting your search or filters.' : 'There are no job applications yet.'; ?></p>
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
</body>
</html>