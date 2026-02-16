    </div><!-- End Main Content -->
    
    <!-- Impersonation Banner -->
    <?php require_once __DIR__ . '/../../includes/impersonation-banner.php'; ?>
    
    <!-- Version Display -->
    <?php require_once __DIR__ . '/../../includes/version-display.php'; ?>
    
    <!-- Include Theme CSS and JS -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/themes.css">
    <script src="<?= APP_URL ?>/assets/js/theme.js"></script>
    
    <!-- Include Chatbot Widget -->
    <?php require_once __DIR__ . '/../../includes/chatbot-widget.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile menu toggle
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.querySelector('.mobile-menu-toggle');
            const sidebar = document.querySelector('.sidebar');
            
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                });
            }
        });
    </script>
</body>
</html>
