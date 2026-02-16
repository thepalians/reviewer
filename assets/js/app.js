/**
 * ReviewFlow - Main Application JavaScript
 * PWA initialization, notifications, and utilities
 */

(function() {
    'use strict';
    
    const APP = {
        // Configuration
        config: {
            appName: 'ReviewFlow',
            swPath: '/reviewer/sw.js',
            apiBase: '/reviewer/api/',
            notificationPermission: false
        },
        
        // Initialize app
        init: function() {
            console.log('[App] Initializing...');
            
            this.registerServiceWorker();
            this.initNotifications();
            this.initOfflineDetection();
            this.initFormValidation();
            this.initLazyLoading();
            this.initTooltips();
            this.initPullToRefresh();
            
            console.log('[App] Initialized');
        },
        
        // Register Service Worker
        registerServiceWorker: function() {
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register(this.config.swPath)
                    .then((registration) => {
                        console.log('[App] SW registered:', registration.scope);
                        
                        // Check for updates
                        registration.addEventListener('updatefound', () => {
                            const newWorker = registration.installing;
                            newWorker.addEventListener('statechange', () => {
                                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    this.showUpdateNotification();
                                }
                            });
                        });
                    })
                    .catch((error) => {
                        console.log('[App] SW registration failed:', error);
                    });
                
                // Handle controller change
                navigator.serviceWorker.addEventListener('controllerchange', () => {
                    console.log('[App] SW controller changed');
                });
            }
        },
        
        // Show update notification
        showUpdateNotification: function() {
            if (confirm('A new version is available! Reload to update?')) {
                window.location.reload();
            }
        },
        
        // Initialize push notifications
        initNotifications: function() {
            if (!('Notification' in window)) {
                console.log('[App] Notifications not supported');
                return;
            }
            
            if (Notification.permission === 'granted') {
                this.config.notificationPermission = true;
            } else if (Notification.permission !== 'denied') {
                // Ask for permission after user interaction
                document.addEventListener('click', () => {
                    this.requestNotificationPermission();
                }, { once: true });
            }
        },
        
        // Request notification permission
        requestNotificationPermission: function() {
            Notification.requestPermission().then((permission) => {
                if (permission === 'granted') {
                    this.config.notificationPermission = true;
                    console.log('[App] Notification permission granted');
                    this.subscribeToPush();
                }
            });
        },
        
        // Subscribe to push notifications
        subscribeToPush: function() {
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                return;
            }
            
            navigator.serviceWorker.ready.then((registration) => {
                registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: this.urlBase64ToUint8Array(
                        // Replace with your VAPID public key
                        'YOUR_VAPID_PUBLIC_KEY'
                    )
                })
                .then((subscription) => {
                    console.log('[App] Push subscription:', subscription);
                    // Send subscription to server
                    this.sendSubscriptionToServer(subscription);
                })
                .catch((error) => {
                    console.log('[App] Push subscription failed:', error);
                });
            });
        },
        
        // Send subscription to server
        sendSubscriptionToServer: function(subscription) {
            fetch(this.config.apiBase + 'push-subscribe.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(subscription)
            }).catch(console.error);
        },
        
        // Convert VAPID key
        urlBase64ToUint8Array: function(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        },
        
        // Offline detection
        initOfflineDetection: function() {
            const updateOnlineStatus = () => {
                if (navigator.onLine) {
                    document.body.classList.remove('offline');
                    this.hideOfflineBanner();
                } else {
                    document.body.classList.add('offline');
                    this.showOfflineBanner();
                }
            };
            
            window.addEventListener('online', updateOnlineStatus);
            window.addEventListener('offline', updateOnlineStatus);
            
            updateOnlineStatus();
        },
        
        // Show offline banner
        showOfflineBanner: function() {
            if (document.getElementById('offline-banner')) return;
            
            const banner = document.createElement('div');
            banner.id = 'offline-banner';
            banner.innerHTML = '📡 You are offline. Some features may be unavailable.';
            banner.style.cssText = `
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: #ef4444;
                color: white;
                padding: 12px;
                text-align: center;
                font-size: 14px;
                font-weight: 600;
                z-index: 9999;
                animation: slideUp 0.3s ease;
            `;
            document.body.appendChild(banner);
        },
        
        // Hide offline banner
        hideOfflineBanner: function() {
            const banner = document.getElementById('offline-banner');
            if (banner) {
                banner.style.animation = 'slideDown 0.3s ease';
                setTimeout(() => banner.remove(), 300);
            }
        },
        
        // Form validation
        initFormValidation: function() {
            document.querySelectorAll('form[data-validate]').forEach(form => {
                form.addEventListener('submit', (e) => {
                    if (!this.validateForm(form)) {
                        e.preventDefault();
                    }
                });
            });
        },
        
        // Validate form
        validateForm: function(form) {
            let isValid = true;
            
            form.querySelectorAll('[required]').forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    this.showFieldError(field, 'This field is required');
                } else {
                    this.clearFieldError(field);
                }
            });
            
            form.querySelectorAll('[type="email"]').forEach(field => {
                if (field.value && !this.isValidEmail(field.value)) {
                    isValid = false;
                    this.showFieldError(field, 'Please enter a valid email');
                }
            });
            
            form.querySelectorAll('[data-minlength]').forEach(field => {
                const min = parseInt(field.dataset.minlength);
                if (field.value && field.value.length < min) {
                    isValid = false;
                    this.showFieldError(field, `Minimum ${min} characters required`);
                }
            });
            
            return isValid;
        },
        
        // Show field error
        showFieldError: function(field, message) {
            field.classList.add('error');
            
            let errorEl = field.parentNode.querySelector('.field-error');
            if (!errorEl) {
                errorEl = document.createElement('div');
                errorEl.className = 'field-error';
                errorEl.style.cssText = 'color: #ef4444; font-size: 12px; margin-top: 5px;';
                field.parentNode.appendChild(errorEl);
            }
            errorEl.textContent = message;
        },
        
        // Clear field error
        clearFieldError: function(field) {
            field.classList.remove('error');
            const errorEl = field.parentNode.querySelector('.field-error');
            if (errorEl) errorEl.remove();
        },
        
        // Email validation
        isValidEmail: function(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        },
        
        // Lazy loading
        initLazyLoading: function() {
            if ('IntersectionObserver' in window) {
                const lazyObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.src = img.dataset.src;
                            img.classList.remove('lazy');
                            lazyObserver.unobserve(img);
                        }
                    });
                });
                
                document.querySelectorAll('img.lazy').forEach(img => {
                    lazyObserver.observe(img);
                });
            }
        },
        
        // Tooltips
        initTooltips: function() {
            document.querySelectorAll('[data-tooltip]').forEach(el => {
                el.addEventListener('mouseenter', (e) => {
                    const tooltip = document.createElement('div');
                    tooltip.className = 'tooltip';
                    tooltip.textContent = el.dataset.tooltip;
                    tooltip.style.cssText = `
                        position: absolute;
                        background: #1e293b;
                        color: #fff;
                        padding: 8px 12px;
                        border-radius: 6px;
                        font-size: 12px;
                        z-index: 1000;
                        white-space: nowrap;
                    `;
                    document.body.appendChild(tooltip);
                    
                    const rect = el.getBoundingClientRect();
                    tooltip.style.top = (rect.top - tooltip.offsetHeight - 8) + 'px';
                    tooltip.style.left = (rect.left + rect.width/2 - tooltip.offsetWidth/2) + 'px';
                    
                    el._tooltip = tooltip;
                });
                
                el.addEventListener('mouseleave', () => {
                    if (el._tooltip) {
                        el._tooltip.remove();
                        el._tooltip = null;
                    }
                });
            });
        },
        
        // Pull to refresh
        initPullToRefresh: function() {
            let startY = 0;
            let pulling = false;
            
            document.addEventListener('touchstart', (e) => {
                if (window.scrollY === 0) {
                    startY = e.touches[0].pageY;
                    pulling = true;
                }
            }, { passive: true });
            
            document.addEventListener('touchmove', (e) => {
                if (!pulling) return;
                
                const y = e.touches[0].pageY;
                const diff = y - startY;
                
                if (diff > 100) {
                    pulling = false;
                    this.showRefreshIndicator();
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                }
            }, { passive: true });
            
            document.addEventListener('touchend', () => {
                pulling = false;
            }, { passive: true });
        },
        
        // Show refresh indicator
        showRefreshIndicator: function() {
            const indicator = document.createElement('div');
            indicator.innerHTML = '🔄 Refreshing...';
            indicator.style.cssText = `
                position: fixed;
                top: 20px;
                left: 50%;
                transform: translateX(-50%);
                background: #fff;
                padding: 12px 25px;
                border-radius: 25px;
                box-shadow: 0 5px 20px rgba(0,0,0,0.2);
                font-weight: 600;
                z-index: 9999;
            `;
            document.body.appendChild(indicator);
        },
        
        // Show toast notification
        showToast: function(message, type = 'info', duration = 3000) {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.textContent = message;
            toast.style.cssText = `
                position: fixed;
                bottom: 30px;
                left: 50%;
                transform: translateX(-50%);
                background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#1e293b'};
                color: #fff;
                padding: 15px 30px;
                border-radius: 10px;
                font-weight: 600;
                z-index: 9999;
                animation: fadeInUp 0.3s ease;
            `;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'fadeOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, duration);
        },
        
        // Copy to clipboard
        copyToClipboard: function(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text)
                    .then(() => this.showToast('Copied!', 'success'))
                    .catch(() => this.fallbackCopyToClipboard(text));
            } else {
                this.fallbackCopyToClipboard(text);
            }
        },
        
        // Fallback copy
        fallbackCopyToClipboard: function(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            this.showToast('Copied!', 'success');
        },
        
        // Format currency
        formatCurrency: function(amount) {
            return '₹' + parseFloat(amount).toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        },
        
        // Time ago
        timeAgo: function(date) {
            const seconds = Math.floor((new Date() - new Date(date)) / 1000);
            
            if (seconds < 60) return 'Just now';
            if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
            if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
            if (seconds < 604800) return Math.floor(seconds / 86400) + 'd ago';
            
            return new Date(date).toLocaleDateString('en-IN', { 
                day: 'numeric', 
                month: 'short' 
            });
        }
    };
    
    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeInUp {
            from { opacity: 0; transform: translate(-50%, 20px); }
            to { opacity: 1; transform: translate(-50%, 0); }
        }
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        @keyframes slideUp {
            from { transform: translateY(100%); }
            to { transform: translateY(0); }
        }
        @keyframes slideDown {
            from { transform: translateY(0); }
            to { transform: translateY(100%); }
        }
        .error { border-color: #ef4444 !important; }
    `;
    document.head.appendChild(style);
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => APP.init());
    } else {
        APP.init();
    }
    
    // Expose to global scope
    window.APP = APP;
    
})();
