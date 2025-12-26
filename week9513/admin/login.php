<?php
session_start();
require_once '../config/config.php';

// 简单的管理员登录验证
$error = '';
if ($_POST) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    // 硬编码的管理员凭据（在实际项目中应该使用数据库）
    $admin_username = 'admin';
    $admin_password = 'admin123';
    
    if ($username === $admin_username && $password === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        header('Location: index.php');
        exit();
    } else {
        $error = 'Invalid username or password';
    }
}

// 如果已经登录，重定向到管理面板
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - SkillCraft Workshops</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-logo">
                <h1>
                    <i class="fas fa-hands me-2"></i>
                    SkillCraft Admin
                </h1>
                <p>Access the administration panel</p>
            </div>

            <?php if ($error): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <form method="post">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" required 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>

                <button type="submit" class="btn-admin btn-admin-primary" style="width: 100%;">
                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                </button>
            </form>

            <div style="text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                <p style="color: #666; margin: 0;">
                    <strong>Demo Credentials:</strong><br>
                    Username: admin<br>
                    Password: admin123
                </p>
            </div>
        </div>
    </div>
</body>
</html>