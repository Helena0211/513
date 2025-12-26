<?php
require_once 'config/config.php';

// 获取特色工作坊
try {
    $featured_query = "SELECT w.*, u.first_name, u.last_name, c.category_name 
                       FROM workshops w 
                       JOIN users u ON w.instructor_id = u.user_id 
                       JOIN categories c ON w.category_id = c.category_id 
                       WHERE w.status = 'published' 
                       ORDER BY w.created_at DESC 
                       LIMIT 6";
    $featured_stmt = $db->prepare($featured_query);
    $featured_stmt->execute();
    $featured_workshops = $featured_stmt->fetchAll();
} catch (PDOException $e) {
    $featured_workshops = [];
}

// 获取分类
try {
    $categories_query = "SELECT * FROM categories LIMIT 8";
    $categories_stmt = $db->prepare($categories_query);
    $categories_stmt->execute();
    $categories = $categories_stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// 从 JSON 文件获取产品数据用于展示
$products_json = file_get_contents('data/products.json');
$products_data = json_decode($products_json, true);
$products = $products_data['products'] ?? [];

// 提取分类
$product_categories = [];
foreach ($products as $product) {
    $category = $product['category'];
    if (!in_array($category, $product_categories)) {
        $product_categories[] = $category;
    }
}
?>

<?php include 'includes/header.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold">Learn, Create, Connect</h1>
                    <p class="lead">Discover hands-on workshops led by expert instructors. From pottery to coding, unlock your creativity with SkillCraft.</p>
                    <div class="mt-4">
                        <a href="products.php" class="btn btn-light btn-lg me-3">Explore Workshops</a>
                        <a href="https://helena1201.free.nf/123/register/" class="btn btn-outline-light btn-lg">Become an Instructor</a>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <div class="hero-image-placeholder bg-light rounded p-5">
                        <i class="fas fa-hands-helping fa-6x text-primary mb-3"></i>
                        <p class="text-muted">Workshop Experience Preview</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Open Source Integration Banner -->
    <section class="integration-banner">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="mb-0">
                        <i class="fas fa-code-branch me-2"></i>
                        Powered by Calendso - Open Source Scheduling Platform
                    </h5>
                </div>
                <div class="col-md-4 text-end">
                    <small>Self-hosted scheduling for complete data control</small>
                </div>
            </div>
        </div>
    </section>

    <!-- Categories Section -->
    <section class="categories-section py-5">
        <div class="container">
            <h2 class="text-center mb-4">Browse by Category</h2>
            <div class="row">
                <?php foreach (array_slice($product_categories, 0, 8) as $category): ?>
                <div class="col-md-3 col-6 mb-3">
                    <a href="products.php?category=<?php echo urlencode($category); ?>" class="category-card">
                        <i class="fas fa-palette fa-2x text-primary mb-3"></i>
                        <h5 class="card-title"><?php echo htmlspecialchars($category); ?></h5>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Featured Workshops -->
    <section class="featured-workshops py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-4">Featured Workshops</h2>
            <div class="row">
                <?php if (empty($products)): ?>
                    <div class="col-12 text-center">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No featured workshops available at the moment. Check back soon!
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach (array_slice($products, 0, 6) as $product): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card workshop-card h-100">
                            <div class="workshop-image-placeholder position-relative">
                                <?php if ($product['discount_percent'] > 0): ?>
                                    <span class="position-absolute top-0 start-0 badge bg-danger">
                                        <?php echo $product['discount_percent']; ?>% OFF
                                    </span>
                                <?php endif; ?>
                                
                                <?php if (!empty($product['picture'])): ?>
                                    <img src="<?php echo htmlspecialchars($product['picture']); ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                         class="img-fluid rounded" 
                                         style="height: 200px; object-fit: cover; width: 100%;">
                                <?php else: ?>
                                    <i class="fas fa-graduation-cap fa-3x text-muted mb-2"></i>
                                    <p class="text-muted mb-0">Workshop Image</p>
                                <?php endif; ?>
                            </div>
                            <div class="card-body d-flex flex-column">
                                <div class="mb-2">
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($product['skill_level'] ?? 'All Levels'); ?></span>
                                </div>
                                <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                <p class="card-text flex-grow-1"><?php echo htmlspecialchars(substr($product['description'], 0, 100)) . '...'; ?></p>
                                
                                <div class="workshop-meta mb-3">
                                    <small class="text-muted d-block">
                                        <i class="fas fa-user me-1"></i>
                                        <?php echo htmlspecialchars($product['instructor'] ?? 'Expert Instructor'); ?>
                                    </small>
                                    <small class="text-muted d-block">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo htmlspecialchars($product['duration'] ?? '2 hours'); ?>
                                    </small>
                                </div>
                                
                                <div class="price-section mt-auto">
                                    <?php if ($product['discount_percent'] > 0): ?>
                                        <?php
                                        $original_price = $product['price'];
                                        $discounted_price = $original_price * (1 - $product['discount_percent'] / 100);
                                        ?>
                                        <div class="d-flex align-items-center">
                                            <span class="h5 text-primary mb-0">$<?php echo number_format($discounted_price, 2); ?></span>
                                            <span class="text-muted text-decoration-line-through ms-2">$<?php echo number_format($original_price, 2); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="h5 text-primary">$<?php echo number_format($product['price'], 2); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <!-- 删除整个 card-footer 部分，移除 View Details 按钮 -->
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <!-- 保留页面底部的 View All Workshops 按钮 -->
            <div class="text-center mt-4">
                <a href="products.php" class="btn btn-outline-primary btn-lg">View All Workshops</a>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section py-5">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-card">
                        <i class="fas fa-graduation-cap text-primary"></i>
                        <h3 class="text-primary fw-bold"><?php echo count($products); ?>+</h3>
                        <p class="text-muted">Workshops</p>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-card">
                        <i class="fas fa-chalkboard-teacher text-success"></i>
                        <h3 class="text-success fw-bold">25+</h3>
                        <p class="text-muted">Expert Instructors</p>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-card">
                        <i class="fas fa-users text-info"></i>
                        <h3 class="text-info fw-bold">1,000+</h3>
                        <p class="text-muted">Happy Learners</p>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-card">
                        <i class="fas fa-star text-warning"></i>
                        <h3 class="text-warning fw-bold">4.9</h3>
                        <p class="text-muted">Average Rating</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Community CTA -->
    <section class="community-cta">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h3>Join Our Creative Community</h3>
                    <p class="lead mb-0">Share your projects, ask questions, and connect with fellow learners and instructors.</p>
                </div>
               
            </div>
        </div>
    </section>

<?php include 'includes/footer.php'; ?>