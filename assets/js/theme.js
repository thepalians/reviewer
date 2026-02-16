/**
 * ReviewFlow Theme Toggle Script - Version 2.0
 * Handles light/dark theme switching with localStorage persistence
 */

(function() {
    'use strict';
    
    const THEME_KEY = 'reviewflow_theme';
    const THEME_ATTR = 'data-theme';
    
    // Theme Manager Class
    class ThemeManager {
        constructor() {
            this.currentTheme = this.getStoredTheme() || this.getSystemTheme();
            this.init();
        }
        
        init() {
            // Apply theme immediately to prevent flash
            this.applyTheme(this.currentTheme);
            
            // Wait for DOM to be ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.setupToggle());
            } else {
                this.setupToggle();
            }
        }
        
        setupToggle() {
            // Create toggle button if not exists
            if (!document.getElementById('theme-toggle-btn')) {
                this.createToggleButton();
            }
            
            // Attach event listener
            const toggleBtn = document.getElementById('theme-toggle-btn');
            if (toggleBtn) {
                toggleBtn.addEventListener('click', () => this.toggle());
            }
            
            // Update icon
            this.updateIcon();
        }
        
        createToggleButton() {
            const button = document.createElement('button');
            button.id = 'theme-toggle-btn';
            button.className = 'theme-toggle';
            button.setAttribute('aria-label', 'Toggle theme');
            button.setAttribute('title', 'Toggle theme');
            button.innerHTML = '<i class="bi bi-sun-fill"></i>';
            document.body.appendChild(button);
        }
        
        getSystemTheme() {
            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                return 'dark';
            }
            return 'light';
        }
        
        getStoredTheme() {
            try {
                return localStorage.getItem(THEME_KEY);
            } catch (e) {
                console.warn('localStorage not available:', e);
                return null;
            }
        }
        
        setStoredTheme(theme) {
            try {
                localStorage.setItem(THEME_KEY, theme);
            } catch (e) {
                console.warn('Could not save theme preference:', e);
            }
        }
        
        applyTheme(theme) {
            document.documentElement.setAttribute(THEME_ATTR, theme);
            this.currentTheme = theme;
            this.setStoredTheme(theme);
            this.updateIcon();
            
            // Dispatch custom event for other scripts
            window.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme } }));
        }
        
        toggle() {
            const newTheme = this.currentTheme === 'light' ? 'dark' : 'light';
            this.applyTheme(newTheme);
            
            // Add animation class
            const btn = document.getElementById('theme-toggle-btn');
            if (btn) {
                btn.style.transform = 'rotate(360deg) scale(1.1)';
                setTimeout(() => {
                    btn.style.transform = '';
                }, 300);
            }
        }
        
        updateIcon() {
            const btn = document.getElementById('theme-toggle-btn');
            if (!btn) return;
            
            const icon = btn.querySelector('i');
            if (!icon) return;
            
            if (this.currentTheme === 'dark') {
                icon.className = 'bi bi-moon-stars-fill';
                btn.setAttribute('title', 'Switch to light mode');
            } else {
                icon.className = 'bi bi-sun-fill';
                btn.setAttribute('title', 'Switch to dark mode');
            }
        }
    }
    
    // Initialize theme manager
    window.themeManager = new ThemeManager();
    
    // Listen for system theme changes
    if (window.matchMedia) {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            // Only auto-switch if user hasn't set a preference
            if (!localStorage.getItem(THEME_KEY)) {
                window.themeManager.applyTheme(e.matches ? 'dark' : 'light');
            }
        });
    }
    
    // Export utility functions
    window.ReviewFlowTheme = {
        getCurrentTheme: () => window.themeManager.currentTheme,
        setTheme: (theme) => {
            if (theme === 'light' || theme === 'dark') {
                window.themeManager.applyTheme(theme);
            }
        },
        toggle: () => window.themeManager.toggle()
    };
    
})();
