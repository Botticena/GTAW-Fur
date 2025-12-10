<?php
/**
 * GTAW Furniture Catalog - English Translations
 * 
 * UI strings for the English locale.
 * Keys should be descriptive and grouped by feature.
 */

return [
    // ===========================================
    // HEADER & NAVIGATION
    // ===========================================
    'nav.dashboard' => 'Dashboard',
    'nav.login' => 'Login with GTA World',
    'nav.logout' => 'Logout',
    'nav.browse' => 'Browse Catalog',
    'nav.skip_to_content' => 'Skip to main content',
    
    // ===========================================
    // COMMUNITY SWITCHER
    // ===========================================
    'community.switch' => 'Switch Community',
    'community.en' => 'GTA World (English)',
    'community.fr' => 'GTA World (French)',
    'community.current' => 'Current: {name}',
    'community.login_note' => 'You will be logged in via {name}',
    
    // ===========================================
    // THEME
    // ===========================================
    'theme.toggle' => 'Toggle theme',
    'theme.dark' => 'dark',
    'theme.light' => 'light',
    'theme.switched' => 'Switched to {mode} mode',
    
    // ===========================================
    // SEARCH & FILTERS
    // ===========================================
    'search.placeholder' => 'Search furniture, categories, or tags...',
    'search.hint' => 'Press / to focus search • C to copy command • Click image to zoom • ↑↓←→ to navigate',
    'search.no_results' => 'No furniture found',
    'search.try_adjusting' => 'Try adjusting your search or filters',
    'search.search_favorites' => 'Search favorites...',
    'search.search_collections' => 'Search collections...',
    'search.search_items' => 'Search items...',
    'search.search_submissions' => 'Search submissions...',
    'search.translated_from' => 'Searching for "{translated}" (translated from "{original}")',
    'search.also_searching' => 'Also searching for:',
    'search.did_you_mean' => 'Did you mean {suggestions}?',
    'search.try_category' => 'Try browsing the {category} category',
    'search.dismiss' => 'Dismiss',
    
    'filter.category' => 'Category:',
    'filter.all_categories' => 'All Categories',
    'filter.sort' => 'Sort by:',
    'filter.sort.name_asc' => 'Name (A-Z)',
    'filter.sort.name_desc' => 'Name (Z-A)',
    'filter.sort.price_asc' => 'Price (Low to High)',
    'filter.sort.price_desc' => 'Price (High to Low)',
    'filter.sort.newest' => 'Newest First',
    'filter.favorites_only' => 'Favorites only',
    'filter.clear_all' => 'Clear All Filters',
    'filter.clear_all_short' => 'Clear all filters',
    'filter.active' => 'Active filters:',
    'filter.clear_group' => 'Clear',
    'filter.remove_tag' => 'Remove',
    
    // ===========================================
    // FURNITURE CARDS
    // ===========================================
    'card.copy' => 'Copy',
    'card.copy_command' => 'Copy /sf command',
    'card.copied' => 'Copied: {command}',
    'card.copy_failed' => 'Failed to copy command',
    
    // ===========================================
    // FAVORITES
    // ===========================================
    'favorites.add' => 'Add to favorites',
    'favorites.remove' => 'Remove from favorites',
    'favorites.login_required' => 'Login to save favorites',
    'favorites.added' => 'Added to favorites',
    'favorites.removed' => 'Removed from favorites',
    'favorites.failed' => 'Failed to update favorites',
    'favorites.title' => 'My Favorites',
    'favorites.export' => 'Export',
    'favorites.clear_all' => 'Clear All',
    'favorites.empty' => 'No favorites yet',
    'favorites.empty_hint' => 'Browse the catalog and click the heart icon to add items to your favorites.',
    'favorites.confirm_remove' => 'Remove this item from favorites?',
    'favorites.confirm_clear' => 'Remove ALL {count} favorites? This cannot be undone.',
    'favorites.cleared' => 'Cleared {count} favorites',
    'favorites.exported' => 'Exported {count} items',
    'favorites.nothing_to_export' => 'No favorites to export',
    'favorites.nothing_to_clear' => 'No favorites to clear',
    'favorites.export_failed' => 'Export failed',
    
    // ===========================================
    // LIGHTBOX
    // ===========================================
    'lightbox.title' => 'Image preview',
    'lightbox.close' => 'Close preview',
    'lightbox.previous' => 'Previous image',
    'lightbox.next' => 'Next image',
    'lightbox.copy_command' => 'Copy /sf command',
    'lightbox.share' => 'Share',
    'lightbox.share_copied' => 'Link copied to clipboard!',
    'lightbox.add_collection' => 'Add to Collection',
    'lightbox.suggest_edit' => 'Suggest Edit',
    'lightbox.admin_edit' => 'Admin Edit',
    
    // ===========================================
    // COLLECTIONS
    // ===========================================
    'collections.title' => 'My Collections',
    'collections.create' => 'Create Collection',
    'collections.create_title' => 'Create Collection',
    'collections.edit_title' => 'Edit Collection',
    'collections.name' => 'Collection Name',
    'collections.name_placeholder' => 'e.g., Modern Living Room',
    'collections.description' => 'Description',
    'collections.description_optional' => 'Description (optional)',
    'collections.description_placeholder' => 'Describe this collection...',
    'collections.make_public' => 'Make this collection public (shareable)',
    'collections.save' => 'Save Changes',
    'collections.cancel' => 'Cancel',
    'collections.delete' => 'Delete',
    'collections.duplicate' => 'Duplicate',
    'collections.share' => 'Share',
    'collections.export' => 'Export',
    'collections.view' => 'View',
    'collections.edit' => 'Edit',
    'collections.back' => '← Back',
    'collections.visibility' => 'Visibility',
    'collections.public' => '🌐 Public',
    'collections.private' => '🔒 Private',
    'collections.items' => 'Items',
    'collections.item_count' => '{count} items',
    'collections.empty' => 'No collections yet',
    'collections.empty_hint' => 'Create collections to organize your furniture items into shareable lists.',
    'collections.collection_empty' => 'Collection is empty',
    'collections.collection_empty_hint' => 'Browse the catalog and add items to this collection.',
    'collections.confirm_delete' => 'Delete collection "{name}"? This cannot be undone.',
    'collections.deleted' => 'Collection deleted',
    'collections.duplicated' => 'Collection duplicated: {name}',
    'collections.link_copied' => 'Collection link copied!',
    'collections.confirm_duplicate' => 'Create a copy of "{name}"?',
    'collections.nothing_to_export' => 'No items in collection to export',
    'collections.added' => 'Added to collection',
    'collections.removed' => 'Removed from collection',
    'collections.reordered' => 'Items reordered successfully',
    'collections.reorder_failed' => 'Failed to reorder items',
    'collections.confirm_remove_item' => 'Remove this item from the collection?',
    'collections.pick_title' => 'Add to Collection',
    'collections.no_collections' => "You haven't created any collections yet.",
    'collections.create_first' => 'Create Collection',
    'collections.new_collection' => '+ New Collection',
    'collections.added_status' => '✓ Added',
    'collections.not_found' => 'Collection not found',
    'collections.public_disabled' => 'Public collections are currently disabled.',
    'collections.will_be_private' => 'This collection will be private.',
    'collections.currently_public_warning' => 'This collection is currently public but will be set to private when saved.',
    
    // ===========================================
    // SUBMISSIONS
    // ===========================================
    'submissions.title' => 'My Submissions',
    'submissions.submit' => 'Submit Furniture',
    'submissions.submit_new' => 'Submit New Furniture',
    'submissions.suggest_edit' => 'Suggest Edit',
    'submissions.submit_edit' => 'Submit Edit',
    'submissions.type' => 'Type',
    'submissions.type_new' => '✨ New',
    'submissions.type_edit' => '✏️ Edit',
    'submissions.status' => 'Status',
    'submissions.status_pending' => '⏳ Pending',
    'submissions.status_approved' => '✓ Approved',
    'submissions.status_rejected' => '✕ Rejected',
    'submissions.submitted' => 'Submitted',
    'submissions.view' => 'View',
    'submissions.cancel' => 'Cancel',
    'submissions.confirm_cancel' => 'Cancel this submission? This cannot be undone.',
    'submissions.cancelled' => 'Submission cancelled',
    'submissions.empty' => 'No submissions yet',
    'submissions.empty_hint' => 'Submit new furniture to add to the catalog, or suggest edits to existing items.',
    'submissions.furniture_name' => 'Furniture Name',
    'submissions.furniture_name_placeholder' => 'e.g., Black Double Bed',
    'submissions.furniture_name_help' => 'The exact prop name used in-game',
    'submissions.price' => 'Price',
    'submissions.price_help' => 'Default is $250 (most common price in-game)',
    'submissions.image_url' => 'Image URL',
    'submissions.image_url_placeholder' => 'https://... or /images/...',
    'submissions.image_url_help' => 'URL to an image of the furniture (will be processed and converted)',
    'submissions.edit_notes' => 'Edit Notes (optional)',
    'submissions.edit_notes_placeholder' => 'Explain what you\'re changing and why...',
    'submissions.categories' => 'Categories',
    'submissions.categories_help' => '(first selected = primary)',
    'submissions.tags' => 'Tags',
    'submissions.category_specific_tags' => 'Category-Specific Tags',
    'submissions.editing' => 'Editing:',
    'submissions.editing_note' => 'Your suggested changes will be reviewed by an administrator before being applied.',
    'submissions.new_note' => 'Your submission will be reviewed by an administrator before being added to the catalog.',
    'submissions.feedback' => 'Feedback from reviewer:',
    'submissions.reviewed_on' => 'Reviewed on {date}',
    'submissions.details' => 'Submission Details',
    'submissions.received' => 'Submission received',
    'submissions.not_found' => 'Submission not found',
    'submissions.disabled' => 'Submissions are currently disabled.',
    'submissions.cannot_edit' => 'Cannot edit a {status} submission',
    'submissions.original_item' => 'Original Item',
    'submissions.proposed_changes' => 'Proposed Changes',
    
    // ===========================================
    // DASHBOARD
    // ===========================================
    'dashboard.title' => 'My Dashboard',
    'dashboard.overview' => 'Overview',
    'dashboard.favorites' => 'Favorites',
    'dashboard.collections' => 'Collections',
    'dashboard.submissions' => 'Submissions',
    'dashboard.browse' => 'Browse Catalog',
    'dashboard.logged_in_as' => 'Logged in as',
    'dashboard.quick_actions' => 'Quick Actions',
    'dashboard.recently_viewed' => 'Recently Viewed',
    'dashboard.pending_review' => 'Pending Review',
    
    // ===========================================
    // PAGINATION
    // ===========================================
    'pagination.previous' => '← Previous',
    'pagination.next' => 'Next →',
    'pagination.previous_page' => 'Previous page',
    'pagination.next_page' => 'Next page',
    'pagination.page_info' => 'Page {page} of {total_pages} ({total} items)',
    'pagination.items' => '{total} item|{total} items',
    
    // ===========================================
    // EMPTY STATES
    // ===========================================
    'empty.loading' => 'Loading furniture...',
    'empty.please_wait' => 'Please wait',
    'empty.welcome' => 'Welcome!',
    'empty.start_browsing' => 'Start browsing furniture items',
    'empty.not_found' => 'Furniture item not found',
    
    // ===========================================
    // ERRORS & MESSAGES
    // ===========================================
    'error.generic' => 'An error occurred',
    'error.loading' => 'Failed to load',
    'error.network' => 'Network error',
    'error.network_retry' => 'Network error. Please try again.',
    'error.not_found' => 'Not found',
    'error.failed_to_load' => 'Failed to load furniture item',
    
    'success.saved' => 'Saved successfully',
    'success.created' => 'Created successfully',
    'success.updated' => 'Updated successfully',
    'success.deleted' => 'Deleted successfully',
    
    // ===========================================
    // FORMS
    // ===========================================
    'form.required' => 'Required',
    'form.optional' => 'Optional',
    'form.save' => 'Save',
    'form.saving' => 'Saving...',
    'form.cancel' => 'Cancel',
    'form.create' => 'Create',
    'form.search' => 'Search',
    'form.search_placeholder' => 'Search...',
    
    // ===========================================
    // TABLES
    // ===========================================
    'table.image' => 'Image',
    'table.name' => 'Name',
    'table.category' => 'Category',
    'table.price' => 'Price',
    'table.actions' => 'Actions',
    'table.description' => 'Description',
    'table.no_results' => 'No items match your search',
    'table.drag_reorder' => 'Drag to reorder',
    
    // ===========================================
    // FOOTER
    // ===========================================
    'footer.made_by' => 'Made with ❤️ by',
    'footer.for_community' => 'for the GTA World Community',
    'footer.not_affiliated' => 'Not affiliated with GTA World',
    'footer.forums' => 'Forums',
    
    // ===========================================
    // SETUP
    // ===========================================
    'setup.required' => 'Setup Required',
    'setup.not_configured' => 'The application is not configured yet.',
    'setup.go_to_admin' => 'Go to Admin Panel',
    
    // ===========================================
    // LOGIN
    // ===========================================
    'login.error_title' => 'Login Failed',
    'login.return_to_catalog' => 'Return to Catalog',
    'login.rate_limited' => 'Too many login attempts. Please try again in a few minutes.',
    'login.invalid_state' => 'Invalid state parameter. Please try logging in again.',
    'login.denied' => 'Authorization denied',
    'login.no_code' => 'Authorization code not received.',
    'login.token_failed' => 'Failed to obtain access token. Please try again.',
    'login.user_failed' => 'Failed to retrieve user data. Please try again.',
    'login.invalid_data' => 'Invalid user data received.',
    'login.process_failed' => 'Failed to process login. Please try again.',
    'login.banned' => 'Your account has been banned. Reason: {reason}',
    'login.oauth_not_configured' => 'OAuth is not configured for this community. Please contact the administrator.',
    'login.community_disabled' => 'This community is currently disabled. Please contact the administrator.',
    'login.registration_disabled' => 'New user registration is currently disabled. Please contact the administrator.',
    
    // ===========================================
    // MAINTENANCE MODE
    // ===========================================
    'maintenance.title' => 'Under Maintenance',
    'maintenance.message' => 'We are currently performing scheduled maintenance. Please check back soon.',
    'maintenance.admin_notice' => 'Maintenance mode is active. Only administrators can access the site.',
];
