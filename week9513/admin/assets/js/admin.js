// admin/assets/js/admin.js - 管理后台 JavaScript
document.addEventListener('DOMContentLoaded', function() {
    initializeAdmin();
    initializeFormValidation();
    initializeDataTables();
});

function initializeAdmin() {
    // 初始化工具提示
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(tooltip => {
        tooltip.addEventListener('mouseenter', showTooltip);
        tooltip.addEventListener('mouseleave', hideTooltip);
    });

    // 自动隐藏成功消息
    const successMessages = document.querySelectorAll('[style*="background: #d1f7c4"]');
    successMessages.forEach(message => {
        setTimeout(() => {
            message.style.display = 'none';
        }, 5000);
    });
}

function showTooltip(e) {
    const tooltipText = e.target.getAttribute('data-tooltip');
    const tooltip = document.createElement('div');
    tooltip.className = 'admin-tooltip';
    tooltip.textContent = tooltipText;
    tooltip.style.position = 'absolute';
    tooltip.style.background = 'rgba(0,0,0,0.8)';
    tooltip.style.color = 'white';
    tooltip.style.padding = '5px 10px';
    tooltip.style.borderRadius = '3px';
    tooltip.style.fontSize = '12px';
    tooltip.style.zIndex = '1000';
    
    document.body.appendChild(tooltip);
    
    const rect = e.target.getBoundingClientRect();
    tooltip.style.left = rect.left + 'px';
    tooltip.style.top = (rect.top - tooltip.offsetHeight - 5) + 'px';
    
    e.target._currentTooltip = tooltip;
}

function hideTooltip(e) {
    if (e.target._currentTooltip) {
        e.target._currentTooltip.remove();
        e.target._currentTooltip = null;
    }
}

function initializeFormValidation() {
    const forms = document.querySelectorAll('.admin-form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = '#d63638';
                    
                    // 添加错误消息
                    if (!field.nextElementSibling || !field.nextElementSibling.classList.contains('error-message')) {
                        const errorMsg = document.createElement('div');
                        errorMsg.className = 'error-message';
                        errorMsg.style.color = '#d63638';
                        errorMsg.style.fontSize = '12px';
                        errorMsg.style.marginTop = '5px';
                        errorMsg.textContent = 'This field is required';
                        field.parentNode.appendChild(errorMsg);
                    }
                } else {
                    field.style.borderColor = '';
                    const errorMsg = field.parentNode.querySelector('.error-message');
                    if (errorMsg) {
                        errorMsg.remove();
                    }
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showNotification('Please fill in all required fields.', 'error');
            }
        });
    });
}

function initializeDataTables() {
    // 简单的表格排序功能
    const tables = document.querySelectorAll('.admin-table');
    tables.forEach(table => {
        const headers = table.querySelectorAll('th');
        headers.forEach((header, index) => {
            if (index < headers.length - 1) { // 排除操作列
                header.style.cursor = 'pointer';
                header.addEventListener('click', () => {
                    sortTable(table, index);
                });
            }
        });
    });
}

function sortTable(table, columnIndex) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        const aVal = a.cells[columnIndex].textContent.trim();
        const bVal = b.cells[columnIndex].textContent.trim();
        
        // 尝试数字比较
        const aNum = parseFloat(aVal.replace(/[^\d.-]/g, ''));
        const bNum = parseFloat(bVal.replace(/[^\d.-]/g, ''));
        
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return aNum - bNum;
        }
        
        // 字符串比较
        return aVal.localeCompare(bVal);
    });
    
    // 重新排列行
    while (tbody.firstChild) {
        tbody.removeChild(tbody.firstChild);
    }
    
    rows.forEach(row => tbody.appendChild(row));
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.padding = '15px 20px';
    notification.style.borderRadius = '4px';
    notification.style.color = 'white';
    notification.style.zIndex = '10000';
    notification.style.maxWidth = '300px';
    notification.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
    
    switch (type) {
        case 'success':
            notification.style.background = '#00a32a';
            break;
        case 'error':
            notification.style.background = '#d63638';
            break;
        case 'warning':
            notification.style.background = '#dba617';
            break;
        default:
            notification.style.background = '#2271b1';
    }
    
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 5000);
}

// 批量操作功能
function handleBulkAction(action) {
    const selectedItems = document.querySelectorAll('input[name="selected_items[]"]:checked');
    
    if (selectedItems.length === 0) {
        showNotification('Please select at least one item.', 'warning');
        return;
    }
    
    const itemIds = Array.from(selectedItems).map(item => item.value);
    
    if (confirm(`Are you sure you want to ${action} ${selectedItems.length} item(s)?`)) {
        // 这里可以添加 AJAX 请求来处理批量操作
        showNotification(`Processing ${action} action...`, 'info');
        
        // 模拟处理
        setTimeout(() => {
            showNotification(`${action.charAt(0).toUpperCase() + action.slice(1)} completed successfully!`, 'success');
        }, 2000);
    }
}

// 搜索功能
function filterTable(searchTerm) {
    const tables = document.querySelectorAll('.admin-table');
    
    tables.forEach(table => {
        const rows = table.querySelectorAll('tbody tr');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.includes(searchTerm.toLowerCase())) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // 更新结果计数
        const resultsElement = table.parentNode.querySelector('.results-count');
        if (resultsElement) {
            resultsElement.textContent = `${visibleCount} results found`;
        }
    });
}

// 图片预览功能
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    const file = input.files[0];
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        preview.style.display = 'none';
    }
}

// 导出功能
function exportData(format = 'csv') {
    const table = document.querySelector('.admin-table');
    if (!table) {
        showNotification('No data available to export.', 'warning');
        return;
    }
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            let data = cols[j].textContent.replace(/(\r\n|\n|\r)/gm, '').replace(/(\s\s)/gm, ' ');
            data = data.replace(/"/g, '""');
            row.push('"' + data + '"');
        }
        
        csv.push(row.join(','));
    }
    
    // 下载 CSV 文件
    const csvFile = new Blob([csv.join('\n')], {type: 'text/csv'});
    const downloadLink = document.createElement('a');
    downloadLink.download = `admin-export-${new Date().toISOString().split('T')[0]}.csv`;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
    
    showNotification('Data exported successfully!', 'success');
}

// 防抖搜索
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// 初始化搜索
const searchInput = document.getElementById('tableSearch');
if (searchInput) {
    searchInput.addEventListener('input', debounce(function() {
        filterTable(this.value);
    }, 300));
}

// 全局函数
window.showNotification = showNotification;
window.handleBulkAction = handleBulkAction;
window.exportData = exportData;
window.previewImage = previewImage;
/* 明暗切换 */
/* ===== 纯文字切换 ===== */
(function(){
    const btn = document.createElement('button');
    btn.className = 'theme-toggle';
    btn.id = 'themeToggle';               // 方便后面换文字
    btn.textContent = 'Light';            // 默认亮面
    btn.title = 'Switch to dark mode';    // hover 提示
    document.body.appendChild(btn);

    function setText(){
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        btn.textContent = isDark ? 'Dark' : 'Light';
        btn.title = isDark ? 'Switch to light mode' : 'Switch to dark mode';
    }

    btn.onclick = () => {
        const html = document.documentElement;
        const now = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', now);
        localStorage.setItem('theme', now);
        setText();
    };

    const saved = localStorage.getItem('theme');
    if (saved) {
        document.documentElement.setAttribute('data-theme', saved);
        setText();
    }
})();