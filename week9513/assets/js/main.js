// Main JavaScript for SkillCraft Workshops
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Form validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let valid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    valid = false;
                    field.classList.add('is-invalid');
                } else {
                    field.classList.remove('is-invalid');
                }
            });

            if (!valid) {
                e.preventDefault();
                showAlert('Please fill in all required fields.', 'danger');
            }
        });
    });

    // Update cart count
    updateCartCount();
});

// Cart functions
function addToCart(workshopId, workshopTitle, price) {
    // Send AJAX request to add to cart
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'cart/add_to_cart.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status === 200) {
            const response = JSON.parse(xhr.responseText);
            if (response.success) {
                updateCartCount();
                showAlert('Workshop added to cart!', 'success');
            } else {
                showAlert('Failed to add workshop to cart.', 'danger');
            }
        }
    };
    xhr.send('workshop_id=' + workshopId + '&workshop_title=' + encodeURIComponent(workshopTitle) + '&price=' + price);
}

function removeFromCart(workshopId) {
    if (confirm('Are you sure you want to remove this workshop from your cart?')) {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'cart/remove_from_cart.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            if (xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    location.reload(); // Reload to update the cart display
                }
            }
        };
        xhr.send('workshop_id=' + workshopId);
    }
}

function updateCartCount() {
    const cartCount = document.querySelector('.cart-count');
    if (cartCount) {
        // This would typically come from an AJAX call, but for demo we'll use session
        fetch('cart/get_cart_count.php')
            .then(response => response.json())
            .then(data => {
                if (data.count > 0) {
                    cartCount.textContent = data.count;
                    cartCount.style.display = 'inline';
                } else {
                    cartCount.style.display = 'none';
                }
            });
    }
}

// Utility functions
function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.querySelector('main').insertBefore(alertDiv, document.querySelector('main').firstChild);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentElement) {
            alertDiv.remove();
        }
    }, 5000);
}

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

// Search functionality
const searchInput = document.getElementById('workshop-search');
if (searchInput) {
    searchInput.addEventListener('input', debounce(function() {
        const searchTerm = this.value.toLowerCase();
        const workshopCards = document.querySelectorAll('.workshop-card');
        
        workshopCards.forEach(card => {
            const title = card.querySelector('.card-title').textContent.toLowerCase();
            const description = card.querySelector('.card-text').textContent.toLowerCase();
            
            if (title.includes(searchTerm) || description.includes(searchTerm)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }, 300));
}
/* ===== 暗色切换 ===== */
(function(){
    const btn = document.createElement('button');
    btn.className = 'theme-toggle';
    btn.innerHTML = '🌓';
    btn.setAttribute('aria-label','Toggle dark mode');
    document.body.appendChild(btn);

    btn.onclick = () => {
        const html = document.documentElement;
        const now = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', now);
        localStorage.setItem('theme', now);
    };

    /* 进页面读缓存 */
    const saved = localStorage.getItem('theme');
    if (saved) document.documentElement.setAttribute('data-theme', saved);
})();
/* ===== 普通用户页暗色切换 ===== */
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