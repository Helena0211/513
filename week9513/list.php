<?php
// 启动会话并设置基础URL
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$base_url = 'https://helena1201.free.nf/513/week9513';

// 数据库连接配置
$host = 'sql210.infinityfree.com';
$dbname = 'if0_40378146_wp579';
$username = 'if0_40378146';
$password = 'nQuyY3nfXVA';
?>
<?php include 'includes/header.php'; ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
        }
        
        /* Subscribers List Section */
        .subscribers-section {
            margin: 3rem auto;
            max-width: 1200px;
            background-color: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .subscribers-section h2 {
            text-align: center;
            margin-bottom: 2rem;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .subscribers-table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            font-size: 0.9rem;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .subscribers-table th,
        .subscribers-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .subscribers-table th {
            background-color: #34495e;
            color: white;
            font-weight: bold;
            white-space: nowrap;
        }
        
        .subscribers-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .status-pending {
            color: #e67e22;
            font-weight: bold;
        }
        
        .status-confirmed {
            color: #27ae60;
            font-weight: bold;
        }
        
        /* 按钮样式 - 修改为正常大小 */
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .action-btn {
            padding: 0.6rem 1.2rem;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            justify-content: center; /* 文字居中 */
            transition: background-color 0.3s, transform 0.2s;
            border: none;
            cursor: pointer;
            min-width: 140px;
            font-size: 0.9rem;
            text-align: center;
        }
        
        .subscribe-btn {
            background-color: #e74c3c;
            color: white;
        }
        
        .subscribe-btn:hover {
            background-color: #c0392b;
            color: white;
            transform: translateY(-2px);
        }
        
        .back-btn {
            background-color: #3498db;
            color: white;
        }
        
        .back-btn:hover {
            background-color: #2980b9;
            color: white;
            transform: translateY(-2px);
        }
        
        .export-btn {
            background-color: #2ecc71;
            color: white;
        }
        
        .export-btn:hover {
            background-color: #27ae60;
            color: white;
            transform: translateY(-2px);
        }
        
        .refresh-btn {
            background-color: #9b59b6;
            color: white;
        }
        
        .refresh-btn:hover {
            background-color: #8e44ad;
            color: white;
            transform: translateY(-2px);
        }
        
        /* 为小屏幕添加响应式 */
        @media (max-width: 768px) {
            .subscribers-section {
                padding: 1.5rem;
                margin: 1.5rem;
            }
            
            .subscribers-table {
                font-size: 0.8rem;
            }
            
            .subscribers-table th,
            .subscribers-table td {
                padding: 0.5rem;
            }
            
            .action-buttons {
                gap: 0.8rem;
            }
            
            .action-btn {
                min-width: 120px;
                padding: 0.5rem 1rem;
                font-size: 0.85rem;
            }
        }
        
        @media (max-width: 576px) {
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .action-btn {
                width: 200px;
                max-width: 100%;
            }
        }
        
        /* 搜索和过滤样式 */
        .filter-section {
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .search-box {
            flex: 1;
            min-width: 250px;
        }
        
        .search-box input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .filter-options {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 0.5rem 1rem;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .filter-btn:hover {
            background-color: #e9ecef;
        }
        
        .filter-btn.active {
            background-color: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        /* 分页样式 */
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
            gap: 0.5rem;
        }
        
        .page-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            background-color: white;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .page-btn:hover {
            background-color: #f8f9fa;
        }
        
        .page-btn.active {
            background-color: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        /* 表格上方空白区域 */
        .table-header-area {
            margin-bottom: 1.5rem;
        }
    </style>

    <main class="container py-4">
        <!-- Subscribers List Section -->
        <section class="subscribers-section">
            <h2>Current Subscribers</h2>
            
            <?php
            try {
                $pdo_display = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
                $pdo_display->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // 获取总记录数
                $totalStmt = $pdo_display->query("SELECT COUNT(*) as total FROM wpej_fc_subscribers");
                $totalRecords = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
                
                // 分页设置
                $recordsPerPage = 10;
                $totalPages = ceil($totalRecords / $recordsPerPage);
                $currentPage = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
                $offset = ($currentPage - 1) * $recordsPerPage;
                
                // 搜索和过滤
                $search = isset($_GET['search']) ? trim($_GET['search']) : '';
                $statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
                $countryFilter = isset($_GET['country']) ? $_GET['country'] : '';
                
                // 构建基本查询
                $query = "SELECT 
                    first_name, 
                    last_name, 
                    email, 
                    city, 
                    state, 
                    country, 
                    phone, 
                    date_of_birth,
                    is_confirmed 
                    FROM wpej_fc_subscribers 
                    WHERE 1=1";
                
                $params = [];
                
                if (!empty($search)) {
                    $query .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR city LIKE ?)";
                    $searchParam = "%$search%";
                    $params[] = $searchParam;
                    $params[] = $searchParam;
                    $params[] = $searchParam;
                    $params[] = $searchParam;
                }
                
                if (!empty($statusFilter)) {
                    if ($statusFilter === 'confirmed') {
                        $query .= " AND is_confirmed = 1";
                    } elseif ($statusFilter === 'pending') {
                        $query .= " AND is_confirmed = 0";
                    }
                }
                
                if (!empty($countryFilter)) {
                    $query .= " AND country LIKE ?";
                    $params[] = "%$countryFilter%";
                }
                
                // 排序和分页
                $query .= " ORDER BY created_at DESC";
                
                // 先获取总数用于分页
                $countQuery = "SELECT COUNT(*) as count FROM wpej_fc_subscribers WHERE 1=1" . 
                              (!empty($search) ? " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR city LIKE ?)" : "") .
                              (!empty($statusFilter) ? ($statusFilter === 'confirmed' ? " AND is_confirmed = 1" : " AND is_confirmed = 0") : "") .
                              (!empty($countryFilter) ? " AND country LIKE ?" : "");
                
                $countStmt = $pdo_display->prepare($countQuery);
                if (!empty($params)) {
                    $countStmt->execute($params);
                } else {
                    $countStmt->execute();
                }
                $filteredTotal = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                // 添加分页
                $query .= " LIMIT $recordsPerPage OFFSET $offset";
                
                // 执行查询
                $stmt = $pdo_display->prepare($query);
                if (!empty($params)) {
                    $stmt->execute($params);
                } else {
                    $stmt->execute();
                }
                
                $subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $filteredPages = ceil($filteredTotal / $recordsPerPage);

                // 搜索和过滤表单
                ?>
                <div class="filter-section">
                    <div class="search-box">
                        <form method="GET" action="" id="searchForm">
                            <input type="text" name="search" placeholder="Search by name, email, or city..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-options">
                        <button type="submit" class="filter-btn">
                            <i class="fas fa-search me-2"></i>Search
                        </button>
                        <button type="button" class="filter-btn" onclick="location.href='list.php'">
                            <i class="fas fa-redo me-2"></i>Reset
                        </button>
                        </form>
                        
                        <!-- 状态过滤 -->
                        <div class="filter-options">
                            <button type="button" class="filter-btn <?php echo empty($statusFilter) ? 'active' : ''; ?>"
                                    onclick="setFilter('status', '')">
                                All Status
                            </button>
                            <button type="button" class="filter-btn <?php echo $statusFilter === 'confirmed' ? 'active' : ''; ?>"
                                    onclick="setFilter('status', 'confirmed')">
                               Confirmed
                            </button>
                            <button type="button" class="filter-btn <?php echo $statusFilter === 'pending' ? 'active' : ''; ?>"
                                    onclick="setFilter('status', 'pending')">
                                 Pending
                            </button>
                        </div>
                    </div>
                </div>

                <?php
                if (count($subscribers) > 0) {
                    echo '<div class="table-responsive">';
                    echo '<table class="subscribers-table">';
                    echo '<thead>';
                    echo '<tr>';
                    echo '<th>First Name</th>';
                    echo '<th>Last Name</th>';
                    echo '<th>Email</th>';
                    echo '<th>City</th>';
                    echo '<th>State</th>';
                    echo '<th>Country</th>';
                    echo '<th>Phone</th>';
                    echo '<th>Date of Birth</th>';
                    echo '<th>Status</th>';
                    echo '</tr>';
                    echo '</thead>';
                    echo '<tbody>';
                    
                    foreach ($subscribers as $subscriber) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($subscriber['first_name']) . '</td>';
                        echo '<td>' . htmlspecialchars($subscriber['last_name']) . '</td>';
                        echo '<td>' . htmlspecialchars($subscriber['email']) . '</td>';
                        echo '<td>' . htmlspecialchars($subscriber['city']) . '</td>';
                        echo '<td>' . htmlspecialchars($subscriber['state']) . '</td>';
                        echo '<td>' . htmlspecialchars($subscriber['country']) . '</td>';
                        echo '<td>' . htmlspecialchars($subscriber['phone']) . '</td>';
                        echo '<td>' . htmlspecialchars($subscriber['date_of_birth']) . '</td>';
                        echo '<td>';
                        if ($subscriber['is_confirmed']) {
                            echo '<span class="status-confirmed">✅ Confirmed</span>';
                        } else {
                            echo '<span class="status-pending">⏳ Pending</span>';
                        }
                        echo '</td>';
                        echo '</tr>';
                    }
                    
                    echo '</tbody>';
                    echo '</table>';
                    echo '</div>';
                    
                    // 分页显示
                    if ($filteredPages > 1) {
                        echo '<div class="pagination">';
                        if ($currentPage > 1) {
                            echo '<button class="page-btn" onclick="goToPage(' . ($currentPage - 1) . ')">«</button>';
                        }
                        
                        for ($i = 1; $i <= $filteredPages; $i++) {
                            if ($i == $currentPage) {
                                echo '<button class="page-btn active">' . $i . '</button>';
                            } elseif (abs($i - $currentPage) <= 2 || $i == 1 || $i == $filteredPages) {
                                echo '<button class="page-btn" onclick="goToPage(' . $i . ')">' . $i . '</button>';
                            } elseif (abs($i - $currentPage) == 3) {
                                echo '<span>...</span>';
                            }
                        }
                        
                        if ($currentPage < $filteredPages) {
                            echo '<button class="page-btn" onclick="goToPage(' . ($currentPage + 1) . ')">»</button>';
                        }
                        echo '</div>';
                    }
                    
                    echo '<p class="text-center text-muted mt-3">Showing ' . count($subscribers) . ' of ' . $filteredTotal . ' subscribers (Total: ' . $totalRecords . ')</p>';
                } else {
                    echo '<p class="text-center text-muted">No subscribers found.</p>';
                }
            } catch (PDOException $e) {
                echo '<div class="alert alert-danger">';
                echo '<h4>Database Error</h4>';
                echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '</div>';
            }
            ?>
            
            <!-- 操作按钮 - 移到表格下方 -->
            <div class="action-buttons">
               <a href="https://helena1201.free.nf/123/register/" class="action-btn subscribe-btn">
                        <i class="fas fa-paper-plane me-2"></i>Register Now
                </a>
                <a href="<?php echo $base_url; ?>/auth/login.php" class="action-btn back-btn">
                    <i class="fas fa-arrow-left me-2"></i>Back to Login
                </a>
                <button class="action-btn export-btn" id="exportBtn">
                    <i class="fas fa-download me-2"></i>Export to CSV
                </button>
            </div>
        </section>
    </main>

  <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 设置过滤参数
        function setFilter(type, value) {
            const url = new URL(window.location.href);
            const searchParams = new URLSearchParams(url.search);
            
            if (value) {
                searchParams.set(type, value);
            } else {
                searchParams.delete(type);
            }
            
            // 保持搜索关键词
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput && searchInput.value) {
                searchParams.set('search', searchInput.value);
            } else {
                searchParams.delete('search');
            }
            
            // 跳转到新URL
            window.location.href = 'list.php?' + searchParams.toString();
        }
        
        // 跳转到指定页面
        function goToPage(page) {
            const url = new URL(window.location.href);
            const searchParams = new URLSearchParams(url.search);
            
            searchParams.set('page', page);
            window.location.href = 'list.php?' + searchParams.toString();
        }
        
        // 导出数据为CSV（客户端方法）
        function exportToCSV() {
            try {
                const table = document.querySelector('.subscribers-table');
                if (!table) {
                    alert('No data to export');
                    return;
                }
                
                const rows = table.querySelectorAll('tr');
                const csvData = [];
                
                rows.forEach(row => {
                    const rowData = [];
                    const cells = row.querySelectorAll('th, td');
                    
                    cells.forEach(cell => {
                        // 移除状态图标只保留文本
                        let text = cell.textContent.trim();
                        text = text.replace(/[✅⏳]/g, '').trim();
                        rowData.push('"' + text.replace(/"/g, '""') + '"');
                    });
                    
                    csvData.push(rowData.join(','));
                });
                
                const csvString = csvData.join('\n');
                const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                
                a.href = url;
                a.download = 'subscribers_' + new Date().toISOString().slice(0, 10) + '.csv';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            } catch (error) {
                console.error('Export error:', error);
                alert('Error exporting data: ' + error.message);
            }
        }

        // 自动刷新功能
        let autoRefresh = false;
        let refreshInterval;
        
        function toggleAutoRefresh() {
            const refreshBtn = document.getElementById('refreshBtn');
            
            if (!autoRefresh) {
                // 开启自动刷新
                autoRefresh = true;
                refreshBtn.innerHTML = '<i class="fas fa-pause me-2"></i>Pause Auto-Refresh';
                refreshBtn.classList.remove('refresh-btn');
                refreshBtn.classList.add('subscribe-btn');
                
                refreshInterval = setInterval(() => {
                    window.location.reload();
                }, 30000); // 每30秒刷新一次
                
                // 显示通知
                showNotification('Auto-refresh enabled (30s interval)', 'info');
            } else {
                // 关闭自动刷新
                autoRefresh = false;
                refreshBtn.innerHTML = '<i class="fas fa-sync me-2"></i>Auto-Refresh';
                refreshBtn.classList.remove('subscribe-btn');
                refreshBtn.classList.add('refresh-btn');
                clearInterval(refreshInterval);
                
                // 显示通知
                showNotification('Auto-refresh disabled', 'warning');
            }
        }
        
        // 显示通知
        function showNotification(message, type = 'info') {
            // 移除现有通知
            const existingNotification = document.querySelector('.notification');
            if (existingNotification) {
                existingNotification.remove();
            }
            
            // 创建新通知
            const notification = document.createElement('div');
            notification.className = `notification alert alert-${type}`;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                min-width: 300px;
                animation: slideIn 0.3s ease-out;
            `;
            notification.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <span>${message}</span>
                    <button type="button" class="btn-close" onclick="this.parentElement.parentElement.remove()"></button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // 3秒后自动移除
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 3000);
        }
        
        // 添加CSS动画
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            .notification {
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            }
        `;
        document.head.appendChild(style);

        // 事件监听
        document.addEventListener('DOMContentLoaded', function() {
            // 导出按钮
            document.getElementById('exportBtn').addEventListener('click', exportToCSV);
            
            // 自动刷新按钮
            document.getElementById('refreshBtn').addEventListener('click', toggleAutoRefresh);
            
            // 表单提交处理
            const searchForm = document.getElementById('searchForm');
            if (searchForm) {
                searchForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const searchInput = this.querySelector('input[name="search"]');
                    const url = new URL(window.location.href);
                    const searchParams = new URLSearchParams(url.search);
                    
                    if (searchInput.value.trim()) {
                        searchParams.set('search', searchInput.value.trim());
                        searchParams.delete('page'); // 重置到第一页
                    } else {
                        searchParams.delete('search');
                    }
                    
                    window.location.href = 'list.php?' + searchParams.toString();
                });
            }
            
            // 显示当前时间
            updateTime();
            setInterval(updateTime, 1000);
        });
        
        // 更新时间显示
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString();
            
            // 创建或更新时间显示
            let timeDisplay = document.getElementById('currentTime');
            if (!timeDisplay) {
                timeDisplay = document.createElement('div');
                timeDisplay.id = 'currentTime';
                timeDisplay.style.cssText = `
                    position: fixed;
                    bottom: 10px;
                    right: 10px;
                    background: rgba(0,0,0,0.7);
                    color: white;
                    padding: 5px 10px;
                    border-radius: 5px;
                    font-size: 12px;
                    z-index: 1000;
                `;
                document.body.appendChild(timeDisplay);
            }
            
            timeDisplay.textContent = `Last updated: ${timeString}`;
        }
    </script>