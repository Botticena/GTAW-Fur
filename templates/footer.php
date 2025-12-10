<?php
/**
 * GTAW Furniture Catalog - Footer Template
 * 
 * Include at the end of pages
 */

declare(strict_types=1);

// Get current locale for JS translations
$currentLocale = getCurrentLocale();
$jsTranslations = getJsTranslations();
?>
    </main>
    
    <footer class="site-footer">
        <div class="container">
            <p>
                <?= e(__('footer.made_by')) ?>
                <a href="https://forum.gta.world/en/profile/56418-lena/" target="_blank" rel="noopener"> Lena </a>
                <?= e(__('footer.for_community')) ?>
                <span class="text-muted"> • </span>
                <a href="https://forum.gta.world/en/topic/152514-gtaw-furniture-catalog" target="_blank" rel="noopener"><?= e(__('footer.forums')) ?></a>
                <span class="text-muted"> • </span>
                <a href="https://github.com/Botticena/GTAW-Fur" target="_blank" rel="noopener">GitHub</a>
                <span class="text-muted"> • </span>
                <span class="text-muted"><?= e(__('footer.not_affiliated')) ?></span>
            </p>
        </div>
    </footer>
    
    <!-- Toast Container -->
    <div id="toast-container" class="toast-container" aria-live="polite"></div>
    
    <!-- Loading Overlay -->
    <div id="loading" class="loading-overlay" aria-hidden="true">
        <div class="loading-spinner"></div>
    </div>
    
    <!-- Image Lightbox -->
    <div id="lightbox" class="lightbox-overlay" role="dialog" aria-modal="true" aria-label="<?= e(__('lightbox.title')) ?>" tabindex="-1">
        <button class="lightbox-close" aria-label="<?= e(__('lightbox.close')) ?>" title="<?= e(__('lightbox.close')) ?> (ESC)">&times;</button>
        <button class="lightbox-nav prev" aria-label="<?= e(__('lightbox.previous')) ?>" title="<?= e(__('lightbox.previous')) ?> (←)">‹</button>
        <button class="lightbox-nav next" aria-label="<?= e(__('lightbox.next')) ?>" title="<?= e(__('lightbox.next')) ?> (→)">›</button>
        <div class="lightbox-content">
            <div class="lightbox-image-container">
                <img src="" alt="" id="lightbox-image">
            </div>
            <div class="lightbox-info-card">
                <h3 id="lightbox-title"></h3>
                <p class="meta" id="lightbox-meta"></p>
                <div class="lightbox-tags" id="lightbox-tags" style="display: none;"></div>
                <div class="lightbox-actions">
                    <div class="lightbox-actions-row lightbox-actions-primary">
                        <button class="btn-copy" id="lightbox-copy" title="<?= e(__('lightbox.copy_command')) ?>">
                            📋 <?= e(__('lightbox.copy_command')) ?>
                        </button>
                        <button class="btn-favorite" id="lightbox-favorite" title="<?= e(__('favorites.add')) ?>" aria-label="<?= e(__('favorites.add')) ?>">
                            🤍
                        </button>
                        <button class="btn-share" id="lightbox-share" title="<?= e(__('lightbox.share')) ?>">
                            🔗 <?= e(__('lightbox.share')) ?>
                        </button>
                    </div>
                    <div class="lightbox-actions-row lightbox-actions-secondary">
                        <?php if ($currentUser): ?>
                        <button class="btn-collection" id="lightbox-add-collection" title="<?= e(__('lightbox.add_collection')) ?>">
                            📁 <?= e(__('lightbox.add_collection')) ?>
                        </button>
                        <a href="#" class="btn-suggest" id="lightbox-suggest-edit" title="<?= e(__('lightbox.suggest_edit')) ?>">
                            ✏️ <?= e(__('lightbox.suggest_edit')) ?>
                        </a>
                        <?php endif; ?>
                        <?php if (isAdminLoggedIn()): ?>
                        <a href="#" class="btn-edit" id="lightbox-edit" title="<?= e(__('lightbox.admin_edit')) ?>">
                            ⚙️ <?= e(__('lightbox.admin_edit')) ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($currentUser): ?>
    <!-- Collection Picker Modal will be created dynamically by JavaScript -->
    <?php endif; ?>
    
    <!-- Translations and Settings for JavaScript -->
    <script>
    window.GTAW_TRANSLATIONS = <?= json_encode($jsTranslations, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    window.GTAW_LOCALE = <?= json_encode($currentLocale) ?>;
    window.GTAW_SETTINGS = <?= json_encode([
        'items_per_page' => getDefaultItemsPerPage(),
        'max_items_per_page' => getMaxItemsPerPage(),
    ]) ?>;
    </script>
    
    <!-- Main Application Script -->
    <script src="/js/common.js"></script>
    <script src="/js/app.js"></script>
    
    <!-- Community Switcher Script -->
    <script>
    (function() {
        const toggle = document.getElementById('community-toggle');
        const dropdown = document.getElementById('community-dropdown');
        
        if (!toggle || !dropdown) return;
        
        toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = dropdown.classList.toggle('open');
            toggle.setAttribute('aria-expanded', isOpen);
        });
        
        document.addEventListener('click', (e) => {
            if (!e.target.closest('#community-switcher')) {
                dropdown.classList.remove('open');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
        
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && dropdown.classList.contains('open')) {
                dropdown.classList.remove('open');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.focus();
            }
        });
    })();
    </script>
</body>
</html>
