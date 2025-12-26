<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$base_url = 'https://helena1201.free.nf/513/week9513';

// Check if user is logged in
if (!isset($_SESSION['subscriber_id']) && !isset($_SESSION['user_id'])) {
    header("Location: " . $base_url . "/auth/login.php?redirect=forum.php");
    exit;
}

// Set unified user_id variable
if (!isset($_SESSION['user_id']) && isset($_SESSION['subscriber_id'])) {
    $_SESSION['user_id'] = $_SESSION['subscriber_id'];
}

// Database configuration
$host = 'sql210.infinityfree.com';
$dbname = 'if0_40378146_wp579';
$username = 'if0_40378146';
$password = 'nQuyY3nfXVA';

// Check if viewing single post detail
$view_post_id = isset($_GET['view_post']) ? intval($_GET['view_post']) : 0;
$current_view_post = null;
$current_post_replies = [];

// Process new post submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_post'])) {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $category = trim($_POST['category'] ?? 'General');
    
    if (!empty($title) && !empty($content)) {
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->prepare("
                INSERT INTO wpej_forum_posts (user_id, title, content, category) 
                VALUES (?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $_SESSION['user_id'],
                $title,
                $content,
                $category
            ]);
            
            $_SESSION['forum_message'] = 'success:Post created successfully!';
            header("Location: forum.php");
            exit;
            
        } catch (PDOException $e) {
            $error = "Error creating post: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}

// Process reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reply'])) {
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $content = trim($_POST['reply_content'] ?? '');
    
    if (!empty($content) && $post_id > 0) {
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Add reply
            $stmt = $pdo->prepare("
                INSERT INTO wpej_forum_replies (post_id, user_id, content) 
                VALUES (?, ?, ?)
            ");
            
            $stmt->execute([$post_id, $_SESSION['user_id'], $content]);
            
            // Update post reply count
            $updateStmt = $pdo->prepare("
                UPDATE wpej_forum_posts 
                SET replies = replies + 1, updated_at = NOW() 
                WHERE id = ?
            ");
            
            $updateStmt->execute([$post_id]);
            
            $_SESSION['forum_message'] = 'success:Reply posted successfully!';
            header("Location: forum.php" . ($post_id ? "#post-" . $post_id : ""));
            exit;
            
        } catch (PDOException $e) {
            $error = "Error posting reply: " . $e->getMessage();
        }
    } else {
        $error = "Please enter your reply.";
    }
}

// Process like
if (isset($_GET['like_post'])) {
    $post_id = intval($_GET['like_post']);
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Update post likes
        $stmt = $pdo->prepare("
            UPDATE wpej_forum_posts 
            SET likes = likes + 1 
            WHERE id = ?
        ");
        
        $stmt->execute([$post_id]);
        
        $_SESSION['forum_message'] = 'success:Thanks for liking this discussion!';
        header("Location: forum.php#post-" . $post_id);
        exit;
        
    } catch (PDOException $e) {
        // Silent failure
    }
}

// Process search and category filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$selected_category = isset($_GET['category']) ? $_GET['category'] : '';

// If viewing single post, get post details and replies
if ($view_post_id > 0) {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Increase view count
        $viewStmt = $pdo->prepare("
            UPDATE wpej_forum_posts 
            SET views = views + 1 
            WHERE id = ?
        ");
        $viewStmt->execute([$view_post_id]);
        
        // Get post details
        $stmt = $pdo->prepare("
            SELECT fp.*, 
                   CONCAT(fs.first_name, ' ', fs.last_name) as author_name,
                   fs.email as author_email
            FROM wpej_forum_posts fp
            LEFT JOIN wpej_fc_subscribers fs ON fp.user_id = fs.id
            WHERE fp.id = ? AND fp.status = 'active'
        ");
        
        $stmt->execute([$view_post_id]);
        $current_view_post = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($current_view_post) {
            // Get replies for this post
            $repliesStmt = $pdo->prepare("
                SELECT fr.*, 
                       CONCAT(fs.first_name, ' ', fs.last_name) as author_name,
                       fs.email as author_email
                FROM wpej_forum_replies fr
                LEFT JOIN wpej_fc_subscribers fs ON fr.user_id = fs.id
                WHERE fr.post_id = ? AND fr.status = 'active'
                ORDER BY fr.created_at ASC
            ");
            
            $repliesStmt->execute([$view_post_id]);
            $current_post_replies = $repliesStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
    } catch (PDOException $e) {
        $error = "Error loading discussion: " . $e->getMessage();
    }
}

// Generate 20 sample posts (if database is empty)
function generateSamplePosts($pdo, $user_id) {
    $sample_posts = [
        [
            'title' => 'Welcome to SkillCraft Workshops Forum!',
            'content' => 'This is the official discussion forum for SkillCraft Workshops. Feel free to share your thoughts, ask questions, and connect with other learners.',
            'category' => 'Announcements'
        ],
        [
            'title' => 'Best Photography Workshop for Beginners',
            'content' => 'I recently attended the photography workshop and it was amazing! The instructor was very knowledgeable. Does anyone have recommendations for intermediate level courses?',
            'category' => 'Photography'
        ],
        [
            'title' => 'Web Development Career Path Advice',
            'content' => 'I want to transition into web development. Which workshops would you recommend for someone with basic HTML/CSS knowledge?',
            'category' => 'Web Development'
        ],
        [
            'title' => 'Graphic Design Software Recommendations',
            'content' => 'What software do you use for graphic design? I\'m looking for affordable alternatives to Adobe Creative Suite.',
            'category' => 'Graphic Design'
        ],
        [
            'title' => 'Digital Marketing Trends 2024',
            'content' => 'What are the most important digital marketing trends to watch out for in 2024? Share your insights!',
            'category' => 'Digital Marketing'
        ],
        [
            'title' => 'Workshop Feedback: Python for Data Science',
            'content' => 'The Python workshop was comprehensive but I felt it moved too fast. Anyone else had similar experience?',
            'category' => 'Programming'
        ],
        [
            'title' => 'Looking for Study Partners - UI/UX Design',
            'content' => 'I\'m currently taking the UI/UX design workshop. Would anyone like to form a study group?',
            'category' => 'UI/UX Design'
        ],
        [
            'title' => 'Video Editing Workshop Review',
            'content' => 'The video editing workshop exceeded my expectations! The hands-on projects were very helpful.',
            'category' => 'Video Editing'
        ],
        [
            'title' => 'Time Management Tips for Online Learning',
            'content' => 'How do you manage your time effectively when taking multiple workshops?',
            'category' => 'General'
        ],
        [
            'title' => 'Recommended Resources for SEO Learning',
            'content' => 'Apart from the SEO workshop, what other resources would you recommend for mastering SEO?',
            'category' => 'SEO'
        ],
        [
            'title' => 'Mobile App Development Roadmap',
            'content' => 'What\'s the best path to learn mobile app development? React Native vs Flutter vs Native?',
            'category' => 'Mobile Development'
        ],
        [
            'title' => 'Workshop Completion Certificates',
            'content' => 'Do the workshop completion certificates help with job applications?',
            'category' => 'Career'
        ],
        [
            'title' => 'Content Creation Equipment Setup',
            'content' => 'What\'s your content creation setup? Looking for budget-friendly equipment recommendations.',
            'category' => 'Content Creation'
        ],
        [
            'title' => 'Social Media Marketing Strategies',
            'content' => 'Share your most effective social media marketing strategies for small businesses.',
            'category' => 'Social Media'
        ],
        [
            'title' => 'E-commerce Website Optimization Tips',
            'content' => 'What are your top tips for optimizing e-commerce websites for better conversion?',
            'category' => 'E-commerce'
        ],
        [
            'title' => 'Freelancing After Completing Workshops',
            'content' => 'Has anyone started freelancing after completing the workshops? Share your experience!',
            'category' => 'Freelancing'
        ],
        [
            'title' => 'Workshop Pricing Feedback',
            'content' => 'What are your thoughts on the workshop pricing? Are there any discounts available?',
            'category' => 'Feedback'
        ],
        [
            'title' => 'Building a Portfolio Website',
            'content' => 'Looking for tips on building an impressive portfolio website to showcase workshop projects.',
            'category' => 'Portfolio'
        ],
        [
            'title' => 'Networking Opportunities',
            'content' => 'Are there any networking events or community meetups for SkillCraft learners?',
            'category' => 'Community'
        ],
        [
            'title' => 'Workshop Suggestions for Future',
            'content' => 'What topics would you like to see covered in future workshops?',
            'category' => 'Suggestions'
        ]
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO wpej_forum_posts (user_id, title, content, category, created_at) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $date = date('Y-m-d H:i:s');
    foreach ($sample_posts as $post) {
        $post_date = date('Y-m-d H:i:s', strtotime("-" . rand(1, 30) . " days"));
        $stmt->execute([
            $user_id,
            $post['title'],
            $post['content'],
            $post['category'],
            $post_date
        ]);
    }
}
?>
<?php include 'includes/header.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discussion Forum - SkillCraft Workshops</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f8fafc;
            min-height: 100vh;
        }
        
        .forum-wrapper {
            display: flex;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            gap: 25px;
        }
        
        /* Left Sidebar Styles */
        .sidebar {
            width: 280px;
            flex-shrink: 0;
        }
        
        .sidebar-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }
        
        .sidebar-section h3 {
            color: #2d3748;
            font-size: 1.2rem;
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .sidebar-section h3 i {
            color: #667eea;
        }
        
        /* New Post Sidebar Form */
        .new-post-sidebar .form-group {
            margin-bottom: 18px;
        }
        
        .new-post-sidebar label {
            display: block;
            margin-bottom: 6px;
            color: #4a5568;
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .new-post-sidebar input,
        .new-post-sidebar select,
        .new-post-sidebar textarea {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .new-post-sidebar input:focus,
        .new-post-sidebar select:focus,
        .new-post-sidebar textarea:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .new-post-sidebar textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .submit-sidebar-btn {
            background: linear-gradient(90deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .submit-sidebar-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        /* Category Filter Styles */
        .category-list {
            list-style: none;
        }
        
        .category-item {
            margin-bottom: 10px;
        }
        
        .category-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            background: #f7fafc;
            border-radius: 8px;
            color: #4a5568;
            text-decoration: none;
            transition: all 0.3s;
            border: 1px solid #e2e8f0;
        }
        
        .category-link:hover {
            background: #edf2f7;
            border-color: #cbd5e0;
        }
        
        .category-link.active {
            background: linear-gradient(90deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            color: #667eea;
            border-color: #667eea;
            font-weight: 600;
        }
        
        .category-count {
            margin-left: auto;
            background: #e2e8f0;
            color: #4a5568;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .category-link.active .category-count {
            background: #667eea;
            color: white;
        }
        
        /* Main Content Styles */
        .main-content {
            flex: 1;
        }
        
        /* Forum Header */
        .forum-header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            text-align: center;
        }
        
        .forum-header h1 {
            color: #2d3748;
            font-size: 2.2rem;
            margin-bottom: 15px;
            font-weight: 700;
        }
        
        .forum-header p {
            color: #718096;
            font-size: 1.1rem;
            margin-bottom: 25px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .stats {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .stat-box {
            background: #f7fafc;
            padding: 12px 20px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            text-align: center;
            min-width: 120px;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #718096;
            font-size: 0.85rem;
        }
        
        /* Posts Container */
        .posts-container {
            background: white;
            border-radius: 12px;
            padding: 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        
        .posts-header {
            padding: 25px 30px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        
        .posts-header h2 {
            color: #2d3748;
            font-size: 1.5rem;
            margin-bottom: 20px;
        }
        
        .filter-controls {
            display: flex;
            gap: 10px;
            align-items: center;
            justify-content: space-between;
        }
        
        .search-box {
            position: relative;
            flex: 1;
            max-width: 400px;
        }
        
        .search-box input {
            padding: 10px 15px 10px 40px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            width: 100%;
            font-size: 1rem;
            transition: all 0.3s;
            background: white;
        }
        
        .search-box input:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
        }
        
        select {
            padding: 10px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            background: white;
            font-size: 1rem;
            cursor: pointer;
            min-width: 150px;
        }
        
        select:focus {
            border-color: #667eea;
            outline: none;
        }
        
        /* Post Cards */
        .post-card {
            background: white;
            padding: 25px 30px;
            border-bottom: 1px solid #e2e8f0;
            transition: all 0.3s;
        }
        
        .post-card:last-child {
            border-bottom: none;
        }
        
        .post-card:hover {
            background: #f8fafc;
        }
        
        .post-header {
            margin-bottom: 20px;
        }
        
        .post-category {
            display: inline-block;
            background: #e2e8f0;
            color: #4a5568;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .post-title {
            font-size: 1.4rem;
            color: #2d3748;
            margin-bottom: 10px;
            font-weight: 600;
            line-height: 1.4;
        }
        
        .post-date {
            color: #a0aec0;
            font-size: 0.9rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .post-date i {
            font-size: 0.85rem;
        }
        
        .post-content {
            color: #4a5568;
            line-height: 1.7;
            margin-bottom: 20px;
            font-size: 1.05rem;
            padding: 15px 0;
            border-bottom: 1px solid #f1f1f1;
        }
        
        .post-stats {
            display: flex;
            gap: 25px;
            margin-bottom: 20px;
            color: #718096;
            font-size: 0.95rem;
        }
        
        .stat {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .stat i {
            font-size: 1.1rem;
        }
        
        /* Author Info */
        .author-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .author-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .author-details h4 {
            color: #2d3748;
            margin-bottom: 3px;
            font-size: 1rem;
        }
        
        .author-details p {
            color: #718096;
            font-size: 0.85rem;
        }
        
        /* Reply Section */
        .reply-section {
            margin-top: 25px;
            border-top: 1px solid #e2e8f0;
            padding-top: 25px;
        }
        
        .reply-form-container {
            background: #f8fafc;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
        }
        
        .reply-form-container h4 {
            color: #2d3748;
            margin-bottom: 15px;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .reply-form textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            min-height: 120px;
            resize: vertical;
            transition: all 0.3s;
            background: white;
            margin-bottom: 15px;
        }
        
        .reply-form textarea:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .submit-reply-btn {
            background: linear-gradient(90deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .submit-reply-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        /* Replies List */
        .replies-list {
            margin-top: 25px;
        }
        
        .replies-list h4 {
            color: #2d3748;
            margin-bottom: 15px;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .reply-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid #e2e8f0;
            position: relative;
        }
        
        .reply-card:last-child {
            margin-bottom: 0;
        }
        
        .reply-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }
        
        .reply-author-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .reply-author-name {
            font-weight: 600;
            color: #2d3748;
            font-size: 0.95rem;
        }
        
        .reply-date {
            color: #a0aec0;
            font-size: 0.85rem;
            margin-left: auto;
        }
        
        .reply-content {
            color: #4a5568;
            line-height: 1.6;
            font-size: 1rem;
            padding-left: 47px;
        }
        
        /* Messages */
        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .message.success {
            background: #c6f6d5;
            color: #22543d;
            border-left: 4px solid #38a169;
        }
        
        .message.error {
            background: #fed7d7;
            color: #742a2a;
            border-left: 4px solid #e53e3e;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .page-btn {
            padding: 8px 15px;
            border: 2px solid #e2e8f0;
            background: white;
            border-radius: 6px;
            color: #4a5568;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        
        .page-btn:hover {
            border-color: #667eea;
            color: #667eea;
        }
        
        .page-btn.active {
            background: #667eea;
            border-color: #667eea;
            color: white;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #cbd5e0;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            font-size: 1.4rem;
            margin-bottom: 10px;
            color: #4a5568;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .forum-wrapper {
                flex-direction: column;
                padding: 15px;
            }
            
            .sidebar {
                width: 100%;
            }
            
            .sidebar-section {
                padding: 20px;
            }
        }
        
        @media (max-width: 768px) {
            .forum-header {
                padding: 20px;
            }
            
            .forum-header h1 {
                font-size: 1.8rem;
            }
            
            .stats {
                flex-direction: column;
                gap: 10px;
            }
            
            .stat-box {
                width: 100%;
                max-width: 200px;
            }
            
            .filter-controls {
                flex-direction: column;
                gap: 15px;
            }
            
            .search-box {
                max-width: 100%;
            }
            
            select {
                width: 100%;
            }
            
            .post-card {
                padding: 20px;
            }
            
            .post-title {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <div class="forum-wrapper">
        <!-- Left Sidebar -->
        <div class="sidebar">
            <!-- New Discussion Form -->
            <div class="sidebar-section new-post-sidebar">
                <h3><i class="fas fa-plus-circle"></i> New Discussion</h3>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="sidebar_title">Title *</label>
                        <input type="text" id="sidebar_title" name="title" required 
                               placeholder="Enter discussion title">
                    </div>
                    
                    <div class="form-group">
                        <label for="sidebar_category">Category *</label>
                        <select id="sidebar_category" name="category" required>
                            <option value="">Select category</option>
                            <option value="General">General Discussion</option>
                            <option value="Web Development">Web Development</option>
                            <option value="Digital Marketing">Digital Marketing</option>
                            <option value="Graphic Design">Graphic Design</option>
                            <option value="Photography">Photography</option>
                            <option value="Programming">Programming</option>
                            <option value="UI/UX Design">UI/UX Design</option>
                            <option value="Video Editing">Video Editing</option>
                            <option value="Content Creation">Content Creation</option>
                            <option value="SEO">SEO</option>
                            <option value="Social Media">Social Media</option>
                            <option value="E-commerce">E-commerce</option>
                            <option value="Mobile Development">Mobile Development</option>
                            <option value="Career">Career Advice</option>
                            <option value="Portfolio">Portfolio</option>
                            <option value="Freelancing">Freelancing</option>
                            <option value="Feedback">Workshop Feedback</option>
                            <option value="Suggestions">Suggestions</option>
                            <option value="Community">Community</option>
                            <option value="Announcements">Announcements</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="sidebar_content">Content *</label>
                        <textarea id="sidebar_content" name="content" required 
                                  placeholder="Share your thoughts..."></textarea>
                    </div>
                    
                    <button type="submit" name="submit_post" class="submit-sidebar-btn">
                        <i class="fas fa-paper-plane me-2"></i>Publish Discussion
                    </button>
                </form>
            </div>
            
            <!-- Category Filter -->
            <div class="sidebar-section">
                <h3><i class="fas fa-filter"></i> Filter by Category</h3>
                <ul class="category-list">
                    <?php
                    try {
                        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        
                        // Get all categories and their counts
                        $stmt = $pdo->query("
                            SELECT category, COUNT(*) as count 
                            FROM wpej_forum_posts 
                            WHERE status = 'active' 
                            GROUP BY category 
                            ORDER BY category ASC
                        ");
                        
                        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Add "All Categories" option
                        $allStmt = $pdo->query("SELECT COUNT(*) as total FROM wpej_forum_posts WHERE status = 'active'");
                        $allCount = $allStmt->fetch(PDO::FETCH_ASSOC)['total'];
                        
                        // All Categories link
                        $is_all_active = empty($selected_category) ? 'active' : '';
                        echo '<li class="category-item">';
                        echo '<a href="forum.php" class="category-link ' . $is_all_active . '">';
                        echo 'All Discussions';
                        echo '<span class="category-count">' . $allCount . '</span>';
                        echo '</a>';
                        echo '</li>';
                        
                        // Category links
                        foreach ($categories as $category) {
                            $is_active = ($selected_category === $category['category']) ? 'active' : '';
                            
                            echo '<li class="category-item">';
                            echo '<a href="forum.php?category=' . urlencode($category['category']) . 
                                 (!empty($search) ? '&search=' . urlencode($search) : '') .
                                 (!empty($sort_by) ? '&sort=' . urlencode($sort_by) : '') . '" ' .
                                 'class="category-link ' . $is_active . '">';
                            echo htmlspecialchars($category['category']);
                            echo '<span class="category-count">' . $category['count'] . '</span>';
                            echo '</a>';
                            echo '</li>';
                        }
                        
                    } catch (PDOException $e) {
                        echo '<li class="category-item">';
                        echo '<a href="#" class="category-link">';
                        echo 'Error loading categories';
                        echo '</a>';
                        echo '</li>';
                    }
                    ?>
                </ul>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Forum Header -->
            <div class="forum-header">
                <h1>📝 Discussion Forum</h1>
                <p>Connect with other learners, share experiences, ask questions, and discuss topics related to SkillCraft Workshops.</p>
                
                <div class="stats">
                    <div class="stat-box">
                        <div class="stat-number">20+</div>
                        <div class="stat-label">Active Discussions</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number">50+</div>
                        <div class="stat-label">Community Members</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number">24/7</div>
                        <div class="stat-label">Support Available</div>
                    </div>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if (isset($_SESSION['forum_message'])): 
                $message = explode(':', $_SESSION['forum_message'], 2);
                $type = $message[0];
                $text = $message[1] ?? '';
            ?>
                <div class="message <?php echo $type; ?>">
                    <i class="fas fa-<?php echo $type === 'success' ? 'check-circle' : 'info-circle'; ?>"></i>
                    <span><?php echo htmlspecialchars($text); ?></span>
                </div>
                <?php unset($_SESSION['forum_message']); ?>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <!-- Posts Container -->
            <div class="posts-container">
                <div class="posts-header">
                    <h2><?php echo empty($selected_category) ? 'Recent Discussions' : htmlspecialchars($selected_category) . ' Discussions'; ?></h2>
                    
                    <div class="filter-controls">
                        <form method="GET" action="" class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="Search discussions..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <?php if (!empty($selected_category)): ?>
                                <input type="hidden" name="category" value="<?php echo htmlspecialchars($selected_category); ?>">
                            <?php endif; ?>
                        </form>
                        
                        <select name="sort" onchange="this.form.submit()">
                            <option value="newest" <?php echo $sort_by === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="popular" <?php echo $sort_by === 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                        </select>
                    </div>
                </div>
                
                <?php
                try {
                    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    // Check if there are posts, if not generate sample posts
                    $checkStmt = $pdo->query("SELECT COUNT(*) as count FROM wpej_forum_posts");
                    $postCount = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
                    
                    if ($postCount == 0) {
                        // Get a user ID for creating sample posts
                        $userStmt = $pdo->query("SELECT id FROM wpej_fc_subscribers LIMIT 1");
                        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($user) {
                            generateSamplePosts($pdo, $user['id']);
                            $checkStmt = $pdo->query("SELECT COUNT(*) as count FROM wpej_forum_posts");
                            $postCount = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
                        }
                    }
                    
                    // Build query
                    $query = "
                        SELECT fp.*, 
                               CONCAT(fs.first_name, ' ', fs.last_name) as author_name,
                               fs.email as author_email
                        FROM wpej_forum_posts fp
                        LEFT JOIN wpej_fc_subscribers fs ON fp.user_id = fs.id
                        WHERE fp.status = 'active'
                    ";
                    
                    $params = [];
                    
                    if (!empty($search)) {
                        $query .= " AND (fp.title LIKE ? OR fp.content LIKE ?)";
                        $searchParam = "%$search%";
                        $params[] = $searchParam;
                        $params[] = $searchParam;
                    }
                    
                    if (!empty($selected_category)) {
                        $query .= " AND fp.category = ?";
                        $params[] = $selected_category;
                    }
                    
                    // Sorting
                    switch ($sort_by) {
                        case 'popular':
                            $query .= " ORDER BY fp.replies DESC, fp.likes DESC, fp.created_at DESC";
                            break;
                        default:
                            $query .= " ORDER BY fp.created_at DESC";
                    }
                    
                    // Pagination
                    $perPage = 10;
                    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                    $offset = ($page - 1) * $perPage;
                    
                    // Get total count
                    $countQuery = "SELECT COUNT(*) as total FROM (" . str_replace("fp.*, CONCAT(fs.first_name, ' ', fs.last_name) as author_name, fs.email as author_email", "fp.id", $query) . ") as temp";
                    $countStmt = $pdo->prepare($countQuery);
                    $countStmt->execute($params);
                    $totalPosts = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
                    $totalPages = ceil($totalPosts / $perPage);
                    
                    // Add pagination limit
                    $query .= " LIMIT $perPage OFFSET $offset";
                    
                    // Execute query
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);
                    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($posts) > 0) {
                        foreach ($posts as $post) {
                            // Get replies for this post
                            $repliesStmt = $pdo->prepare("
                                SELECT fr.*, 
                                       CONCAT(fs.first_name, ' ', fs.last_name) as author_name,
                                       fs.email as author_email
                                FROM wpej_forum_replies fr
                                LEFT JOIN wpej_fc_subscribers fs ON fr.user_id = fs.id
                                WHERE fr.post_id = ? AND fr.status = 'active'
                                ORDER BY fr.created_at ASC
                            ");
                            
                            $repliesStmt->execute([$post['id']]);
                            $replies = $repliesStmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            $postDate = date('F j, Y \a\t g:i A', strtotime($post['created_at']));
                            $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($post['author_name']) . "&background=667eea&color=fff";
                            ?>
                            <div class="post-card" id="post-<?php echo $post['id']; ?>">
                                <div class="post-header">
                                    <span class="post-category"><?php echo htmlspecialchars($post['category']); ?></span>
                                    <h3 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h3>
                                    <div class="post-date">
                                        <i class="far fa-clock"></i><?php echo $postDate; ?>
                                    </div>
                                </div>
                                
                                <div class="post-content">
                                    <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                                </div>
                                
                                <div class="post-stats">
                                    <span class="stat">
                                        <i class="far fa-comment"></i> <?php echo $post['replies']; ?> replies
                                    </span>
                                    <span class="stat">
                                        <i class="far fa-heart"></i> <?php echo $post['likes']; ?> likes
                                    </span>
                                </div>
                                
                                <!-- Author Info -->
                                <div class="author-info">
                                    <img src="<?php echo $avatarUrl; ?>" alt="<?php echo htmlspecialchars($post['author_name']); ?>" class="author-avatar">
                                    <div class="author-details">
                                        <h4>Posted by <?php echo htmlspecialchars($post['author_name']); ?></h4>
                                        <p>Member since <?php echo date('F Y', strtotime($post['created_at'])); ?></p>
                                    </div>
                                    <a href="?like_post=<?php echo $post['id']; ?>&category=<?php echo urlencode($selected_category); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort_by); ?>#post-<?php echo $post['id']; ?>" style="margin-left: auto; text-decoration: none;">
                                        <button style="background: #fed7d7; color: #742a2a; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 5px; font-weight: 600;">
                                            <i class="fas fa-heart"></i> Like
                                        </button>
                                    </a>
                                </div>
                                
                                <!-- Reply Section -->
                                <div class="reply-section">
                                    <!-- Reply Form -->
                                    <div class="reply-form-container">
                                        <h4>Add Your Reply</h4>
                                        <form class="reply-form" method="POST" action="">
                                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                            <textarea name="reply_content" required placeholder="Share your thoughts, answer questions, or contribute to the discussion..."></textarea>
                                            <button type="submit" name="submit_reply" class="submit-reply-btn">
                                                <i class="fas fa-paper-plane me-2"></i>Post Reply
                                            </button>
                                        </form>
                                    </div>
                                    
                                    <!-- Replies List -->
                                    <?php if (count($replies) > 0): ?>
                                        <div class="replies-list">
                                            <h4>Replies (<?php echo count($replies); ?>)</h4>
                                            <?php foreach ($replies as $reply): ?>
                                                <?php
                                                $replyDate = date('F j, Y \a\t g:i A', strtotime($reply['created_at']));
                                                $replyAvatar = "https://ui-avatars.com/api/?name=" . urlencode($reply['author_name']) . "&background=764ba2&color=fff";
                                                ?>
                                                <div class="reply-card">
                                                    <div class="reply-header">
                                                        <img src="<?php echo $replyAvatar; ?>" alt="<?php echo htmlspecialchars($reply['author_name']); ?>" class="reply-author-avatar">
                                                        <div class="reply-author-name"><?php echo htmlspecialchars($reply['author_name']); ?></div>
                                                        <div class="reply-date"><?php echo $replyDate; ?></div>
                                                    </div>
                                                    <div class="reply-content">
                                                        <?php echo nl2br(htmlspecialchars($reply['content'])); ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="text-align: center; padding: 20px; color: #a0aec0; font-style: italic;">
                                            <i class="far fa-comment-dots" style="font-size: 1.2rem; margin-right: 5px;"></i>
                                            No replies yet. Be the first to reply!
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php
                        }
                        
                        // Pagination
                        if ($totalPages > 1) {
                            echo '<div class="pagination">';
                            
                            if ($page > 1) {
                                echo '<a href="?page=' . ($page - 1) . 
                                     (!empty($search) ? '&search=' . urlencode($search) : '') .
                                     (!empty($sort_by) ? '&sort=' . urlencode($sort_by) : '') .
                                     (!empty($selected_category) ? '&category=' . urlencode($selected_category) : '') .
                                     '" class="page-btn">Previous</a>';
                            }
                            
                            for ($i = 1; $i <= $totalPages; $i++) {
                                if ($i == $page) {
                                    echo '<span class="page-btn active">' . $i . '</span>';
                                } elseif ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)) {
                                    echo '<a href="?page=' . $i .
                                         (!empty($search) ? '&search=' . urlencode($search) : '') .
                                         (!empty($sort_by) ? '&sort=' . urlencode($sort_by) : '') .
                                         (!empty($selected_category) ? '&category=' . urlencode($selected_category) : '') .
                                         '" class="page-btn">' . $i . '</a>';
                                } elseif ($i == $page - 3 || $i == $page + 3) {
                                    echo '<span class="page-btn">...</span>';
                                }
                            }
                            
                            if ($page < $totalPages) {
                                echo '<a href="?page=' . ($page + 1) .
                                     (!empty($search) ? '&search=' . urlencode($search) : '') .
                                     (!empty($sort_by) ? '&sort=' . urlencode($sort_by) : '') .
                                     (!empty($selected_category) ? '&category=' . urlencode($selected_category) : '') .
                                     '" class="page-btn">Next</a>';
                            }
                            
                            echo '</div>';
                        }
                        
                    } else {
                        echo '<div class="empty-state">';
                        echo '<i class="far fa-comments"></i>';
                        echo '<h3>No discussions found</h3>';
                        echo '<p>Be the first to start a discussion in this category!</p>';
                        echo '</div>';
                    }
                    
                } catch (PDOException $e) {
                    echo '<div class="message error">';
                    echo '<i class="fas fa-exclamation-circle"></i>';
                    echo '<span>Error loading discussions: ' . htmlspecialchars($e->getMessage()) . '</span>';
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-submit search on Enter
        document.querySelector('.search-box input')?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.target.closest('form').submit();
            }
        });
        
        // Scroll to post if hash exists
        document.addEventListener('DOMContentLoaded', function() {
            if (window.location.hash) {
                const target = document.querySelector(window.location.hash);
                if (target) {
                    setTimeout(() => {
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 100);
                }
            }
        });
        
        // Character counter for sidebar content
        const sidebarContent = document.getElementById('sidebar_content');
        if (sidebarContent) {
            const counter = document.createElement('div');
            counter.style.color = '#718096';
            counter.style.fontSize = '0.85rem';
            counter.style.textAlign = 'right';
            counter.style.marginTop = '5px';
            sidebarContent.parentNode.insertBefore(counter, sidebarContent.nextSibling);
            
            function updateCounter() {
                const length = sidebarContent.value.length;
                counter.textContent = `${length} characters`;
                counter.style.color = length < 50 ? '#e53e3e' : length < 100 ? '#d69e2e' : '#38a169';
            }
            
            sidebarContent.addEventListener('input', updateCounter);
            updateCounter();
        }
    </script>
</body>
</html>
<?php include 'includes/footer.php'; ?>