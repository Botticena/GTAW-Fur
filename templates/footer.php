<?php
/**
 * GTAW Furniture Catalog - Footer Template
 * 
 * Include at the end of pages
 */

declare(strict_types=1);
?>
    </main>
    
    <footer class="site-footer">
        <div class="container">
            <p>
                Made with ❤️ by
                <a href="https://forum.gta.world/en/profile/56418-lena/" target="_blank" rel="noopener"> Lena </a>
                for the GTA World Community
                <span class="text-muted"> • </span>
                <a href="https://github.com/Botticena/GTAW-Fur" target="_blank" rel="noopener">GitHub</a>
                <span class="text-muted"> • </span>
                <span class="text-muted">Not affiliated with GTA World</span>
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
    <div id="lightbox" class="lightbox-overlay" role="dialog" aria-modal="true" aria-label="Image preview" tabindex="-1">
        <button class="lightbox-close" aria-label="Close preview" title="Close (ESC)">&times;</button>
        <button class="lightbox-nav prev" aria-label="Previous image" title="Previous (←)">‹</button>
        <button class="lightbox-nav next" aria-label="Next image" title="Next (→)">›</button>
        <div class="lightbox-content">
            <img src="" alt="" id="lightbox-image">
            <div class="lightbox-info">
                <h3 id="lightbox-title"></h3>
                <p class="meta" id="lightbox-meta"></p>
                <div class="lightbox-actions">
                    <div class="lightbox-actions-row lightbox-actions-primary">
                        <button class="btn-copy" id="lightbox-copy" title="Copy /sf command">
                            📋 Copy /sf command
                        </button>
                        <button class="btn-favorite" id="lightbox-favorite" title="Add to favorites" aria-label="Add to favorites">
                            🤍
                        </button>
                        <button class="btn-share" id="lightbox-share" title="Share link to this furniture">
                            🔗 Share
                        </button>
                    </div>
                    <div class="lightbox-actions-row lightbox-actions-secondary">
                        <?php if ($currentUser): ?>
                        <button class="btn-collection" id="lightbox-add-collection" title="Add to collection">
                            📁 Add to Collection
                        </button>
                        <a href="#" class="btn-suggest" id="lightbox-suggest-edit" title="Suggest an edit">
                            ✏️ Suggest Edit
                        </a>
                        <?php endif; ?>
                        <?php if (isAdminLoggedIn()): ?>
                        <a href="#" class="btn-edit" id="lightbox-edit" title="Edit this furniture">
                            ⚙️ Admin Edit
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
    
    <!-- Main Application Script -->
    <script src="/js/app.js"></script>
</body>
</html>

