<?php
/**
 * Version Display Component - Version 2.0
 * Shows application version with optional changelog link
 */

// Get current page to adjust position
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>

<style>
.version-display {
    position: fixed;
    bottom: 10px;
    left: 10px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 12px;
    color: var(--text-secondary, #64748b);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    z-index: 9996;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

[data-theme="dark"] .version-display {
    background: rgba(30, 41, 59, 0.95);
    color: var(--text-secondary, #cbd5e1);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.version-display:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.version-display i {
    font-size: 14px;
    color: var(--brand-primary, #667eea);
}

.version-number {
    font-weight: 600;
    color: var(--text-primary, #1e293b);
}

[data-theme="dark"] .version-number {
    color: var(--text-primary, #f1f5f9);
}

.version-link {
    color: var(--brand-primary, #667eea);
    text-decoration: none;
    padding: 2px 8px;
    border-radius: 4px;
    transition: all 0.2s;
}

.version-link:hover {
    background: rgba(102, 126, 234, 0.1);
    color: var(--brand-primary, #667eea);
}

@media (max-width: 768px) {
    .version-display {
        font-size: 10px;
        padding: 6px 12px;
        bottom: 70px;
    }
    
    .version-link {
        display: none;
    }
}
</style>

<div class="version-display">
    <i class="bi bi-code-square"></i>
    <span class="version-number">v<?= APP_VERSION ?></span>
    <?php if (file_exists(__DIR__ . '/../CHANGELOG.md')): ?>
        <a href="<?= APP_URL ?>/CHANGELOG.md" target="_blank" class="version-link" title="View Changelog">
            <i class="bi bi-journal-text"></i>
        </a>
    <?php endif; ?>
</div>

<?php
// End of version display component
?>
