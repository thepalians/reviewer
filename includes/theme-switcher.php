<?php
/**
 * ReviewFlow Theme Switcher Component - Version 2.0
 * Include this file on any page to add theme switching capability
 * Requires: Bootstrap Icons, themes.css, theme.js
 */

// This is a simple include file, no session validation needed here
?>

<!-- Theme Switcher Styles (if not using themes.css) -->
<style>
.theme-switcher-inline {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 8px 16px;
    background: var(--glass-bg, rgba(255, 255, 255, 0.7));
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.3));
    border-radius: 24px;
    box-shadow: var(--shadow-sm, 0 2px 8px rgba(0, 0, 0, 0.1));
    cursor: pointer;
    transition: all 0.3s ease;
}

.theme-switcher-inline:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md, 0 4px 12px rgba(0, 0, 0, 0.15));
}

.theme-switcher-inline .theme-icon {
    font-size: 18px;
    color: var(--text-primary, #1e293b);
    transition: transform 0.3s ease;
}

.theme-switcher-inline:hover .theme-icon {
    transform: rotate(20deg);
}

.theme-switcher-inline .theme-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-secondary, #64748b);
}

/* Floating theme toggle (fixed position) */
.theme-toggle-floating {
    position: fixed;
    top: 80px;
    right: 20px;
    z-index: 9999;
}

@media (max-width: 768px) {
    .theme-toggle-floating {
        top: 70px;
        right: 10px;
    }
    
    .theme-switcher-inline .theme-label {
        display: none;
    }
}
</style>

<!-- Inline Theme Switcher (for headers/navbars) -->
<div class="theme-switcher-inline" id="theme-switcher-inline" onclick="window.ReviewFlowTheme && window.ReviewFlowTheme.toggle()" title="Toggle theme">
    <i class="bi bi-sun-fill theme-icon" id="theme-icon-inline"></i>
    <span class="theme-label">Theme</span>
</div>

<script>
// Update inline theme icon when theme changes
if (window.addEventListener) {
    window.addEventListener('themeChanged', function(e) {
        updateInlineThemeIcon(e.detail.theme);
    });
    
    // Initial icon update
    document.addEventListener('DOMContentLoaded', function() {
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
        updateInlineThemeIcon(currentTheme);
    });
}

function updateInlineThemeIcon(theme) {
    const icon = document.getElementById('theme-icon-inline');
    if (icon) {
        icon.className = theme === 'dark' 
            ? 'bi bi-moon-stars-fill theme-icon' 
            : 'bi bi-sun-fill theme-icon';
    }
}
</script>

<?php
// End of theme switcher component
?>
