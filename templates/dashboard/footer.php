<?php
/**
 * GTAW Furniture Catalog - User Dashboard Footer Template
 */

declare(strict_types=1);

// Get JS translations
$currentLocale = getCurrentLocale();
$jsTranslations = getJsTranslations();
?>
        </main>
    </div>
    
    <!-- Toast Container -->
    <div id="toast-container" class="toast-container" aria-live="polite"></div>
    
    <!-- Translations and Settings for JavaScript -->
    <script>
    window.GTAW_TRANSLATIONS = <?= json_encode($jsTranslations, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    window.GTAW_LOCALE = <?= json_encode($currentLocale) ?>;
    window.GTAW_SETTINGS = <?= json_encode([
        'items_per_page' => getDefaultItemsPerPage(),
        'max_items_per_page' => getMaxItemsPerPage(),
    ]) ?>;
    </script>
    
    <!-- Dashboard JavaScript -->
    <script src="/js/common.js"></script>
    <script src="/js/dashboard.js"></script>
    
    <!-- Sidebar Community Switcher Script -->
    <script>
    (function() {
        const toggle = document.getElementById('sidebar-community-toggle');
        const dropdown = document.getElementById('sidebar-community-dropdown');
        
        if (!toggle || !dropdown) return;
        
        toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = dropdown.classList.toggle('open');
            toggle.setAttribute('aria-expanded', isOpen);
        });
        
        document.addEventListener('click', (e) => {
            if (!e.target.closest('#sidebar-community-switcher')) {
                dropdown.classList.remove('open');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    })();
    </script>
</body>
</html>
