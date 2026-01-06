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
$feedback_id = $_GET['id'] ?? null;
$success_msg = '';
$error_msg = '';

try {
    // Handle delete feedback
    if ($action == 'delete' && $feedback_id) {
        $stmt = $db->prepare("DELETE FROM wpej_feedback WHERE id = ?");
        $stmt->execute([$feedback_id]);
        $success_msg = 'Feedback deleted successfully';
        header('Location: feedback.php?success=' . urlencode($success_msg));
        exit();
    }
    
    // Handle update feedback status
    if ($action == 'update_status' && $feedback_id && isset($_GET['status'])) {
        $status = $_GET['status'];
        $valid_statuses = ['pending', 'read', 'in_progress', 'resolved', 'closed'];
        
        if (in_array($status, $valid_statuses)) {
            $stmt = $db->prepare("UPDATE wpej_feedback SET status = ? WHERE id = ?");
            $stmt->execute([$status, $feedback_id]);
            $success_msg = 'Feedback status updated successfully';
            header('Location: feedback.php?success=' . urlencode($success_msg));
            exit();
        }
    }
    
    // Handle reply to feedback
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
        $feedback_id = $_POST['feedback_id'] ?? null;
        $reply_message = trim($_POST['reply_message'] ?? '');
        $admin_name = $_SESSION['admin_username'] ?? 'Admin';
        
        if ($feedback_id && !empty($reply_message)) {
            // Get feedback details
            $stmt = $db->prepare("SELECT * FROM wpej_feedback WHERE id = ?");
            $stmt->execute([$feedback_id]);
            $feedback = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($feedback) {
                // Update feedback with reply
                $updateStmt = $db->prepare("UPDATE wpej_feedback SET status = 'replied', admin_reply = ?, replied_at = NOW() WHERE id = ?");
                $updateStmt->execute([$reply_message, $feedback_id]);
                
                // Send email to customer
                $customer_email = $feedback['email'];
                $customer_name = $feedback['name'];
                $subject = "Re: " . $feedback['subject'];
                
                $message_content = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: #3498db; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 5px 5px; }
                        .ticket-details { background: white; padding: 20px; border-radius: 5px; border-left: 4px solid #2ecc71; margin-bottom: 20px; }
                        .reply-box { background: white; padding: 20px; border-radius: 5px; border-left: 4px solid #3498db; margin-top: 20px; }
                        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>Response to Your Feedback</h1>
                            <p>Ticket ID: #{$feedback_id}</p>
                        </div>
                        <div class='content'>
                            <div class='ticket-details'>
                                <h3>Original Message</h3>
                                <p><strong>Subject:</strong> {$feedback['subject']}</p>
                                <p><strong>Your Message:</strong></p>
                                <p>{$feedback['message']}</p>
                            </div>
                            <div class='reply-box'>
                                <h3>Our Response</h3>
                                <p>{$reply_message}</p>
                                <p><strong>Responded by:</strong> {$admin_name}</p>
                                <p><strong>Response Date:</strong> " . date('F j, Y, H:i:s') . "</p>
                            </div>
                            <div class='footer'>
                                <p>Thank you for contacting us. If you need further assistance, please reply to this email.</p>
                                <p><strong>Best regards,</strong><br>Support Team</p>
                            </div>
                        </div>
                    </div>
                </body>
                </html>
                ";
                
                // Send email using WordPress mail function if available
                if (function_exists('wp_mail')) {
                    $headers = [
                        'Content-Type: text/html; charset=UTF-8',
                        'From: Support Team <support@ncc-ecommerce.com>',
                        'Reply-To: Support Team <support@ncc-ecommerce.com>'
                    ];
                    
                    wp_mail($customer_email, $subject, $message_content, $headers);
                } else {
                    // Fallback to regular mail
                    $headers = "MIME-Version: 1.0\r\n";
                    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                    $headers .= "From: Support Team <support@ncc-ecommerce.com>\r\n";
                    $headers .= "Reply-To: Support Team <support@ncc-ecommerce.com>\r\n";
                    
                    mail($customer_email, $subject, $message_content, $headers);
                }
                
                $success_msg = 'Reply sent successfully';
                header('Location: feedback.php?success=' . urlencode($success_msg));
                exit();
            }
        } else {
            $error_msg = 'Please enter a reply message';
        }
    }
    
    // Handle edit feedback (update feedback details)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_feedback'])) {
        $feedback_id = $_POST['feedback_id'] ?? null;
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $status = $_POST['status'] ?? 'pending';
        $admin_reply = trim($_POST['admin_reply'] ?? '');
        
        if ($feedback_id && !empty($name) && !empty($email) && !empty($subject) && !empty($message)) {
            // Prepare the update query
            $updateQuery = "UPDATE wpej_feedback SET name = ?, email = ?, subject = ?, message = ?, status = ?, admin_reply = ?";
            
            // If admin_reply is provided and not empty, update replied_at
            if (!empty($admin_reply)) {
                $updateQuery .= ", replied_at = NOW()";
            }
            
            $updateQuery .= " WHERE id = ?";
            
            $stmt = $db->prepare($updateQuery);
            
            if (!empty($admin_reply)) {
                $stmt->execute([$name, $email, $subject, $message, $status, $admin_reply, $feedback_id]);
            } else {
                $stmt->execute([$name, $email, $subject, $message, $status, $admin_reply, $feedback_id]);
            }
            
            $success_msg = 'Feedback updated successfully';
            header('Location: feedback.php?action=view&id=' . $feedback_id . '&success=' . urlencode($success_msg));
            exit();
        } else {
            $error_msg = 'Please fill in all required fields';
        }
    }
    
    // Get specific feedback for view/reply/edit
    $current_feedback = null;
    if (($action == 'view' || $action == 'reply' || $action == 'edit') && $feedback_id) {
        $stmt = $db->prepare("SELECT * FROM wpej_feedback WHERE id = ?");
        $stmt->execute([$feedback_id]);
        $current_feedback = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Build query to get feedback
    $search = $_GET['search'] ?? '';
    $status_filter = $_GET['status_filter'] ?? '';
    $date_filter = $_GET['date_filter'] ?? '';
    
    $query = "SELECT * FROM wpej_feedback WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if (!empty($status_filter)) {
        $query .= " AND status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($date_filter)) {
        $query .= " AND DATE(created_at) = ?";
        $params[] = $date_filter;
    }
    
    $query .= " ORDER BY id DESC";
    
    // Pagination
    $perPage = 20;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($page - 1) * $perPage;
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM wpej_feedback WHERE 1=1";
    $countParams = [];
    
    if (!empty($search)) {
        $countQuery .= " AND (name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)";
        $countParams[] = "%$search%";
        $countParams[] = "%$search%";
        $countParams[] = "%$search%";
        $countParams[] = "%$search%";
    }
    
    if (!empty($status_filter)) {
        $countQuery .= " AND status = ?";
        $countParams[] = $status_filter;
    }
    
    if (!empty($date_filter)) {
        $countQuery .= " AND DATE(created_at) = ?";
        $countParams[] = $date_filter;
    }
    
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($countParams);
    $totalFeedback = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalFeedback / $perPage);
    
    // Get data
    $query .= " LIMIT $perPage OFFSET $offset";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $feedback_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $statsStmt = $db->query("
        SELECT 
            COUNT(*) as total_feedback,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END) as replied_count,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
            COUNT(DISTINCT email) as unique_customers
        FROM wpej_feedback
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
    <title>Manage Feedback - SkillCraft Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        /* Additional styles for vertical button layout */
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
        }
        
        .table-container {
            overflow-x: auto;
            max-width: 100%;
        }
        
        .admin-table {
            min-width: 1000px;
            width: 100%;
        }
        
        .admin-table th:nth-child(6),
        .admin-table td:nth-child(6) {
            min-width: 120px;
            max-width: 130px;
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
        
        .btn-admin {
            display: inline-block;
            padding: 10px 20px;
            border: 1px solid transparent;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            text-align: center;
        }
        
        .btn-admin-primary {
            background-color: #2271b1;
            color: white;
            border-color: #2271b1;
        }
        
        .btn-admin-primary:hover {
            background-color: #135e96;
            border-color: #135e96;
        }
        
        .btn-admin-outline {
            background-color: white;
            color: #2271b1;
            border-color: #2271b1;
        }
        
        .btn-admin-outline:hover {
            background-color: #f0f6fc;
        }
        
        .btn-admin-danger {
            background-color: #d63638;
            color: white;
            border-color: #d63638;
        }
        
        .btn-admin-danger:hover {
            background-color: #b32d2e;
            border-color: #b32d2e;
        }
        
        .admin-form {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .status-select {
            padding: 8px 12px;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            width: 100%;
            box-sizing: border-box;
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
                        echo 'View Feedback';
                    } elseif ($action == 'reply') {
                        echo 'Reply to Feedback';
                    } elseif ($action == 'edit') {
                        echo 'Edit Feedback';
                    } else {
                        echo 'Manage Customer Feedback';
                    }
                    ?>
                </h1>
                <p>Manage customer feedback and support tickets</p>
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

                <?php if ($action == 'view' && $current_feedback): ?>
                <!-- View Feedback Details -->
                <div class="admin-card">
                    <div class="admin-card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h2 class="admin-card-title" style="margin: 0;">
                            Feedback: #<?php echo $current_feedback['id']; ?>
                        </h2>
                        <div style="display: flex; gap: 10px;">
                            <a href="feedback.php?action=edit&id=<?php echo $current_feedback['id']; ?>" class="btn-admin btn-admin-primary">
                                Edit
                            </a>
                            <a href="feedback.php?action=reply&id=<?php echo $current_feedback['id']; ?>" class="btn-admin" style="background: #17a2b8; border-color: #17a2b8; color: white;">
                                Reply
                            </a>
                            <a href="feedback.php" class="btn-admin btn-admin-outline">
                                Back to List
                            </a>
                        </div>
                    </div>
                    
                    <div class="admin-card-body">
                        <!-- Feedback Information -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">
                            <div class="feedback-detail-section">
                                <h4>Customer Information</h4>
                                <div style="display: grid; grid-template-columns: 120px 1fr; gap: 10px;">
                                    <div style="font-weight: bold;">Name:</div>
                                    <div><?php echo htmlspecialchars($current_feedback['name']); ?></div>
                                    
                                    <div style="font-weight: bold;">Email:</div>
                                    <div><a href="mailto:<?php echo htmlspecialchars($current_feedback['email']); ?>">
                                        <?php echo htmlspecialchars($current_feedback['email']); ?>
                                    </a></div>
                                    
                                    <div style="font-weight: bold;">Submitted:</div>
                                    <div><?php echo date('F d, Y H:i:s', strtotime($current_feedback['created_at'])); ?></div>
                                    
                                    <div style="font-weight: bold;">Status:</div>
                                    <div>
                                        <span class="status-badge status-<?php echo htmlspecialchars($current_feedback['status']); ?>">
                                            <?php echo ucfirst(htmlspecialchars($current_feedback['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="feedback-detail-section">
                                <h4>Feedback Details</h4>
                                <div style="display: grid; grid-template-columns: 120px 1fr; gap: 10px;">
                                    <div style="font-weight: bold;">Subject:</div>
                                    <div><strong><?php echo htmlspecialchars($current_feedback['subject']); ?></strong></div>
                                    
                                    <?php if (isset($current_feedback['replied_at']) && $current_feedback['replied_at']): ?>
                                    <div style="font-weight: bold;">Replied:</div>
                                    <div><?php echo date('F d, Y H:i:s', strtotime($current_feedback['replied_at'])); ?></div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($current_feedback['admin_reply']) && $current_feedback['admin_reply']): ?>
                                    <div style="font-weight: bold;">Admin Reply:</div>
                                    <div>
                                        <div style="background: #f0f7ff; padding: 10px; border-radius: 4px; border-left: 4px solid #0073aa; margin-top: 5px;">
                                            <?php echo nl2br(htmlspecialchars($current_feedback['admin_reply'])); ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Feedback Message -->
                        <div class="feedback-detail-section">
                            <h4>Customer Message</h4>
                            <div style="white-space: pre-wrap; background: white; padding: 20px; border-radius: 4px; border: 1px solid #dee2e6; line-height: 1.6;">
                                <?php echo htmlspecialchars($current_feedback['message']); ?>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6;">
                            <h4>Quick Actions</h4>
                            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 15px;">
                                <?php if ($current_feedback['status'] != 'pending'): ?>
                                <a href="feedback.php?action=update_status&id=<?php echo $current_feedback['id']; ?>&status=pending" 
                                   class="btn-admin" style="background: #ffc107; border-color: #ffc107; color: #212529;">
                                    Mark as Pending
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($current_feedback['status'] != 'read'): ?>
                                <a href="feedback.php?action=update_status&id=<?php echo $current_feedback['id']; ?>&status=read" 
                                   class="btn-admin" style="background: #17a2b8; border-color: #17a2b8;">
                                    Mark as Read
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($current_feedback['status'] != 'resolved'): ?>
                                <a href="feedback.php?action=update_status&id=<?php echo $current_feedback['id']; ?>&status=resolved" 
                                   class="btn-admin" style="background: #28a745; border-color: #28a745;">
                                    Mark as Resolved
                                </a>
                                <?php endif; ?>
                                
                                <a href="feedback.php?action=delete&id=<?php echo $current_feedback['id']; ?>" 
                                   class="btn-admin btn-admin-danger"
                                   onclick="return confirm('Delete this feedback? This action cannot be undone.')">
                                    Delete
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <?php elseif ($action == 'reply' && $current_feedback): ?>
                <!-- Reply to Feedback Form -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h2 class="admin-card-title">Reply to Feedback: #<?php echo $current_feedback['id']; ?></h2>
                    </div>
                    <div class="admin-card-body">
                        <form method="post" class="admin-form">
                            <input type="hidden" name="feedback_id" value="<?php echo $current_feedback['id']; ?>">
                            
                            <div class="form-group">
                                <label>Customer</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($current_feedback['name'] . ' (' . $current_feedback['email'] . ')'); ?>" disabled>
                            </div>
                            
                            <div class="form-group">
                                <label>Subject</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($current_feedback['subject']); ?>" disabled>
                            </div>
                            
                            <div class="form-group">
                                <label>Original Message</label>
                                <textarea class="form-control" rows="4" disabled><?php echo htmlspecialchars($current_feedback['message']); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="reply_message">Your Reply *</label>
                                <textarea id="reply_message" name="reply_message" class="form-control" rows="8" required placeholder="Type your response to the customer..."></textarea>
                                <small class="text-muted">Your reply will be sent to the customer's email address.</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Reply Information</label>
                                <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; border: 1px solid #dee2e6;">
                                    <p><strong>Reply will be sent from:</strong> Support Team &lt;support@ncc-ecommerce.com&gt;</p>
                                    <p><strong>Reply will be sent to:</strong> <?php echo htmlspecialchars($current_feedback['email']); ?></p>
                                    <p><strong>Reply will be signed as:</strong> <?php echo $_SESSION['admin_username'] ?? 'Admin'; ?></p>
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 15px; margin-top: 30px;">
                                <button type="submit" name="send_reply" class="btn-admin btn-admin-primary">
                                    Send Reply
                                </button>
                                <a href="feedback.php?action=view&id=<?php echo $current_feedback['id']; ?>" class="btn-admin btn-admin-outline">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <?php elseif ($action == 'edit' && $current_feedback): ?>
                <!-- Edit Feedback Form -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h2 class="admin-card-title">Edit Feedback: #<?php echo $current_feedback['id']; ?></h2>
                    </div>
                    <div class="admin-card-body">
                        <form method="post" class="admin-form">
                            <input type="hidden" name="feedback_id" value="<?php echo $current_feedback['id']; ?>">
                            
                            <div class="form-group">
                                <label for="name">Customer Name *</label>
                                <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($current_feedback['name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Customer Email *</label>
                                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($current_feedback['email']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="subject">Subject *</label>
                                <input type="text" id="subject" name="subject" class="form-control" value="<?php echo htmlspecialchars($current_feedback['subject']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status" class="status-select">
                                    <option value="pending" <?php echo ($current_feedback['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="read" <?php echo ($current_feedback['status'] == 'read') ? 'selected' : ''; ?>>Read</option>
                                    <option value="in_progress" <?php echo ($current_feedback['status'] == 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="replied" <?php echo ($current_feedback['status'] == 'replied') ? 'selected' : ''; ?>>Replied</option>
                                    <option value="resolved" <?php echo ($current_feedback['status'] == 'resolved') ? 'selected' : ''; ?>>Resolved</option>
                                    <option value="closed" <?php echo ($current_feedback['status'] == 'closed') ? 'selected' : ''; ?>>Closed</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="message">Customer Message *</label>
                                <textarea id="message" name="message" class="form-control" rows="6" required><?php echo htmlspecialchars($current_feedback['message']); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="admin_reply">Admin Reply (Optional)</label>
                                <textarea id="admin_reply" name="admin_reply" class="form-control" rows="6" placeholder="Enter admin reply here..."><?php echo isset($current_feedback['admin_reply']) ? htmlspecialchars($current_feedback['admin_reply']) : ''; ?></textarea>
                                <small class="text-muted">If you add an admin reply, the replied_at timestamp will be updated.</small>
                            </div>
                            
                            <div style="display: flex; gap: 15px; margin-top: 30px;">
                                <button type="submit" name="update_feedback" class="btn-admin btn-admin-primary">
                                    Update Feedback
                                </button>
                                <a href="feedback.php?action=view&id=<?php echo $current_feedback['id']; ?>" class="btn-admin btn-admin-outline">
                                    Cancel
                                </a>
                                <a href="feedback.php" class="btn-admin btn-admin-outline">
                                    Back to List
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <?php else: ?>
                <!-- Feedback List -->
                <!-- Statistics Cards -->
                <?php if ($stats): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <div class="stat-card">
                        <div class="stat-label">Total Feedback</div>
                        <div class="stat-value"><?php echo number_format($stats['total_feedback'] ?? 0); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Pending</div>
                        <div class="stat-value" style="color: #ffc107;"><?php echo number_format($stats['pending_count'] ?? 0); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Replied</div>
                        <div class="stat-value" style="color: #17a2b8;"><?php echo number_format($stats['replied_count'] ?? 0); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Resolved</div>
                        <div class="stat-value" style="color: #28a745;"><?php echo number_format($stats['resolved_count'] ?? 0); ?></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="admin-card">
                    <div class="admin-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <h2 class="admin-card-title" style="margin: 0;">All Customer Feedback</h2>
                        
                        <!-- Search and Filter Form -->
                        <form method="GET" action="" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <input type="text" name="search" placeholder="Search feedback..." 
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   style="padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 4px; min-width: 200px;">
                            
                            <select name="status_filter" style="padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 4px;">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="read" <?php echo $status_filter == 'read' ? 'selected' : ''; ?>>Read</option>
                                <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="replied" <?php echo $status_filter == 'replied' ? 'selected' : ''; ?>>Replied</option>
                                <option value="resolved" <?php echo $status_filter == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="closed" <?php echo $status_filter == 'closed' ? 'selected' : ''; ?>>Closed</option>
                            </select>
                            
                            <div style="display: flex; align-items: center; gap: 5px;">
                                <span style="font-size: 0.9rem; color: #666;">Date:</span>
                                <input type="date" name="date_filter" 
                                       value="<?php echo htmlspecialchars($date_filter); ?>"
                                       style="padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 4px; width: 150px;">
                            </div>
                            
                            <button type="submit" class="btn-admin btn-admin-primary" style="padding: 8px 16px;">
                                Search
                            </button>
                            
                            <a href="feedback.php" class="btn-admin btn-admin-outline" style="padding: 8px 16px;">
                                Reset
                            </a>
                        </form>
                    </div>
                    
                    <div class="admin-card-body">
                        <div class="table-container">
                        <?php if (count($feedback_list) > 0): ?>
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">ID</th>
                                        <th style="width: 150px;">Customer</th>
                                        <th style="width: 200px;">Subject</th>
                                        <th style="width: 100px;">Status</th>
                                        <th style="width: 120px;">Date</th>
                                        <th style="width: 130px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($feedback_list as $feedback): ?>
                                    <tr>
                                        <td><?php echo $feedback['id']; ?></td>
                                        <td style="overflow: hidden; text-overflow: ellipsis;">
                                            <div><strong><?php echo htmlspecialchars(substr($feedback['name'], 0, 20)); ?><?php echo strlen($feedback['name']) > 20 ? '...' : ''; ?></strong></div>
                                            <div style="font-size: 0.8rem; color: #666;"><?php echo htmlspecialchars(substr($feedback['email'], 0, 25)); ?><?php echo strlen($feedback['email']) > 25 ? '...' : ''; ?></div>
                                        </td>
                                        <td style="white-space: normal; word-wrap: break-word;">
                                            <strong title="<?php echo htmlspecialchars($feedback['subject']); ?>">
                                                <?php echo htmlspecialchars(substr($feedback['subject'], 0, 40)); ?><?php echo strlen($feedback['subject']) > 40 ? '...' : ''; ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo htmlspecialchars($feedback['status']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($feedback['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo date('M d, Y', strtotime($feedback['created_at'])); ?>
                                            <?php if (isset($feedback['replied_at']) && $feedback['replied_at']): ?>
                                            <br><small style="color: #28a745;">Replied</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="vertical-actions">
                                                <a href="feedback.php?action=view&id=<?php echo $feedback['id']; ?>" 
                                                   class="vertical-btn btn-action btn-edit">
                                                    View
                                                </a>
                                                
                                                <a href="feedback.php?action=reply&id=<?php echo $feedback['id']; ?>" 
                                                   class="vertical-btn btn-action" style="color: #17a2b8; border-color: #17a2b8;">
                                                    Reply
                                                </a>
                                                
                                                <a href="feedback.php?action=edit&id=<?php echo $feedback['id']; ?>" 
                                                   class="vertical-btn btn-action" style="color: #28a745; border-color: #28a745;">
                                                    Edit
                                                </a>
                                                
                                                <a href="feedback.php?action=delete&id=<?php echo $feedback['id']; ?>" 
                                                   class="vertical-btn btn-action btn-delete"
                                                   onclick="return confirm('Delete this feedback? This action cannot be undone.')">
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
                                <a href="feedback.php?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status_filter=' . urlencode($status_filter) : ''; ?><?php echo !empty($date_filter) ? '&date_filter=' . urlencode($date_filter) : ''; ?>"
                                   class="btn-admin btn-admin-outline" style="padding: 6px 12px;">
                                    Previous
                                </a>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                    <a href="feedback.php?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status_filter=' . urlencode($status_filter) : ''; ?><?php echo !empty($date_filter) ? '&date_filter=' . urlencode($date_filter) : ''; ?>"
                                       class="btn-admin <?php echo $i == $page ? 'btn-admin-primary' : 'btn-admin-outline'; ?>" 
                                       style="padding: 6px 12px;">
                                        <?php echo $i; ?>
                                    </a>
                                    <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                    <span style="padding: 6px 12px;">...</span>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                <a href="feedback.php?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status_filter=' . urlencode($status_filter) : ''; ?><?php echo !empty($date_filter) ? '&date_filter=' . urlencode($date_filter) : ''; ?>"
                                   class="btn-admin btn-admin-outline" style="padding: 6px 12px;">
                                    Next
                                </a>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div style="text-align: center; margin-top: 15px; color: #666;">
                                Showing <?php echo count($feedback_list); ?> of <?php echo $totalFeedback; ?> feedback entries
                            </div>
                            
                        <?php else: ?>
                            <div class="empty-state">
                                <h3>No Feedback Found</h3>
                                <p><?php echo !empty($search) || !empty($status_filter) || !empty($date_filter) ? 'Try adjusting your search or filters.' : 'There is no customer feedback yet.'; ?></p>
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