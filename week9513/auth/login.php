<?php
require_once '../config/config.php';

if (isLoggedIn()) {
    header('Location: ../products.php');
    exit();
}

$error = '';

if ($_POST) {
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $last_name = trim($_POST['last_name']);

    // 验证必填字段
    if (empty($email) || empty($phone) || empty($last_name)) {
        $error = 'Please enter email, phone number, and last name';
    } else {
        // 查询 FluentCRM 订阅者表
        $query = "SELECT id, first_name, last_name, email, phone, status, is_confirmed 
                  FROM wpej_fc_subscribers 
                  WHERE email = :email AND phone = :phone AND last_name = :last_name AND status IN ('subscribed', 'pending')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->execute();

        if ($subscriber = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // 设置订阅者会话变量
            $_SESSION['subscriber_id'] = $subscriber['id'];
            $_SESSION['subscriber_email'] = $subscriber['email'];
            $_SESSION['subscriber_phone'] = $subscriber['phone'];
            $_SESSION['username'] = $subscriber['first_name'] . ' ' . $subscriber['last_name']; // 添加 username
            $_SESSION['first_name'] = $subscriber['first_name'];
            $_SESSION['last_name'] = $subscriber['last_name'];
            $_SESSION['user_type'] = 'subscriber';
            $_SESSION['subscriber_status'] = $subscriber['status'];
            
            // 记录登录时间
            $update_query = "UPDATE wpej_fc_subscribers SET last_activity = NOW() WHERE id = :id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':id', $subscriber['id']);
            $update_stmt->execute();
            
            header('Location: ../products.php');
            exit();
        } else {
            $error = 'Invalid email, phone number, or last name, or account not found';
        }
    }
}
?>

<?php include '../includes/header.php'; ?>

<div class="row justify-content-center mt-5">
    <div class="col-md-6 col-lg-4">
        <div class="card">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <i class="fas fa-hands fa-2x text-primary mb-3"></i>
                    <h2>Customer Login</h2>
                    <p class="text-muted">Sign in with your email, phone, and last name</p>
                </div>

                <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="post">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number *</label>
                        <input type="text" class="form-control" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required 
                               placeholder="Enter your registered phone number">
                    </div>

                    <div class="mb-3">
                        <label for="last_name" class="form-label">Last Name *</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" 
                               value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required 
                               placeholder="Enter your last name">
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">Sign In</button>
                </form>

                <div class="text-center">
                    <p class="mb-0">Not a subscriber yet? 
                       <a href="../subscribe/subscribe.php" class="text-primary">Subscribe here</a>
                    </p>
                </div>

                <div class="text-center mt-4 pt-3 border-top">
                    <p class="mb-2 text-muted small">Need administrator access?</p>
                    <a href="../admin/login.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-user-shield me-1"></i>Admin Login
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>