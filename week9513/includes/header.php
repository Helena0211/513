<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// 基础URL配置
$base_url = 'https://helena1201.free.nf/513/week9513';

// 基础函数定义（确保存在）
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        // 检查是否有任何用户标识，不限于 user_id
        return isset($_SESSION['user_id']) || isset($_SESSION['username']) || isset($_SESSION['user_type']);
    }
}
if (!function_exists('isInstructor')) {
    function isInstructor() {
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'instructor';
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
    }
}

// 获取当前页面路径用于高亮导航
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo defined('SITE_NAME') ? SITE_NAME : 'SkillCraft Workshops'; ?> - Learn, Create, Connect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/style.css">
</head>
<body>
    <!-- WordPress 风格导航 -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="<?php echo $base_url; ?>/index1.php">
                <i class="fas fa-hands me-2"></i>SkillCraft Workshops
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'index1.php') ? 'active' : ''; ?>" 
                           href="<?php echo $base_url; ?>/index1.php">
                           Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], 'products') !== false) ? 'active' : ''; ?>" 
                           href="<?php echo $base_url; ?>/products.php">
                           Workshops
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'about.php') ? 'active' : ''; ?>" 
                           href="<?php echo $base_url; ?>/about.php">
                           About
                        </a>
                    </li>
                     <li class="nav-item">
                       <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], 'feedback') !== false) ? 'active' : ''; ?>" 
                          href="<?php echo $base_url; ?>/feedback.php">
                          Feedback
                      </a>
                    </li>
                    <!-- 新增订阅链接 -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], 'subscribe') !== false || $current_page == 'index.php') ? 'active' : ''; ?>" 
                           href="<?php echo $base_url; ?>/list.php">
                           List
                        </a>
                    </li>
                    <!-- 新增 Recruit 链接 -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], 'recruit') !== false) ? 'active' : ''; ?>" 
                           href="<?php echo $base_url; ?>/recruitment.php">
                           Recruit
                        </a>
                    </li>
                    <li class="nav-item">
                       <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], 'forum') !== false) ? 'active' : ''; ?>" 
                          href="<?php echo $base_url; ?>/forum.php">
                          Forum
                      </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <?php if(isLoggedIn()): ?>
                        <!-- 登录后的购物车链接（无图标） -->
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $base_url; ?>/cart/checkout.php">
                                Cart
                            </a>
                        </li>
                        <!-- 登录后的 Logout 链接（无图标） -->
                        <li class="nav-item">
                           <a class="nav-link" 
                             href="<?php echo $base_url; ?>/auth/logout.php">
                             Logout
                           </a>
                        </li>
                        <!-- 登录后的用户名（放在Logout后面，加括号） -->
                        <li class="nav-item">
                            <span class="nav-link text-muted">
                                (<?php 
                                if (isset($_SESSION['last_name']) && !empty($_SESSION['last_name'])) {
                                    echo htmlspecialchars($_SESSION['last_name']);
                                } else {
                                    echo 'User';
                                }
                                ?>)
                            </span>
                        </li>
                    <?php else: ?>
                        <!-- 未登录时的登录链接（无图标） -->
                        <li class="nav-item">
                           <a class="nav-link <?php echo ($current_page == 'login.php') ? 'active' : ''; ?>" 
                             href="<?php echo $base_url; ?>/auth/login.php">
                             Login
                           </a>
                        </li>
                       <!-- 未登录时的注册链接（无图标） -->
                      <li class="nav-item">
                           <a class="nav-link <?php echo ($current_page == 'register.php') ? 'active' : ''; ?>" 
                             href="https://helena1201.free.nf/123/register/">
                             Register
                           </a>
                       </li>
                       <!-- 未登录时的 Logout 链接（无图标） -->
                       <li class="nav-item">
                          <a class="nav-link" 
                           href="<?php echo $base_url; ?>/auth/logout.php">
                              Logout
                          </a>
                       </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <main class="main-content">