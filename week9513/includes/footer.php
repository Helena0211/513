</main>

    <footer class="site-footer bg-dark text-light py-4 mt-5">
        <div class="container">
            <!-- 社交媒体链接 -->
            <div class="row justify-content-center mb-4">
                <div class="col-md-6 text-center">
                    <h6>Follow Our Journey</h6>
                    <div class="social-links mt-2">
                        <a href="#" class="text-light me-3" title="Facebook">
                            <i class="fab fa-facebook-f fa-lg"></i>
                        </a>
                        <a href="#" class="text-light me-3" title="Twitter">
                            <i class="fab fa-twitter fa-lg"></i>
                        </a>
                        <a href="#" class="text-light me-3" title="Instagram">
                            <i class="fab fa-instagram fa-lg"></i>
                        </a>
                        <a href="#" class="text-light" title="YouTube">
                            <i class="fab fa-youtube fa-lg"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <hr class="my-4">
            
            <!-- 居中版权信息 -->
            <div class="row">
                <div class="col-12 text-center">
                    <p class="mb-0">&copy; 2025 SkillCraft Workshops by Helena. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $base_url; ?>/assets/js/main.js"></script>
    
    <!-- 订阅成功提示脚本 -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 如果有成功消息，3秒后淡出
        const successMessages = document.querySelectorAll('.message.success, .message.pending');
        successMessages.forEach(message => {
            setTimeout(() => {
                message.style.transition = 'opacity 0.5s ease';
                message.style.opacity = '0';
                setTimeout(() => {
                    message.style.display = 'none';
                }, 500);
            }, 5000);
        });
        
        // 订阅表单增强
        const subscribeForm = document.querySelector('.subscribe-form');
        if (subscribeForm) {
            subscribeForm.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('.subscribe-btn');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Subscribing...';
                submitBtn.disabled = true;
            });
        }
    });
    </script>
</body>
</html>