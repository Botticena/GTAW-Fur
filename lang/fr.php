<?php
/**
 * GTAW Furniture Catalog - French Translations
 * 
 * UI strings for the French locale.
 * Keys should be descriptive and grouped by feature.
 */

return [
    // ===========================================
    // HEADER & NAVIGATION
    // ===========================================
    'nav.dashboard' => 'Tableau de bord',
    'nav.login' => 'Connexion via GTA World',
    'nav.logout' => 'Déconnexion',
    'nav.browse' => 'Parcourir le catalogue',
    'nav.skip_to_content' => 'Aller au contenu principal',
    
    // ===========================================
    // COMMUNITY SWITCHER
    // ===========================================
    'community.switch' => 'Changer de communauté',
    'community.en' => 'GTA World (Anglais)',
    'community.fr' => 'GTA World (Français)',
    'community.current' => 'Actuel : {name}',
    'community.login_note' => 'Vous serez connecté via {name}',
    
    // ===========================================
    // THEME
    // ===========================================
    'theme.toggle' => 'Changer de thème',
    'theme.dark' => 'sombre',
    'theme.light' => 'clair',
    'theme.switched' => 'Mode {mode} activé',
    
    // ===========================================
    // SEARCH & FILTERS
    // ===========================================
    'search.placeholder' => 'Rechercher meubles, catégories ou tags...',
    'search.hint' => 'Appuyez sur / pour rechercher • C pour copier • Cliquez sur l\'image pour zoomer • ↑↓←→ pour naviguer',
    'search.no_results' => 'Aucun meuble trouvé',
    'search.try_adjusting' => 'Essayez d\'ajuster votre recherche ou vos filtres',
    'search.search_favorites' => 'Rechercher dans les favoris...',
    'search.search_collections' => 'Rechercher dans les collections...',
    'search.search_items' => 'Rechercher des éléments...',
    'search.search_submissions' => 'Rechercher des soumissions...',
    'search.translated_from' => 'Recherche de "{translated}" (traduit de "{original}")',
    'search.also_searching' => 'Recherche également pour :',
    'search.did_you_mean' => 'Vouliez-vous dire {suggestions} ?',
    'search.try_category' => 'Essayez de parcourir la catégorie {category}',
    'search.dismiss' => 'Fermer',
    
    'filter.category' => 'Catégorie :',
    'filter.all_categories' => 'Toutes les catégories',
    'filter.sort' => 'Trier par :',
    'filter.sort.name_asc' => 'Nom (A-Z)',
    'filter.sort.name_desc' => 'Nom (Z-A)',
    'filter.sort.price_asc' => 'Prix (croissant)',
    'filter.sort.price_desc' => 'Prix (décroissant)',
    'filter.sort.newest' => 'Plus récents',
    'filter.favorites_only' => 'Favoris uniquement',
    'filter.clear_all' => 'Effacer tous les filtres',
    'filter.clear_all_short' => 'Effacer tous les filtres',
    'filter.active' => 'Filtres actifs :',
    'filter.clear_group' => 'Effacer',
    'filter.remove_tag' => 'Retirer',
    
    // ===========================================
    // FURNITURE CARDS
    // ===========================================
    'card.copy' => 'Copier',
    'card.copy_command' => 'Copier la commande /sf',
    'card.copied' => 'Copié : {command}',
    'card.copy_failed' => 'Échec de la copie',
    
    // ===========================================
    // FAVORITES
    // ===========================================
    'favorites.add' => 'Ajouter aux favoris',
    'favorites.remove' => 'Retirer des favoris',
    'favorites.login_required' => 'Connectez-vous pour sauvegarder des favoris',
    'favorites.added' => 'Ajouté aux favoris',
    'favorites.removed' => 'Retiré des favoris',
    'favorites.failed' => 'Échec de la mise à jour des favoris',
    'favorites.title' => 'Mes Favoris',
    'favorites.export' => 'Exporter',
    'favorites.clear_all' => 'Tout effacer',
    'favorites.empty' => 'Aucun favori',
    'favorites.empty_hint' => 'Parcourez le catalogue et cliquez sur le cœur pour ajouter des éléments à vos favoris.',
    'favorites.confirm_remove' => 'Retirer cet élément des favoris ?',
    'favorites.confirm_clear' => 'Supprimer TOUS les {count} favoris ? Cette action est irréversible.',
    'favorites.cleared' => '{count} favoris supprimés',
    'favorites.exported' => '{count} éléments exportés',
    'favorites.nothing_to_export' => 'Aucun favori à exporter',
    'favorites.nothing_to_clear' => 'Aucun favori à effacer',
    'favorites.export_failed' => 'Échec de l\'export',
    
    // ===========================================
    // LIGHTBOX
    // ===========================================
    'lightbox.title' => 'Aperçu de l\'image',
    'lightbox.close' => 'Fermer l\'aperçu',
    'lightbox.previous' => 'Image précédente',
    'lightbox.next' => 'Image suivante',
    'lightbox.copy_command' => 'Copier la commande /sf',
    'lightbox.share' => 'Partager',
    'lightbox.share_copied' => 'Lien copié !',
    'lightbox.add_collection' => 'Ajouter à une collection',
    'lightbox.suggest_edit' => 'Suggérer une modification',
    'lightbox.admin_edit' => 'Modifier (Admin)',
    
    // ===========================================
    // COLLECTIONS
    // ===========================================
    'collections.title' => 'Mes Collections',
    'collections.create' => 'Créer une collection',
    'collections.create_title' => 'Créer une collection',
    'collections.edit_title' => 'Modifier la collection',
    'collections.name' => 'Nom de la collection',
    'collections.name_placeholder' => 'ex: Salon Moderne',
    'collections.description' => 'Description',
    'collections.description_optional' => 'Description (optionnel)',
    'collections.description_placeholder' => 'Décrivez cette collection...',
    'collections.make_public' => 'Rendre cette collection publique (partageable)',
    'collections.save' => 'Enregistrer',
    'collections.cancel' => 'Annuler',
    'collections.delete' => 'Supprimer',
    'collections.duplicate' => 'Dupliquer',
    'collections.share' => 'Partager',
    'collections.export' => 'Exporter',
    'collections.view' => 'Voir',
    'collections.edit' => 'Modifier',
    'collections.back' => '← Retour',
    'collections.visibility' => 'Visibilité',
    'collections.public' => '🌐 Public',
    'collections.private' => '🔒 Privé',
    'collections.items' => 'Éléments',
    'collections.item_count' => '{count} éléments',
    'collections.empty' => 'Aucune collection',
    'collections.empty_hint' => 'Créez des collections pour organiser vos meubles en listes partageables.',
    'collections.collection_empty' => 'Collection vide',
    'collections.collection_empty_hint' => 'Parcourez le catalogue et ajoutez des éléments à cette collection.',
    'collections.confirm_delete' => 'Supprimer la collection "{name}" ? Cette action est irréversible.',
    'collections.deleted' => 'Collection supprimée',
    'collections.duplicated' => 'Collection dupliquée : {name}',
    'collections.link_copied' => 'Lien de la collection copié !',
    'collections.confirm_duplicate' => 'Créer une copie de "{name}" ?',
    'collections.nothing_to_export' => 'Aucun élément à exporter dans la collection',
    'collections.added' => 'Ajouté à la collection',
    'collections.removed' => 'Retiré de la collection',
    'collections.reordered' => 'Éléments réorganisés',
    'collections.reorder_failed' => 'Échec de la réorganisation',
    'collections.confirm_remove_item' => 'Retirer cet élément de la collection ?',
    'collections.pick_title' => 'Ajouter à une collection',
    'collections.no_collections' => 'Vous n\'avez pas encore créé de collections.',
    'collections.create_first' => 'Créer une collection',
    'collections.new_collection' => '+ Nouvelle collection',
    'collections.added_status' => '✓ Ajouté',
    'collections.not_found' => 'Collection non trouvée',
    'collections.public_disabled' => 'Les collections publiques sont actuellement désactivées.',
    'collections.will_be_private' => 'Cette collection sera privée.',
    'collections.currently_public_warning' => 'Cette collection est actuellement publique mais sera définie comme privée lors de l\'enregistrement.',
    
    // ===========================================
    // SUBMISSIONS
    // ===========================================
    'submissions.title' => 'Mes Soumissions',
    'submissions.submit' => 'Soumettre un meuble',
    'submissions.submit_new' => 'Soumettre un nouveau meuble',
    'submissions.suggest_edit' => 'Suggérer une modification',
    'submissions.submit_edit' => 'Soumettre la modification',
    'submissions.type' => 'Type',
    'submissions.type_new' => '✨ Nouveau',
    'submissions.type_edit' => '✏️ Modification',
    'submissions.status' => 'Statut',
    'submissions.status_pending' => '⏳ En attente',
    'submissions.status_approved' => '✓ Approuvé',
    'submissions.status_rejected' => '✕ Rejeté',
    'submissions.submitted' => 'Soumis',
    'submissions.view' => 'Voir',
    'submissions.cancel' => 'Annuler',
    'submissions.confirm_cancel' => 'Annuler cette soumission ? Cette action est irréversible.',
    'submissions.cancelled' => 'Soumission annulée',
    'submissions.empty' => 'Aucune soumission',
    'submissions.empty_hint' => 'Soumettez de nouveaux meubles au catalogue ou suggérez des modifications.',
    'submissions.furniture_name' => 'Nom du meuble',
    'submissions.furniture_name_placeholder' => 'ex: Black Double Bed',
    'submissions.furniture_name_help' => 'Le nom exact du prop utilisé en jeu',
    'submissions.price' => 'Prix',
    'submissions.price_help' => 'Par défaut : 250$ (prix le plus courant en jeu)',
    'submissions.image_url' => 'URL de l\'image',
    'submissions.image_url_placeholder' => 'https://... ou /images/...',
    'submissions.image_url_help' => 'URL d\'une image du meuble (sera traitée et convertie)',
    'submissions.edit_notes' => 'Notes de modification (optionnel)',
    'submissions.edit_notes_placeholder' => 'Expliquez ce que vous modifiez et pourquoi...',
    'submissions.categories' => 'Catégories',
    'submissions.categories_help' => '(premier sélectionné = principal)',
    'submissions.tags' => 'Tags',
    'submissions.category_specific_tags' => 'Tags spécifiques à la catégorie',
    'submissions.editing' => 'Modification de :',
    'submissions.editing_note' => 'Vos modifications seront examinées par un administrateur avant d\'être appliquées.',
    'submissions.new_note' => 'Votre soumission sera examinée par un administrateur avant d\'être ajoutée au catalogue.',
    'submissions.feedback' => 'Commentaire du modérateur :',
    'submissions.reviewed_on' => 'Examiné le {date}',
    'submissions.details' => 'Détails de la soumission',
    'submissions.received' => 'Soumission reçue',
    'submissions.not_found' => 'Soumission non trouvée',
    'submissions.disabled' => 'Les soumissions sont actuellement désactivées.',
    'submissions.cannot_edit' => 'Impossible de modifier une soumission {status}',
    'submissions.original_item' => 'Élément original',
    'submissions.proposed_changes' => 'Modifications proposées',
    
    // ===========================================
    // DASHBOARD
    // ===========================================
    'dashboard.title' => 'Mon Tableau de bord',
    'dashboard.overview' => 'Aperçu',
    'dashboard.favorites' => 'Favoris',
    'dashboard.collections' => 'Collections',
    'dashboard.submissions' => 'Soumissions',
    'dashboard.browse' => 'Parcourir le catalogue',
    'dashboard.logged_in_as' => 'Connecté en tant que',
    'dashboard.quick_actions' => 'Actions rapides',
    'dashboard.recently_viewed' => 'Vus récemment',
    'dashboard.pending_review' => 'En attente de révision',
    
    // ===========================================
    // PAGINATION
    // ===========================================
    'pagination.previous' => '← Précédent',
    'pagination.next' => 'Suivant →',
    'pagination.previous_page' => 'Page précédente',
    'pagination.next_page' => 'Page suivante',
    'pagination.page_info' => 'Page {page} sur {total_pages} ({total} éléments)',
    'pagination.items' => '{total} élément|{total} éléments',
    
    // ===========================================
    // EMPTY STATES
    // ===========================================
    'empty.loading' => 'Chargement des meubles...',
    'empty.please_wait' => 'Veuillez patienter',
    'empty.welcome' => 'Bienvenue !',
    'empty.start_browsing' => 'Commencez à parcourir les meubles',
    'empty.not_found' => 'Meuble non trouvé',
    
    // ===========================================
    // ERRORS & MESSAGES
    // ===========================================
    'error.generic' => 'Une erreur s\'est produite',
    'error.loading' => 'Échec du chargement',
    'error.network' => 'Erreur réseau',
    'error.network_retry' => 'Erreur réseau. Veuillez réessayer.',
    'error.not_found' => 'Non trouvé',
    'error.failed_to_load' => 'Impossible de charger le meuble',
    
    'success.saved' => 'Enregistré avec succès',
    'success.created' => 'Créé avec succès',
    'success.updated' => 'Mis à jour avec succès',
    'success.deleted' => 'Supprimé avec succès',
    
    // ===========================================
    // FORMS
    // ===========================================
    'form.required' => 'Requis',
    'form.optional' => 'Optionnel',
    'form.save' => 'Enregistrer',
    'form.saving' => 'Enregistrement...',
    'form.cancel' => 'Annuler',
    'form.create' => 'Créer',
    'form.search' => 'Rechercher',
    'form.search_placeholder' => 'Rechercher...',
    
    // ===========================================
    // TABLES
    // ===========================================
    'table.image' => 'Image',
    'table.name' => 'Nom',
    'table.category' => 'Catégorie',
    'table.price' => 'Prix',
    'table.actions' => 'Actions',
    'table.description' => 'Description',
    'table.no_results' => 'Aucun élément ne correspond à votre recherche',
    'table.drag_reorder' => 'Glisser pour réorganiser',
    
    // ===========================================
    // FOOTER
    // ===========================================
    'footer.made_by' => 'Fait avec ❤️ par',
    'footer.for_community' => 'pour la communauté GTA World',
    'footer.not_affiliated' => 'Non affilié à GTA World',
    'footer.forums' => 'Forums',
    
    // ===========================================
    // SETUP
    // ===========================================
    'setup.required' => 'Configuration requise',
    'setup.not_configured' => 'L\'application n\'est pas encore configurée.',
    'setup.go_to_admin' => 'Aller au panneau d\'administration',
    
    // ===========================================
    // LOGIN
    // ===========================================
    'login.error_title' => 'Échec de la connexion',
    'login.return_to_catalog' => 'Retour au catalogue',
    'login.rate_limited' => 'Trop de tentatives de connexion. Veuillez réessayer dans quelques minutes.',
    'login.invalid_state' => 'Paramètre d\'état invalide. Veuillez réessayer de vous connecter.',
    'login.denied' => 'Autorisation refusée',
    'login.no_code' => 'Code d\'autorisation non reçu.',
    'login.token_failed' => 'Impossible d\'obtenir le jeton d\'accès. Veuillez réessayer.',
    'login.user_failed' => 'Impossible de récupérer les données utilisateur. Veuillez réessayer.',
    'login.invalid_data' => 'Données utilisateur invalides reçues.',
    'login.process_failed' => 'Échec du traitement de la connexion. Veuillez réessayer.',
    'login.banned' => 'Votre compte a été banni. Raison : {reason}',
    'login.oauth_not_configured' => 'OAuth n\'est pas configuré pour cette communauté. Veuillez contacter l\'administrateur.',
    'login.community_disabled' => 'Cette communauté est actuellement désactivée. Veuillez contacter l\'administrateur.',
    'login.registration_disabled' => 'L\'inscription de nouveaux utilisateurs est actuellement désactivée. Veuillez contacter l\'administrateur.',
    
    // ===========================================
    // MAINTENANCE MODE
    // ===========================================
    'maintenance.title' => 'Maintenance en cours',
    'maintenance.message' => 'Nous effectuons actuellement une maintenance programmée. Veuillez revenir bientôt.',
    'maintenance.admin_notice' => 'Le mode maintenance est actif. Seuls les administrateurs peuvent accéder au site.',
];
