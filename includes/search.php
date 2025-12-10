<?php
/**
 * GTAW Furniture Catalog - Enhanced Search System
 * 
 * Features:
 * - FULLTEXT search with relevance scoring
 * - Optimized synonym expansion with O(1) reverse lookup
 * - Query tokenization for multi-word searches
 * - Basic English stemming for plurals
 * - Weighted synonyms from database with static fallback
 * - Search analytics logging
 * - Fuzzy matching for typo tolerance (Levenshtein)
 * - Category-aware search with relevance boosting
 * - Synonym auto-discovery from search patterns
 * - Multi-language support (English, French)
 */

declare(strict_types=1);

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'search.php') {
    http_response_code(403);
    exit('Direct access forbidden');
}

// ============================================
// FUZZY MATCHING SYSTEM
// ============================================

/**
 * Fuzzy matcher for typo tolerance using Levenshtein distance
 */
class FuzzyMatcher
{
    // Maximum allowed edit distance for fuzzy matching
    private const MAX_DISTANCE_SHORT = 1;  // For words <= 4 chars
    private const MAX_DISTANCE_MEDIUM = 2; // For words 5-7 chars
    private const MAX_DISTANCE_LONG = 3;   // For words 8+ chars
    
    // Minimum word length for fuzzy matching
    private const MIN_LENGTH = 3;
    
    // Cache for fuzzy matches
    private static array $matchCache = [];
    
    /**
     * Find fuzzy matches for a term from a vocabulary list
     * 
     * @param string $term The search term (potentially misspelled)
     * @param array $vocabulary List of valid terms to match against
     * @param int|null $maxResults Maximum number of results to return
     * @return array{term: string, distance: int, score: float}[]
     */
    public static function findMatches(string $term, array $vocabulary, ?int $maxResults = 3): array
    {
        $term = strtolower(trim($term));
        $termLen = strlen($term);
        
        // Too short for fuzzy matching
        if ($termLen < self::MIN_LENGTH) {
            return [];
        }
        
        // Check cache
        $cacheKey = $term . '|' . count($vocabulary);
        if (isset(self::$matchCache[$cacheKey])) {
            return array_slice(self::$matchCache[$cacheKey], 0, $maxResults);
        }
        
        $maxDistance = self::getMaxDistance($termLen);
        $matches = [];
        
        foreach ($vocabulary as $word) {
            $word = strtolower(trim($word));
            $wordLen = strlen($word);
            
            // Skip if lengths differ too much (optimization)
            if (abs($termLen - $wordLen) > $maxDistance) {
                continue;
            }
            
            // Skip exact matches
            if ($term === $word) {
                continue;
            }
            
            // Calculate Levenshtein distance
            $distance = levenshtein($term, $word);
            
            if ($distance <= $maxDistance) {
                // Calculate similarity score (0-1, higher is better)
                $maxLen = max($termLen, $wordLen);
                $score = 1 - ($distance / $maxLen);
                
                $matches[] = [
                    'term' => $word,
                    'distance' => $distance,
                    'score' => round($score, 3),
                ];
            }
        }
        
        // Sort by score (descending), then by distance (ascending)
        usort($matches, function($a, $b) {
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }
            return $a['distance'] <=> $b['distance'];
        });
        
        // Cache results
        self::$matchCache[$cacheKey] = $matches;
        
        // Limit cache size
        if (count(self::$matchCache) > 100) {
            array_shift(self::$matchCache);
        }
        
        return array_slice($matches, 0, $maxResults);
    }
    
    /**
     * Get max allowed edit distance based on word length
     */
    private static function getMaxDistance(int $length): int
    {
        if ($length <= 4) {
            return self::MAX_DISTANCE_SHORT;
        }
        if ($length <= 7) {
            return self::MAX_DISTANCE_MEDIUM;
        }
        return self::MAX_DISTANCE_LONG;
    }
    
    /**
     * Check if two terms are fuzzy matches
     */
    public static function isFuzzyMatch(string $term1, string $term2): bool
    {
        $term1 = strtolower(trim($term1));
        $term2 = strtolower(trim($term2));
        
        if ($term1 === $term2) {
            return false; // Exact match, not fuzzy
        }
        
        $maxDistance = self::getMaxDistance(max(strlen($term1), strlen($term2)));
        return levenshtein($term1, $term2) <= $maxDistance;
    }
    
    /**
     * Get "did you mean" suggestion for a search term
     * Only suggests if the term is NOT in vocabulary (real typo) and has high similarity
     * 
     * @param string $term The search term
     * @param array $vocabulary Valid vocabulary
     * @return string|null Suggested correction or null
     */
    public static function getSuggestion(string $term, array $vocabulary): ?string
    {
        $term = strtolower(trim($term));
        
        // If the term is already in vocabulary, it's not a typo - don't suggest anything
        if (in_array($term, $vocabulary, true)) {
            return null;
        }
        
        // Only suggest for real typos (very high similarity, single character difference)
        $matches = self::findMatches($term, $vocabulary, 1);
        if (empty($matches)) {
            return null;
        }
        
        $bestMatch = $matches[0];
        
        // Very strict criteria for typo suggestions:
        // 1. Very high similarity score (85%+)
        // 2. Only 1 character difference (for short/medium words)
        // 3. Lengths should be similar (not suggesting completely different words)
        $termLen = strlen($term);
        $matchLen = strlen($bestMatch['term']);
        $lengthDiff = abs($termLen - $matchLen);
        
        // Require very high similarity
        if ($bestMatch['score'] < 0.85) {
            return null;
        }
        
        // For short words (3-5 chars), only allow 1 character difference
        if ($termLen <= 5 && $bestMatch['distance'] > 1) {
            return null;
        }
        
        // Lengths should be very similar (max 1 character difference)
        if ($lengthDiff > 1) {
            return null;
        }
        
        // Don't suggest if it's a completely different word (same length but many changes)
        if ($lengthDiff === 0 && $bestMatch['distance'] > 1) {
            return null;
        }
        
        return $bestMatch['term'];
    }
    
    /**
     * Soundex-based phonetic matching for common pronunciation errors
     * 
     * @param string $term Search term
     * @param array $vocabulary Valid vocabulary
     * @return array Phonetically similar terms
     */
    public static function findPhoneticMatches(string $term, array $vocabulary): array
    {
        $termSoundex = soundex($term);
        $matches = [];
        
        foreach ($vocabulary as $word) {
            if ($term !== $word && soundex($word) === $termSoundex) {
                $matches[] = $word;
            }
        }
        
        return $matches;
    }
}

// ============================================
// MULTI-LANGUAGE SUPPORT
// ============================================

/**
 * Language detection and multi-language search support
 */
class LanguageSupport
{
    // Supported languages
    public const LANG_EN = 'en';
    public const LANG_FR = 'fr';
    
    // French common words for language detection
    private const FRENCH_MARKERS = [
        'le', 'la', 'les', 'un', 'une', 'des', 'du', 'de', 'et', 'ou', 'avec', 
        'pour', 'dans', 'sur', 'sous', 'entre', 'blanc', 'noir', 'rouge', 'bleu',
        'vert', 'jaune', 'grande', 'petit', 'petite', 'moderne', 'ancien', 'ancienne',
        'bois', 'métal', 'verre', 'cuir', 'tissu', 'chaise', 'table', 'lit', 
        'canapé', 'armoire', 'lampe', 'bureau', 'fauteuil', 'étagère', 'meuble'
    ];
    
    // French to English translation map for common furniture terms
    private const FR_TO_EN = [
        // Furniture
        'chaise' => 'chair',
        'chaises' => 'chairs',
        'table' => 'table',
        'tables' => 'tables',
        'lit' => 'bed',
        'lits' => 'beds',
        'canapé' => 'sofa',
        'canape' => 'sofa',
        'sofa' => 'sofa',
        'fauteuil' => 'armchair',
        'fauteuils' => 'armchairs',
        'bureau' => 'desk',
        'bureaux' => 'desks',
        'armoire' => 'wardrobe',
        'armoires' => 'wardrobes',
        'commode' => 'dresser',
        'commodes' => 'dressers',
        'étagère' => 'shelf',
        'etagere' => 'shelf',
        'étagères' => 'shelves',
        'lampe' => 'lamp',
        'lampes' => 'lamps',
        'lustre' => 'chandelier',
        'lustres' => 'chandeliers',
        'miroir' => 'mirror',
        'miroirs' => 'mirrors',
        'tapis' => 'rug',
        'rideau' => 'curtain',
        'rideaux' => 'curtains',
        'coussin' => 'pillow',
        'coussins' => 'pillows',
        'couverture' => 'blanket',
        'matelas' => 'mattress',
        'oreiller' => 'pillow',
        'tabouret' => 'stool',
        'tabourets' => 'stools',
        'banc' => 'bench',
        'bancs' => 'benches',
        'buffet' => 'sideboard',
        'vitrine' => 'display case',
        'bibliothèque' => 'bookshelf',
        'bibliotheque' => 'bookshelf',
        'placard' => 'cupboard',
        'placards' => 'cupboards',
        'tiroir' => 'drawer',
        'tiroirs' => 'drawers',
        'meuble' => 'furniture',
        'meubles' => 'furniture',
        
        // Materials
        'bois' => 'wood',
        'métal' => 'metal',
        'metal' => 'metal',
        'verre' => 'glass',
        'cuir' => 'leather',
        'tissu' => 'fabric',
        'plastique' => 'plastic',
        'marbre' => 'marble',
        'pierre' => 'stone',
        'céramique' => 'ceramic',
        'ceramique' => 'ceramic',
        'acier' => 'steel',
        'fer' => 'iron',
        'laiton' => 'brass',
        'chrome' => 'chrome',
        'rotin' => 'rattan',
        'osier' => 'wicker',
        'bambou' => 'bamboo',
        
        // Colors
        'blanc' => 'white',
        'blanche' => 'white',
        'noir' => 'black',
        'noire' => 'black',
        'rouge' => 'red',
        'bleu' => 'blue',
        'bleue' => 'blue',
        'vert' => 'green',
        'verte' => 'green',
        'jaune' => 'yellow',
        'orange' => 'orange',
        'rose' => 'pink',
        'violet' => 'purple',
        'violette' => 'purple',
        'gris' => 'gray',
        'grise' => 'gray',
        'brun' => 'brown',
        'brune' => 'brown',
        'marron' => 'brown',
        'beige' => 'beige',
        'crème' => 'cream',
        'creme' => 'cream',
        'doré' => 'gold',
        'dore' => 'gold',
        'argenté' => 'silver',
        'argente' => 'silver',
        
        // Styles
        'moderne' => 'modern',
        'classique' => 'classic',
        'vintage' => 'vintage',
        'rustique' => 'rustic',
        'industriel' => 'industrial',
        'industrielle' => 'industrial',
        'minimaliste' => 'minimalist',
        'contemporain' => 'contemporary',
        'contemporaine' => 'contemporary',
        'ancien' => 'antique',
        'ancienne' => 'antique',
        'luxueux' => 'luxurious',
        'luxueuse' => 'luxurious',
        
        // Rooms
        'salon' => 'living room',
        'chambre' => 'bedroom',
        'cuisine' => 'kitchen',
        'salle de bain' => 'bathroom',
        'salle à manger' => 'dining room',
        'salle a manger' => 'dining room',
        'entrée' => 'entrance',
        'entree' => 'entrance',
        'jardin' => 'garden',
        'terrasse' => 'terrace',
        'balcon' => 'balcony',
        'garage' => 'garage',
        'grenier' => 'attic',
        'cave' => 'basement',
        
        // Sizes
        'petit' => 'small',
        'petite' => 'small',
        'grand' => 'large',
        'grande' => 'large',
        'moyen' => 'medium',
        'moyenne' => 'medium',
        
        // Appliances
        'réfrigérateur' => 'refrigerator',
        'refrigerateur' => 'refrigerator',
        'frigo' => 'fridge',
        'four' => 'oven',
        'micro-ondes' => 'microwave',
        'micro ondes' => 'microwave',
        'lave-vaisselle' => 'dishwasher',
        'lave vaisselle' => 'dishwasher',
        'machine à laver' => 'washing machine',
        'machine a laver' => 'washing machine',
        'télévision' => 'television',
        'television' => 'television',
        'télé' => 'tv',
        'tele' => 'tv',
        'ordinateur' => 'computer',
        'climatiseur' => 'air conditioner',
        'ventilateur' => 'fan',
        
        // Other
        'neuf' => 'new',
        'neuve' => 'new',
        'occasion' => 'used',
        'usagé' => 'used',
        'usage' => 'used',
        'confortable' => 'comfortable',
        'élégant' => 'elegant',
        'elegant' => 'elegant',
        'pratique' => 'practical',
        'solide' => 'solid',
        'léger' => 'light',
        'leger' => 'light',
        'lourd' => 'heavy',
        'lourde' => 'heavy',
    ];
    
    /**
     * Detect the language of a search query
     * 
     * @param string $query The search query
     * @return string Language code (en or fr)
     */
    public static function detectLanguage(string $query): string
    {
        $query = strtolower(trim($query));
        $words = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY);
        
        $frenchScore = 0;
        foreach ($words as $word) {
            // Check for French markers
            if (in_array($word, self::FRENCH_MARKERS, true)) {
                $frenchScore += 2;
            }
            // Check for French characters
            if (preg_match('/[éèêëàâäùûüôöîïç]/u', $word)) {
                $frenchScore += 3;
            }
            // Check for French translations
            if (isset(self::FR_TO_EN[$word])) {
                $frenchScore += 2;
            }
        }
        
        // If more than 30% of words are French indicators, treat as French
        $threshold = count($words) * 0.3;
        return $frenchScore >= max(2, $threshold) ? self::LANG_FR : self::LANG_EN;
    }
    
    /**
     * Translate French search terms to English
     * Only translates if locale is set to French
     * 
     * @param string $query French query
     * @return array{translated: string, terms: string[], original_lang: string}
     */
    public static function translateQuery(string $query): array
    {
        $query = strtolower(trim($query));
        
        // Check current locale - only translate if locale is French
        $locale = function_exists('getCurrentLocale') ? getCurrentLocale() : 'en';
        if ($locale !== 'fr') {
            // English locale: don't translate, just return query as-is
            return [
                'translated' => $query,
                'terms' => [$query],
                'original_lang' => self::LANG_EN,
            ];
        }
        
        // French locale: detect and translate if needed
        $lang = self::detectLanguage($query);
        
        if ($lang === self::LANG_EN) {
            return [
                'translated' => $query,
                'terms' => [$query],
                'original_lang' => self::LANG_EN,
            ];
        }
        
        // Translate French to English (when locale is French)
        $words = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY);
        $translatedWords = [];
        $additionalTerms = [];
        
        foreach ($words as $word) {
            // Remove accents for lookup
            $normalized = self::removeAccents($word);
            
            if (isset(self::FR_TO_EN[$word])) {
                $translatedWords[] = self::FR_TO_EN[$word];
                $additionalTerms[] = $word; // Keep French term too
            } elseif (isset(self::FR_TO_EN[$normalized])) {
                $translatedWords[] = self::FR_TO_EN[$normalized];
                $additionalTerms[] = $word;
            } else {
                $translatedWords[] = $word; // Keep as-is if not found
            }
        }
        
        $translated = implode(' ', $translatedWords);
        
        return [
            'translated' => $translated,
            'terms' => array_unique(array_merge([$translated, $query], $additionalTerms)),
            'original_lang' => self::LANG_FR,
        ];
    }
    
    /**
     * Remove French accents from a string
     */
    public static function removeAccents(string $str): string
    {
        $accents = ['é', 'è', 'ê', 'ë', 'à', 'â', 'ä', 'ù', 'û', 'ü', 'ô', 'ö', 'î', 'ï', 'ç'];
        $plain = ['e', 'e', 'e', 'e', 'a', 'a', 'a', 'u', 'u', 'u', 'o', 'o', 'i', 'i', 'c'];
        return str_replace($accents, $plain, $str);
    }
    
    /**
     * Get French synonyms for an English term
     * 
     * @param string $englishTerm
     * @return array French equivalents
     */
    public static function getFrenchEquivalents(string $englishTerm): array
    {
        $englishTerm = strtolower(trim($englishTerm));
        $equivalents = [];
        
        foreach (self::FR_TO_EN as $french => $english) {
            if ($english === $englishTerm) {
                $equivalents[] = $french;
            }
        }
        
        return $equivalents;
    }
}

// ============================================
// CATEGORY-AWARE SEARCH
// ============================================

/**
 * Category-aware search with relevance boosting
 */
class CategoryAwareSearch
{
    // Category boost multipliers
    private const CATEGORY_BOOST = 1.5;  // Boost for matching active category
    private const RELATED_CATEGORY_BOOST = 1.2; // Boost for related categories
    
    // Related category mappings
    private const RELATED_CATEGORIES = [
        'seating' => ['living room', 'office', 'outdoor'],
        'tables' => ['dining', 'office', 'living room'],
        'beds' => ['bedroom', 'guest room'],
        'storage' => ['bedroom', 'office', 'garage'],
        'lighting' => ['living room', 'bedroom', 'office'],
        'kitchen' => ['appliances', 'dining'],
        'bathroom' => ['fixtures', 'storage'],
        'office' => ['storage', 'seating', 'electronics'],
        'outdoor' => ['garden', 'patio'],
    ];
    
    /**
     * Enhance search results with category relevance
     * 
     * @param array $results Search results
     * @param string|null $activeCategory Currently selected category slug
     * @param PDO|null $pdo Database connection for category lookups
     * @return array Enhanced results with boosted relevance
     */
    public static function enhanceResults(array $results, ?string $activeCategory, ?PDO $pdo = null): array
    {
        if ($activeCategory === null || empty($results)) {
            return $results;
        }
        
        $activeCategory = strtolower(trim($activeCategory));
        $relatedCategories = self::RELATED_CATEGORIES[$activeCategory] ?? [];
        
        foreach ($results as &$item) {
            $item['_relevance_boost'] = 1.0;
            
            // Check if item's categories match
            if (isset($item['categories']) && is_array($item['categories'])) {
                foreach ($item['categories'] as $category) {
                    $catSlug = strtolower($category['slug'] ?? '');
                    
                    if ($catSlug === $activeCategory) {
                        $item['_relevance_boost'] = self::CATEGORY_BOOST;
                        break;
                    } elseif (in_array($catSlug, $relatedCategories, true)) {
                        $item['_relevance_boost'] = max($item['_relevance_boost'], self::RELATED_CATEGORY_BOOST);
                    }
                }
            }
        }
        
        // Re-sort results by boosted relevance
        usort($results, function($a, $b) {
            $boostA = $a['_relevance_boost'] ?? 1.0;
            $boostB = $b['_relevance_boost'] ?? 1.0;
            
            if ($boostA !== $boostB) {
                return $boostB <=> $boostA;
            }
            
            // Fall back to name comparison
            return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
        });
        
        // Remove internal boost field
        foreach ($results as &$item) {
            unset($item['_relevance_boost']);
        }
        
        return $results;
    }
    
    /**
     * Suggest category for a search query based on actual database structure
     * Uses category names, slugs, and associated tags for accurate matching
     * 
     * @param string $query Search query
     * @param PDO $pdo Database connection
     * @return string|null Suggested category slug or null
     */
    public static function suggestCategory(string $query, PDO $pdo): ?string
    {
        static $categoryKeywords = null;
        
        // Build category keywords from database (cached per request)
        if ($categoryKeywords === null) {
            $categoryKeywords = self::buildCategoryKeywords($pdo);
        }
        
        if (empty($categoryKeywords)) {
            return null;
        }
        
        $query = strtolower(trim($query));
        $queryWords = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY);
        
        $scores = [];
        foreach ($categoryKeywords as $categorySlug => $keywords) {
            $score = 0;
            
            // First check if the full query matches any keyword (compound terms)
            foreach ($keywords as $keyword => $keywordData) {
                $keywordLower = strtolower($keyword);
                
                // Full query exact match (highest score)
                if ($query === $keywordLower) {
                    $score += $keywordData['weight'] * 10;
                }
                // Full query contains keyword or vice versa
                elseif (strlen($query) >= 3 && strlen($keywordLower) >= 3) {
                    if (strpos($query, $keywordLower) !== false || strpos($keywordLower, $query) !== false) {
                        $score += $keywordData['weight'] * 6;
                    }
                }
            }
            
            // Then check individual words
            foreach ($queryWords as $queryWord) {
                $queryWordStem = SynonymManager::stem($queryWord);
                
                // Check exact matches first (highest score)
                foreach ($keywords as $keyword => $keywordData) {
                    $keywordLower = strtolower($keyword);
                    $keywordStem = $keywordData['stem'] ?? SynonymManager::stem($keyword);
                    
                    // Exact match
                    if ($queryWord === $keywordLower) {
                        $score += $keywordData['weight'] * 5;
                    }
                    // Stem match
                    elseif ($queryWordStem === $keywordStem) {
                        $score += $keywordData['weight'] * 3;
                    }
                    // Partial match (word contains keyword or vice versa)
                    elseif (strlen($queryWord) >= 3 && strlen($keywordLower) >= 3) {
                        if (strpos($keywordLower, $queryWord) !== false || strpos($queryWord, $keywordLower) !== false) {
                            $score += $keywordData['weight'] * 1.5;
                        }
                    }
                }
            }
            
            if ($score > 0) {
                $scores[$categorySlug] = $score;
            }
        }
        
        if (empty($scores)) {
            return null;
        }
        
        // Sort by score descending
        arsort($scores);
        
        // Only return if score is above threshold
        $bestScore = reset($scores);
        $bestCategory = array_key_first($scores);
        
        // Minimum score threshold to avoid weak suggestions
        return $bestScore >= 3.0 ? $bestCategory : null;
    }
    
    /**
     * Build category keywords map from database
     * Includes category names, slugs, and all tags associated with categories
     * 
     * @param PDO $pdo Database connection
     * @return array<string, array<string, array{weight: float, stem: string}>>
     */
    private static function buildCategoryKeywords(PDO $pdo): array
    {
        try {
            // Check if tables exist
            $stmt = $pdo->query("SHOW TABLES LIKE 'categories'");
            if ($stmt->rowCount() === 0) {
                return [];
            }
            
            // Get all categories with their names and slugs
            $stmt = $pdo->query('
                SELECT id, name, slug 
                FROM categories 
                ORDER BY sort_order ASC
            ');
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($categories)) {
                return [];
            }
            
            $categoryKeywords = [];
            
            foreach ($categories as $category) {
                $categorySlug = strtolower(trim($category['slug']));
                $categoryName = strtolower(trim($category['name']));
                $categoryId = (int) $category['id'];
                
                $keywords = [];
                
                // Add category slug words
                $slugWords = preg_split('/[\s\-&]+/', $categorySlug, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($slugWords as $word) {
                    $word = trim($word);
                    if (strlen($word) >= 2) {
                        $keywords[$word] = ['weight' => 5.0, 'stem' => SynonymManager::stem($word)];
                    }
                }
                
                // Add category name words (higher weight)
                $nameWords = preg_split('/[\s\-&]+/', $categoryName, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($nameWords as $word) {
                    $word = trim($word);
                    if (strlen($word) >= 2) {
                        // Skip common words
                        if (!in_array($word, ['and', 'the', 'a', 'an', 'of', 'for', 'with', '&'])) {
                            $keywords[$word] = ['weight' => 6.0, 'stem' => SynonymManager::stem($word)];
                        }
                    }
                }
                
                // Add common variations based on category slug
                // Handle "tables-desks" -> also match "table" and "desk" separately
                if (strpos($categorySlug, '-') !== false || strpos($categorySlug, '&') !== false) {
                    foreach ($slugWords as $word) {
                        $word = trim($word);
                        // Remove plural endings for better matching
                        if (strlen($word) > 3) {
                            $singular = SynonymManager::stem($word);
                            if ($singular !== $word && !isset($keywords[$singular])) {
                                $keywords[$singular] = ['weight' => 4.5, 'stem' => $singular];
                            }
                        }
                    }
                }
                
                // Get tags associated with this category through tag groups
                try {
                    $stmt = $pdo->prepare('
                        SELECT DISTINCT t.name, t.slug
                        FROM tags t
                        INNER JOIN tag_groups tg ON t.group_id = tg.id
                        INNER JOIN category_tag_groups ctg ON tg.id = ctg.tag_group_id
                        WHERE ctg.category_id = ?
                        AND t.name IS NOT NULL
                    ');
                    $stmt->execute([$categoryId]);
                    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($tags as $tag) {
                        $tagName = strtolower(trim($tag['name']));
                        $tagSlug = strtolower(trim($tag['slug']));
                        
                        // Stop words to filter out
                        $stopWords = ['type', 'style', 'purpose', 'the', 'a', 'an', 'and', 'or', 'with', 'for', 'of', 'in', 'on', 'at', 'to', 'from'];
                        $prefixWords = ['elec', 'comm', 'ent', 'ind', 'med', 'gov', 'illegal', 'veh', 'pet', 'food', 'beverage', 'serving', 'toy', 'childrens', 'furniture', 'effect', 'prop', 'accessory', 'clothing', 'bag', 'seating', 'table', 'bed', 'storage', 'light', 'decor', 'textile', 'element', 'outdoor'];
                        
                        // Add full tag name as a keyword (high weight for exact matches)
                        $cleanTagName = preg_replace('/\s*\([^)]*\)\s*/', '', $tagName); // Remove parenthetical content
                        if (strlen($cleanTagName) >= 2) {
                            $keywords[$cleanTagName] = ['weight' => 4.5, 'stem' => SynonymManager::stem($cleanTagName)];
                        }
                        
                        // Add tag name words (category-specific tags have high weight)
                        $tagWords = preg_split('/[\s\-&()]+/', $tagName, -1, PREG_SPLIT_NO_EMPTY);
                        foreach ($tagWords as $word) {
                            $word = trim($word);
                            if (strlen($word) >= 2 && !in_array($word, $stopWords)) {
                                if (!isset($keywords[$word]) || $keywords[$word]['weight'] < 4.0) {
                                    $keywords[$word] = ['weight' => 4.0, 'stem' => SynonymManager::stem($word)];
                                }
                            }
                        }
                        
                        // Add tag slug words (remove category prefixes first)
                        $cleanSlug = $tagSlug;
                        // Remove category prefix (e.g., "seating-sofa" -> "sofa")
                        foreach ($prefixWords as $prefix) {
                            if (strpos($cleanSlug, $prefix . '-') === 0) {
                                $cleanSlug = substr($cleanSlug, strlen($prefix) + 1);
                                break;
                            }
                        }
                        
                        $slugWords = preg_split('/[\s\-]+/', $cleanSlug, -1, PREG_SPLIT_NO_EMPTY);
                        foreach ($slugWords as $word) {
                            $word = trim($word);
                            if (strlen($word) >= 2 && !in_array($word, array_merge($stopWords, $prefixWords))) {
                                if (!isset($keywords[$word]) || $keywords[$word]['weight'] < 3.0) {
                                    $keywords[$word] = ['weight' => 3.0, 'stem' => SynonymManager::stem($word)];
                                }
                            }
                        }
                    }
                } catch (PDOException $e) {
                    // If tag groups table doesn't exist yet, continue without tags
                    error_log('CategoryAwareSearch: Could not load tags for categories: ' . $e->getMessage());
                }
                
                if (!empty($keywords)) {
                    $categoryKeywords[$categorySlug] = $keywords;
                }
            }
            
            return $categoryKeywords;
            
        } catch (PDOException $e) {
            error_log('CategoryAwareSearch: Failed to build category keywords: ' . $e->getMessage());
            return [];
        }
    }
}

// ============================================
// SYNONYM AUTO-DISCOVERY
// ============================================

/**
 * Automatic synonym discovery from search patterns
 */
class SynonymAutoDiscovery
{
    /**
     * Analyze search patterns to discover potential synonyms
     * 
     * @param PDO $pdo Database connection
     * @param int $days Days to analyze
     * @return array Discovered synonym suggestions
     */
    public static function analyzeSearchPatterns(PDO $pdo, int $days = 30): array
    {
        $suggestions = [];
        
        // 1. Find zero-result searches that might need synonyms
        $zeroResults = self::getZeroResultPatterns($pdo, $days);
        foreach ($zeroResults as $pattern) {
            $suggestions[] = [
                'type' => 'zero_result',
                'term' => $pattern['query_normalized'],
                'searches' => (int) $pattern['search_count'],
                'suggestion' => self::suggestSynonym($pdo, $pattern['query_normalized']),
            ];
        }
        
        // 2. Find session patterns (user searched A, then B)
        $sessionPatterns = self::getSessionPatterns($pdo, $days);
        foreach ($sessionPatterns as $pattern) {
            if ($pattern['confidence'] >= 0.5) {
                $suggestions[] = [
                    'type' => 'session_pattern',
                    'term' => $pattern['first_query'],
                    'related_term' => $pattern['second_query'],
                    'confidence' => $pattern['confidence'],
                    'occurrences' => (int) $pattern['occurrences'],
                ];
            }
        }
        
        // 3. Find fuzzy matches for zero-result searches
        $vocabulary = self::getSearchVocabulary($pdo);
        foreach ($zeroResults as $pattern) {
            $fuzzyMatches = FuzzyMatcher::findMatches($pattern['query_normalized'], $vocabulary, 2);
            if (!empty($fuzzyMatches)) {
                $suggestions[] = [
                    'type' => 'fuzzy_match',
                    'term' => $pattern['query_normalized'],
                    'matches' => $fuzzyMatches,
                    'searches' => (int) $pattern['search_count'],
                ];
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Get zero-result search patterns
     */
    private static function getZeroResultPatterns(PDO $pdo, int $days): array
    {
        try {
            $stmt = $pdo->prepare('
                SELECT 
                    query_normalized,
                    SUM(search_count) as search_count,
                    SUM(zero_result_count) as zero_count
                FROM search_analytics
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                  AND zero_result_count > 0
                GROUP BY query_normalized
                HAVING zero_count >= search_count * 0.8
                   AND search_count >= 3
                ORDER BY search_count DESC
                LIMIT 50
            ');
            $stmt->execute([$days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get session patterns (searches that follow each other)
     */
    private static function getSessionPatterns(PDO $pdo, int $days): array
    {
        try {
            // Find searches within same session that are close in time
            $stmt = $pdo->prepare('
                SELECT 
                    s1.query_normalized as first_query,
                    s2.query_normalized as second_query,
                    COUNT(*) as occurrences,
                    AVG(CASE WHEN s1.results_count = 0 THEN 1 ELSE 0 END) as first_zero_rate
                FROM search_log s1
                INNER JOIN search_log s2 
                    ON s1.session_id = s2.session_id
                    AND s2.created_at > s1.created_at
                    AND s2.created_at <= DATE_ADD(s1.created_at, INTERVAL 2 MINUTE)
                    AND s1.query_normalized != s2.query_normalized
                WHERE s1.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                  AND s1.session_id IS NOT NULL
                  AND LENGTH(s1.query_normalized) >= 2
                  AND LENGTH(s2.query_normalized) >= 2
                GROUP BY s1.query_normalized, s2.query_normalized
                HAVING occurrences >= 3
                ORDER BY occurrences DESC
                LIMIT 30
            ');
            $stmt->execute([$days]);
            $patterns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate confidence based on occurrences and zero rate
            foreach ($patterns as &$pattern) {
                $pattern['confidence'] = min(1.0, 
                    ($pattern['occurrences'] / 10) * (0.5 + $pattern['first_zero_rate'] * 0.5)
                );
            }
            
            return $patterns;
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get vocabulary of successful searches
     */
    private static function getSearchVocabulary(PDO $pdo): array
    {
        try {
            // Get terms from successful searches
            $stmt = $pdo->prepare('
                SELECT DISTINCT query_normalized
                FROM search_analytics
                WHERE avg_results > 0
                  AND search_count >= 2
                LIMIT 1000
            ');
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Suggest a synonym for a zero-result term
     */
    private static function suggestSynonym(PDO $pdo, string $term): ?string
    {
        // Check existing synonyms for close matches
        try {
            $stmt = $pdo->query('SELECT DISTINCT canonical, synonym FROM synonyms WHERE is_active = 1');
            $vocabulary = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $vocabulary[] = $row['canonical'];
                $vocabulary[] = $row['synonym'];
            }
            $vocabulary = array_unique($vocabulary);
            
            return FuzzyMatcher::getSuggestion($term, $vocabulary);
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Auto-create synonyms from discovered patterns
     * 
     * @param PDO $pdo Database connection
     * @param array $suggestions Suggestions from analyzeSearchPatterns()
     * @param float $minConfidence Minimum confidence to auto-create
     * @return array{created: int, skipped: int}
     */
    public static function autoCreateSynonyms(PDO $pdo, array $suggestions, float $minConfidence = 0.7): array
    {
        $created = 0;
        $skipped = 0;
        
        foreach ($suggestions as $suggestion) {
            try {
                // Handle fuzzy match suggestions
                if ($suggestion['type'] === 'fuzzy_match' && !empty($suggestion['matches'])) {
                    $bestMatch = $suggestion['matches'][0];
                    if ($bestMatch['score'] >= $minConfidence) {
                        $stmt = $pdo->prepare('
                            INSERT IGNORE INTO synonyms 
                            (canonical, synonym, weight, source, is_active)
                            VALUES (?, ?, ?, "analytics", 1)
                        ');
                        $stmt->execute([
                            $bestMatch['term'],
                            $suggestion['term'],
                            round($bestMatch['score'], 2),
                        ]);
                        if ($stmt->rowCount() > 0) {
                            $created++;
                        } else {
                            $skipped++;
                        }
                    } else {
                        $skipped++;
                    }
                }
                
                // Handle session pattern suggestions
                elseif ($suggestion['type'] === 'session_pattern' && $suggestion['confidence'] >= $minConfidence) {
                    $stmt = $pdo->prepare('
                        INSERT IGNORE INTO synonyms 
                        (canonical, synonym, weight, source, is_active)
                        VALUES (?, ?, ?, "analytics", 1)
                    ');
                    $stmt->execute([
                        $suggestion['related_term'],
                        $suggestion['term'],
                        round($suggestion['confidence'], 2),
                    ]);
                    if ($stmt->rowCount() > 0) {
                        $created++;
                    } else {
                        $skipped++;
                    }
                }
                
            } catch (PDOException $e) {
                $skipped++;
            }
        }
        
        // Clear synonym cache after creating new ones
        SynonymManager::clearCache();
        
        return ['created' => $created, 'skipped' => $skipped];
    }
}

// ============================================
// SYNONYM SYSTEM
// ============================================

/**
 * Synonym data structure with forward and reverse indexes
 * Cached in memory after first load for performance
 */
class SynonymManager
{
    private static ?array $data = null;
    private static ?PDO $pdo = null;
    
    /**
     * Initialize synonym manager with optional database connection
     */
    public static function init(?PDO $pdo = null): void
    {
        self::$pdo = $pdo;
    }
    
    /**
     * Get synonym data with forward and reverse indexes
     * Uses database synonyms if available, falls back to static file
     * 
     * @return array{forward: array, reverse: array, weights: array}
     */
    public static function getData(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }
        
        // Load from database
        if (self::$pdo !== null) {
            try {
                $dbSynonyms = self::loadFromDatabase();
                if (!empty($dbSynonyms)) {
                    self::$data = self::buildIndexes($dbSynonyms);
                    return self::$data;
                }
            } catch (PDOException $e) {
                error_log('SynonymManager: Database load failed: ' . $e->getMessage());
            }
        }
        
        // Return empty indexes if database is not available
        self::$data = self::buildIndexes([]);
        return self::$data;
    }
    
    /**
     * Load synonyms from database with language and category support
     * 
     * @return array{canonical: string, synonym: string, weight: float, language: string, category_hint: ?string}[]
     */
    private static function loadFromDatabase(): array
    {
        if (self::$pdo === null) {
            return [];
        }
        
        // Check if table exists
        try {
            $stmt = self::$pdo->query("SHOW TABLES LIKE 'synonyms'");
            if ($stmt->rowCount() === 0) {
                return [];
            }
        } catch (PDOException $e) {
            return [];
        }
        
        // Check if new columns exist (for backwards compatibility)
        $hasLanguageColumn = false;
        try {
            $stmt = self::$pdo->query("SHOW COLUMNS FROM synonyms LIKE 'language'");
            $hasLanguageColumn = $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            // Ignore
        }
        
        // Get current locale for filtering synonyms
        $locale = function_exists('getCurrentLocale') ? getCurrentLocale() : 'en';
        
        if ($hasLanguageColumn) {
            // Filter by locale: English locale = only English synonyms, French locale = English + French synonyms
            if ($locale === 'fr') {
                // French locale: include both English and French synonyms
                $stmt = self::$pdo->query('
                    SELECT canonical, synonym, weight, language, category_hint 
                    FROM synonyms 
                    WHERE is_active = 1 
                      AND language IN (\'en\', \'fr\')
                    ORDER BY weight DESC, language ASC
                ');
            } else {
                // English locale (or default): only English synonyms
                $stmt = self::$pdo->query('
                    SELECT canonical, synonym, weight, language, category_hint 
                    FROM synonyms 
                    WHERE is_active = 1 
                      AND language = \'en\'
                    ORDER BY weight DESC
                ');
            }
        } else {
            // Legacy: no language column, assume all are English
            $stmt = self::$pdo->query('
                SELECT canonical, synonym, weight, "en" as language, NULL as category_hint 
                FROM synonyms 
                WHERE is_active = 1 
                ORDER BY weight DESC
            ');
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Build forward and reverse indexes from synonym data
     * Includes language and category metadata for advanced filtering
     * 
     * @param array $synonyms Array of synonym records
     * @return array{forward: array, reverse: array, weights: array, languages: array, categories: array}
     */
    private static function buildIndexes(array $synonyms): array
    {
        $forward = [];    // canonical => [synonyms]
        $reverse = [];    // synonym => canonical
        $weights = [];    // synonym => weight
        $languages = [];  // synonym => language
        $categories = []; // synonym => category_hint
        
        foreach ($synonyms as $row) {
            $canonical = strtolower(trim($row['canonical']));
            $synonym = strtolower(trim($row['synonym']));
            $weight = (float) ($row['weight'] ?? 1.0);
            $language = $row['language'] ?? 'en';
            $categoryHint = $row['category_hint'] ?? null;
            
            // Build forward index
            if (!isset($forward[$canonical])) {
                $forward[$canonical] = [];
            }
            if (!in_array($synonym, $forward[$canonical], true)) {
                $forward[$canonical][] = $synonym;
            }
            
            // Build reverse index (O(1) lookup)
            $reverse[$synonym] = $canonical;
            
            // Store weights
            $weights[$synonym] = $weight;
            
            // Store language info
            $languages[$synonym] = $language;
            
            // Store category hints
            if ($categoryHint !== null) {
                $categories[$synonym] = $categoryHint;
            }
        }
        
        return [
            'forward' => $forward,
            'reverse' => $reverse,
            'weights' => $weights,
            'languages' => $languages,
            'categories' => $categories,
        ];
    }
    
    /**
     * Expand a single term with synonyms
     * 
     * @param string $term Term to expand
     * @param string|null $categoryFilter Optional category to boost relevant synonyms
     * @return array{terms: string[], weights: array<string, float>}
     */
    public static function expand(string $term, ?string $categoryFilter = null): array
    {
        $data = self::getData();
        $term = strtolower(trim($term));
        
        if (strlen($term) < 2) {
            return ['terms' => [$term], 'weights' => [$term => 1.0]];
        }
        
        $expanded = [$term];
        $weights = [$term => 1.0]; // Original term has highest weight
        
        // Forward lookup: term is a canonical term
        if (isset($data['forward'][$term])) {
            foreach ($data['forward'][$term] as $syn) {
                if (!in_array($syn, $expanded, true)) {
                    $expanded[] = $syn;
                    $baseWeight = $data['weights'][$syn] ?? 0.9;
                    // Boost weight if synonym matches category filter
                    if ($categoryFilter !== null && isset($data['categories'][$syn])) {
                        if (stripos($data['categories'][$syn], $categoryFilter) !== false) {
                            $baseWeight = min(1.0, $baseWeight * 1.15);
                        }
                    }
                    $weights[$syn] = $baseWeight;
                }
            }
        }
        
        // Reverse lookup (O(1)): term is a synonym of something
        if (isset($data['reverse'][$term])) {
            $canonical = $data['reverse'][$term];
            if (!in_array($canonical, $expanded, true)) {
                $expanded[] = $canonical;
                $weights[$canonical] = 0.95; // Canonical terms are high relevance
                
                // Also get other synonyms of this canonical term
                if (isset($data['forward'][$canonical])) {
                    foreach ($data['forward'][$canonical] as $sibling) {
                        if (!in_array($sibling, $expanded, true)) {
                            $expanded[] = $sibling;
                            $baseWeight = $data['weights'][$sibling] ?? 0.8;
                            // Boost weight if synonym matches category filter
                            if ($categoryFilter !== null && isset($data['categories'][$sibling])) {
                                if (stripos($data['categories'][$sibling], $categoryFilter) !== false) {
                                    $baseWeight = min(1.0, $baseWeight * 1.15);
                                }
                            }
                            $weights[$sibling] = $baseWeight;
                        }
                    }
                }
            }
        }
        
        // Apply stemming and add stemmed variants
        $stemmed = self::stem($term);
        if ($stemmed !== $term && !in_array($stemmed, $expanded, true)) {
            $expanded[] = $stemmed;
            $weights[$stemmed] = 0.85;
            
            // Also check synonyms for stemmed term
            if (isset($data['forward'][$stemmed])) {
                foreach ($data['forward'][$stemmed] as $syn) {
                    if (!in_array($syn, $expanded, true)) {
                        $expanded[] = $syn;
                        $weights[$syn] = ($data['weights'][$syn] ?? 0.8) * 0.9;
                    }
                }
            }
        }
        
        return ['terms' => $expanded, 'weights' => $weights];
    }
    
    /**
     * Get synonyms filtered by language
     * 
     * @param string $language Language code (en, fr)
     * @return array Synonyms for the specified language
     */
    public static function getSynonymsByLanguage(string $language): array
    {
        $data = self::getData();
        $result = [];
        
        foreach ($data['languages'] ?? [] as $synonym => $lang) {
            if ($lang === $language) {
                $canonical = $data['reverse'][$synonym] ?? null;
                if ($canonical !== null) {
                    if (!isset($result[$canonical])) {
                        $result[$canonical] = [];
                    }
                    $result[$canonical][] = [
                        'synonym' => $synonym,
                        'weight' => $data['weights'][$synonym] ?? 1.0,
                    ];
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Get synonyms that have a specific category hint
     * 
     * @param string $category Category to filter by
     * @return array Synonyms for the specified category
     */
    public static function getSynonymsByCategory(string $category): array
    {
        $data = self::getData();
        $result = [];
        
        foreach ($data['categories'] ?? [] as $synonym => $catHint) {
            if (stripos($catHint, $category) !== false) {
                $canonical = $data['reverse'][$synonym] ?? null;
                if ($canonical !== null) {
                    if (!isset($result[$canonical])) {
                        $result[$canonical] = [];
                    }
                    $result[$canonical][] = [
                        'synonym' => $synonym,
                        'weight' => $data['weights'][$synonym] ?? 1.0,
                        'category' => $catHint,
                    ];
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Increment usage count for a synonym in database
     * Also updates the last_used_at timestamp
     */
    public static function recordUsage(string $synonym): void
    {
        if (self::$pdo === null) {
            return;
        }
        
        try {
            // Check if last_used_at column exists
            static $hasLastUsedColumn = null;
            if ($hasLastUsedColumn === null) {
                try {
                    $stmt = self::$pdo->query("SHOW COLUMNS FROM synonyms LIKE 'last_used_at'");
                    $hasLastUsedColumn = $stmt->rowCount() > 0;
                } catch (PDOException $e) {
                    $hasLastUsedColumn = false;
                }
            }
            
            if ($hasLastUsedColumn) {
                $stmt = self::$pdo->prepare('
                    UPDATE synonyms 
                    SET usage_count = usage_count + 1, last_used_at = NOW()
                    WHERE synonym = ? AND is_active = 1
                ');
            } else {
                $stmt = self::$pdo->prepare('
                    UPDATE synonyms 
                    SET usage_count = usage_count + 1 
                    WHERE synonym = ? AND is_active = 1
                ');
            }
            $stmt->execute([strtolower(trim($synonym))]);
        } catch (PDOException $e) {
            // Non-critical, log and continue
            error_log('SynonymManager: Failed to record usage: ' . $e->getMessage());
        }
    }
    
    /**
     * Basic English stemmer for common suffixes
     * Handles plurals and common verb forms
     */
    public static function stem(string $word): string
    {
        $word = strtolower(trim($word));
        
        // Too short to stem
        if (strlen($word) <= 3) {
            return $word;
        }
        
        // Handle common irregular plurals
        $irregulars = [
            'shelves' => 'shelf',
            'knives' => 'knife',
            'wives' => 'wife',
            'lives' => 'life',
            'leaves' => 'leaf',
            'wolves' => 'wolf',
            'halves' => 'half',
            'calves' => 'calf',
            'selves' => 'self',
            'loaves' => 'loaf',
            'thieves' => 'thief',
            'children' => 'child',
            'men' => 'man',
            'women' => 'woman',
            'feet' => 'foot',
            'teeth' => 'tooth',
            'mice' => 'mouse',
        ];
        
        if (isset($irregulars[$word])) {
            return $irregulars[$word];
        }
        
        // Handle -ies → -y (babies → baby)
        if (str_ends_with($word, 'ies') && strlen($word) > 4) {
            return substr($word, 0, -3) . 'y';
        }
        
        // Handle -ves → -f (shelves → shelf)
        if (str_ends_with($word, 'ves') && strlen($word) > 4) {
            $base = substr($word, 0, -3);
            // Check if -f or -fe form exists
            return $base . 'f';
        }
        
        // Handle -es endings (boxes → box, watches → watch, buses → bus)
        if (str_ends_with($word, 'es') && strlen($word) > 3) {
            $base = substr($word, 0, -2);
            $lastChar = substr($base, -1);
            $last2Chars = substr($base, -2);
            
            // -xes, -ches, -shes, -sses → remove -es
            if (in_array($lastChar, ['x', 's']) || in_array($last2Chars, ['ch', 'sh', 'ss'])) {
                return $base;
            }
            
            // -oes → -o (potatoes → potato) - but not all words
            if (str_ends_with($base, 'o')) {
                return $base;
            }
        }
        
        // Handle -s endings (cats → cat)
        if (str_ends_with($word, 's') && !str_ends_with($word, 'ss') && strlen($word) > 3) {
            return substr($word, 0, -1);
        }
        
        // Handle -ing endings (running → run)
        if (str_ends_with($word, 'ing') && strlen($word) > 5) {
            $base = substr($word, 0, -3);
            // Handle doubled consonants (running → run)
            if (strlen($base) >= 2) {
                $lastChar = substr($base, -1);
                $secondLast = substr($base, -2, 1);
                if ($lastChar === $secondLast && preg_match('/[bcdfgklmnprstvz]/', $lastChar)) {
                    return substr($base, 0, -1);
                }
            }
            return $base;
        }
        
        // Handle -ed endings (walked → walk)
        if (str_ends_with($word, 'ed') && strlen($word) > 4) {
            // -ied → -y
            if (str_ends_with($word, 'ied')) {
                return substr($word, 0, -3) . 'y';
            }
            return substr($word, 0, -2);
        }
        
        return $word;
    }
    
    /**
     * Clear cached data (useful for admin operations)
     */
    public static function clearCache(): void
    {
        self::$data = null;
    }
}

// ============================================
// QUERY TOKENIZATION
// ============================================

/**
 * Tokenize and expand a search query with full feature support
 * 
 * Features:
 * - Multi-word query splitting and expansion
 * - Multi-language support (French translation)
 * - Fuzzy matching for typo tolerance
 * - Synonym expansion
 * 
 * @param string $query Raw search query
 * @param int $maxTerms Maximum number of terms to return (prevents query explosion)
 * @param bool $useFuzzy Enable fuzzy matching for typos
 * @param bool $useLanguageTranslation Enable French→English translation
 * @return array{terms: string[], weights: array<string, float>, original: string, language?: string, fuzzy_suggestions?: array}
 */
function tokenizeAndExpandQuery(
    string $query, 
    int $maxTerms = 20,
    bool $useFuzzy = true,
    bool $useLanguageTranslation = true
): array {
    $query = strtolower(trim($query));
    
    if (strlen($query) < 2) {
        return ['terms' => [$query], 'weights' => [$query => 1.0], 'original' => $query];
    }
    
    $result = [
        'terms' => [],
        'weights' => [],
        'original' => $query,
    ];
    
    // Language detection and translation
    $translatedQuery = $query;
    if ($useLanguageTranslation) {
        $langResult = LanguageSupport::translateQuery($query);
        if ($langResult['original_lang'] === LanguageSupport::LANG_FR) {
            $translatedQuery = $langResult['translated'];
            $result['language'] = 'fr';
            $result['translated'] = $translatedQuery;
            
            // Add all French terms with moderate weight
            foreach ($langResult['terms'] as $term) {
                if (!isset($result['weights'][$term])) {
                    $result['terms'][] = $term;
                    $result['weights'][$term] = 0.9;
                }
            }
        }
    }
    
    // Split query into words
    $words = preg_split('/\s+/', $translatedQuery, -1, PREG_SPLIT_NO_EMPTY);
    
    // If single word, expand it directly
    if (count($words) === 1) {
        $expanded = SynonymManager::expand($words[0]);
        foreach ($expanded['terms'] as $term) {
            if (!isset($result['weights'][$term])) {
                $result['terms'][] = $term;
                $result['weights'][$term] = $expanded['weights'][$term] ?? 0.8;
            }
        }
        
        // Note: Fuzzy matching removed from automatic expansion
        // It should only be used as a "did you mean" suggestion when zero results are found
        
        $result['terms'] = array_slice($result['terms'], 0, $maxTerms);
        return $result;
    }
    
    // Multi-word query: expand each word
    foreach ($words as $word) {
        if (strlen($word) < 2) {
            continue;
        }
        
        // Add original word with high weight
        if (!isset($allWeights[$word])) {
            $allTerms[] = $word;
            $allWeights[$word] = 1.0;
        }
        
        // Expand this word with synonyms
        $expanded = SynonymManager::expand($word);
        foreach ($expanded['terms'] as $term) {
            if (!isset($allWeights[$term])) {
                $allTerms[] = $term;
                // Reduce weight for expanded terms in multi-word queries
                $allWeights[$term] = ($expanded['weights'][$term] ?? 0.8) * 0.9;
            }
        }
        
        // Note: Fuzzy matching removed from automatic expansion
        // It should only be used as a "did you mean" suggestion when zero results are found
    }
    
    // Also add the full query as a phrase (highest priority for exact matches)
    if (!isset($allWeights[$query]) && strlen($query) >= 3) {
        array_unshift($allTerms, $query);
        $allWeights[$query] = 1.0;
    }
    
    // Also add the translated query as a phrase
    if ($translatedQuery !== $query && !isset($allWeights[$translatedQuery])) {
        $allTerms[] = $translatedQuery;
        $allWeights[$translatedQuery] = 0.95;
    }
    
    // Merge with existing result
    foreach ($allTerms as $term) {
        if (!isset($result['weights'][$term])) {
            $result['terms'][] = $term;
            $result['weights'][$term] = $allWeights[$term];
        }
    }
    
    $result['terms'] = array_slice($result['terms'], 0, $maxTerms);
    return $result;
}

/**
 * Get fuzzy matches for a search term using the synonym vocabulary
 * 
 * @param string $term Search term
 * @return array Fuzzy match results
 */
function getFuzzyMatchesForTerm(string $term): array
{
    static $vocabulary = null;
    
    // Build vocabulary from synonyms on first call
    if ($vocabulary === null) {
        $synonymData = SynonymManager::getData();
        $vocabulary = array_unique(array_merge(
            array_keys($synonymData['forward'] ?? []),
            array_keys($synonymData['reverse'] ?? [])
        ));
    }
    
    return FuzzyMatcher::findMatches($term, $vocabulary, 2);
}

// ============================================
// SEARCH ANALYTICS
// ============================================

/**
 * Log a search query for analytics
 * 
 * @param PDO $pdo Database connection
 * @param string $query Original search query
 * @param int $resultsCount Number of results returned
 * @param array $expandedTerms Terms used in search
 * @param int|null $executionTimeMs Query execution time
 * @param int|null $userId User ID if logged in
 */
function logSearch(
    PDO $pdo,
    string $query,
    int $resultsCount,
    array $expandedTerms = [],
    ?int $executionTimeMs = null,
    ?int $userId = null
): void {
    // Check if search logging is enabled (can be disabled via settings)
    static $loggingEnabled = null;
    if ($loggingEnabled === null) {
        $loggingEnabled = function_exists('getSetting') 
            ? (bool) getSetting('features.search_logging', true)
            : true;
    }
    
    if (!$loggingEnabled) {
        return;
    }
    
    // Check if table exists
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'search_log'");
        if ($stmt->rowCount() === 0) {
            return;
        }
    } catch (PDOException $e) {
        return;
    }
    
    try {
        // Generate session-based identifier (privacy-preserving)
        $sessionId = session_id() ?: null;
        
        // Hash IP for rate limiting (not storing raw IP)
        $ipHash = isset($_SERVER['REMOTE_ADDR']) 
            ? hash('sha256', $_SERVER['REMOTE_ADDR'] . date('Y-m-d'))
            : null;
        
        $stmt = $pdo->prepare('
            INSERT INTO search_log 
            (query, query_normalized, results_count, expanded_terms, execution_time_ms, user_id, session_id, ip_hash)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        
        $stmt->execute([
            substr($query, 0, 255),
            substr(strtolower(trim($query)), 0, 255),
            $resultsCount,
            !empty($expandedTerms) ? json_encode(array_slice($expandedTerms, 0, 20)) : null,
            $executionTimeMs,
            $userId,
            $sessionId,
            $ipHash,
        ]);
        
        // Update daily aggregates (async-friendly, non-blocking)
        updateSearchAnalytics($pdo, strtolower(trim($query)), $resultsCount);
        
    } catch (PDOException $e) {
        // Non-critical, log and continue
        error_log('Search logging failed: ' . $e->getMessage());
    }
}

/**
 * Update search analytics aggregates
 */
function updateSearchAnalytics(PDO $pdo, string $queryNormalized, int $resultsCount): void
{
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'search_analytics'");
        if ($stmt->rowCount() === 0) {
            return;
        }
        
        $stmt = $pdo->prepare('
            INSERT INTO search_analytics 
            (date, query_normalized, search_count, total_results, avg_results, zero_result_count)
            VALUES (CURDATE(), ?, 1, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                search_count = search_count + 1,
                total_results = total_results + VALUES(total_results),
                avg_results = (total_results + VALUES(total_results)) / (search_count + 1),
                zero_result_count = zero_result_count + VALUES(zero_result_count)
        ');
        
        $stmt->execute([
            substr($queryNormalized, 0, 255),
            $resultsCount,
            (float) $resultsCount,
            $resultsCount === 0 ? 1 : 0,
        ]);
    } catch (PDOException $e) {
        // Non-critical
    }
}

/**
 * Get popular searches with zero results (for synonym suggestions)
 * 
 * @param PDO $pdo Database connection
 * @param int $days Number of days to look back
 * @param int $limit Maximum results
 * @return array
 */
function getZeroResultSearches(PDO $pdo, int $days = 7, int $limit = 20): array
{
    try {
        $stmt = $pdo->prepare('
            SELECT 
                query_normalized,
                SUM(zero_result_count) as zero_searches,
                SUM(search_count) as total_searches
            FROM search_analytics
            WHERE date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
              AND zero_result_count > 0
            GROUP BY query_normalized
            HAVING zero_searches >= 3
            ORDER BY zero_searches DESC
            LIMIT ?
        ');
        $stmt->execute([$days, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get popular searches for analytics dashboard
 * 
 * @param PDO $pdo Database connection
 * @param int $days Number of days to look back
 * @param int $limit Maximum results
 * @return array
 */
function getPopularSearches(PDO $pdo, int $days = 7, int $limit = 20): array
{
    try {
        $stmt = $pdo->prepare('
            SELECT 
                query_normalized,
                SUM(search_count) as total_searches,
                ROUND(AVG(avg_results), 1) as avg_results
            FROM search_analytics
            WHERE date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            GROUP BY query_normalized
            ORDER BY total_searches DESC
            LIMIT ?
        ');
        $stmt->execute([$days, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// ============================================
// ENHANCED SEARCH FUNCTION
// ============================================

/**
 * Enhanced furniture search with FULLTEXT, synonyms, and analytics
 * 
 * @param PDO $pdo Database connection
 * @param string $query Search query
 * @param int $page Page number
 * @param int $perPage Items per page
 * @param int|null $userFavoritesId Filter to user's favorites only
 * @param bool $expandSynonyms Whether to expand with synonyms
 * @param bool $logSearch Whether to log this search
 * @return array
 */
function searchFurnitureEnhanced(
    PDO $pdo,
    string $query,
    int $page = 1,
    int $perPage = 50,
    ?int $userFavoritesId = null,
    bool $expandSynonyms = true,
    bool $logSearchQuery = true,
    ?string $categoryFilter = null  // Category-aware search
): array {
    $startTime = microtime(true);
    
    // Initialize synonym manager with database connection
    SynonymManager::init($pdo);
    
    // Validate inputs
    $minSearchLength = defined('MIN_SEARCH_LENGTH') ? MIN_SEARCH_LENGTH : 2;
    if (strlen(trim($query)) < $minSearchLength) {
        return [
            'items' => [],
            'pagination' => createPagination(0, $page, $perPage),
        ];
    }
    
    $maxPerPage = function_exists('getMaxItemsPerPage') ? getMaxItemsPerPage() : 100;
    $perPage = min(max(1, $perPage), $maxPerPage);
    $page = max(1, $page);
    $offset = ($page - 1) * $perPage;
    
    // Tokenize and expand query with all features
    // Note: useFuzzy is false - fuzzy matching only used for zero-result suggestions
    $originalQuery = trim($query);
    $expanded = $expandSynonyms 
        ? tokenizeAndExpandQuery($query, 20, false, true) 
        : ['terms' => [$query], 'weights' => [$query => 1.0], 'original' => $query];
    
    $searchTerms = $expanded['terms'];
    $wasExpanded = count($searchTerms) > 1;
    
    // Build search conditions
    $favoritesJoin = '';
    $favoritesParams = [];
    if ($userFavoritesId !== null) {
        $favoritesJoin = 'INNER JOIN favorites fav ON f.id = fav.furniture_id AND fav.user_id = ?';
        $favoritesParams[] = $userFavoritesId;
    }
    
    // Check if FULLTEXT index exists
    $useFulltext = checkFulltextIndex($pdo);
    
    if ($useFulltext) {
        // Build FULLTEXT query
        $result = searchWithFulltext($pdo, $searchTerms, $originalQuery, $favoritesJoin, $favoritesParams, $perPage, $offset);
    } else {
        // Fall back to LIKE-based search
        $result = searchWithLike($pdo, $searchTerms, $originalQuery, $favoritesJoin, $favoritesParams, $perPage, $offset);
    }
    
    // Apply category-aware relevance boosting
    if ($categoryFilter !== null && !empty($result['items'])) {
        $result['items'] = CategoryAwareSearch::enhanceResults($result['items'], $categoryFilter, $pdo);
    }
    
    // Calculate execution time
    $executionTimeMs = (int) ((microtime(true) - $startTime) * 1000);
    
    // Log search query
    if ($logSearchQuery) {
        $userId = function_exists('getCurrentUserId') ? getCurrentUserId() : null;
        logSearch($pdo, $originalQuery, $result['total'], $searchTerms, $executionTimeMs, $userId);
    }
    
    // Build response
    $response = [
        'items' => $result['items'],
        'pagination' => createPagination($result['total'], $page, $perPage),
    ];
    
    // Add search metadata
    $searchMeta = [];
    
    // Handle French translation: User typed French -> Translated to English -> Search in English DB
    if (isset($expanded['language']) && $expanded['language'] === 'fr' && isset($expanded['translated'])) {
        $searchMeta['language'] = 'fr';
        $searchMeta['original_query'] = $originalQuery; // French user input
        $searchMeta['translated_query'] = $expanded['translated']; // English translation
        $searchMeta['search_type'] = $useFulltext ? 'fulltext' : 'like';
        $searchMeta['execution_time_ms'] = $executionTimeMs;
        
        // Show synonyms that were used (but exclude the translated query itself from "also searching")
        $synonymsUsed = array_slice(array_diff($searchTerms, [$expanded['translated'], $originalQuery]), 0, 5);
        if (!empty($synonymsUsed)) {
            $searchMeta['synonyms_used'] = $synonymsUsed;
        }
    }
    // Handle synonym expansion for English queries
    elseif ($wasExpanded) {
        $searchMeta['original'] = $originalQuery;
        // Only show actual synonym expansions, not the original query variants
        $actualSynonyms = array_filter($searchTerms, function($term) use ($originalQuery) {
            return $term !== $originalQuery && strtolower($term) !== strtolower($originalQuery);
        });
        $searchMeta['synonyms_used'] = array_slice(array_values($actualSynonyms), 0, 5);
        $searchMeta['search_type'] = $useFulltext ? 'fulltext' : 'like';
        $searchMeta['execution_time_ms'] = $executionTimeMs;
    }
    
    // Only suggest typo corrections when zero results are found
    if ($result['total'] === 0) {
        // Try fuzzy matching as a "did you mean" suggestion
        $words = preg_split('/\s+/', strtolower(trim($originalQuery)), -1, PREG_SPLIT_NO_EMPTY);
        $fuzzySuggestions = [];
        
        if (count($words) === 1 && strlen($words[0]) >= 3) {
            // Single word query - check for typo
            $synonymData = SynonymManager::getData();
            $vocabulary = array_unique(array_merge(
                array_keys($synonymData['forward'] ?? []),
                array_keys($synonymData['reverse'] ?? [])
            ));
            
            $suggestion = FuzzyMatcher::getSuggestion($words[0], $vocabulary);
            if ($suggestion !== null) {
                $fuzzySuggestions[$words[0]] = $suggestion;
            }
        }
        
        if (!empty($fuzzySuggestions)) {
            $searchMeta['did_you_mean'] = $fuzzySuggestions;
        }
        
        // Suggest category if zero results and no category filter
        if ($categoryFilter === null) {
            $suggestedCategory = CategoryAwareSearch::suggestCategory($originalQuery, $pdo);
            if ($suggestedCategory !== null) {
                $searchMeta['suggested_category'] = $suggestedCategory;
            }
        }
    }
    
    if (!empty($searchMeta)) {
        $response['search_meta'] = $searchMeta;
    }
    
    return $response;
}

/**
 * Check if FULLTEXT index exists on furniture table
 */
function checkFulltextIndex(PDO $pdo): bool
{
    static $hasFulltext = null;
    
    if ($hasFulltext !== null) {
        return $hasFulltext;
    }
    
    try {
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM information_schema.statistics 
            WHERE table_schema = DATABASE() 
            AND table_name = 'furniture' 
            AND index_type = 'FULLTEXT'
        ");
        $hasFulltext = (int) $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        $hasFulltext = false;
    }
    
    return $hasFulltext;
}

/**
 * Search using FULLTEXT index (faster, better relevance)
 */
function searchWithFulltext(
    PDO $pdo,
    array $searchTerms,
    string $originalQuery,
    string $favoritesJoin,
    array $favoritesParams,
    int $perPage,
    int $offset
): array {
    // Build FULLTEXT boolean query
    // Format: +term* for required prefix match
    $fulltextTerms = [];
    foreach ($searchTerms as $term) {
        // Escape special FULLTEXT characters
        $escapedTerm = preg_replace('/[+\-><()~*"@]+/', ' ', $term);
        $escapedTerm = trim($escapedTerm);
        if (strlen($escapedTerm) >= 2) {
            $fulltextTerms[] = $escapedTerm . '*';
        }
    }
    
    $fulltextQuery = implode(' ', $fulltextTerms);
    $likeQuery = '%' . $originalQuery . '%';
    
    // Count query
    $countSql = "
        SELECT COUNT(DISTINCT f.id)
        FROM furniture f
        {$favoritesJoin}
        LEFT JOIN furniture_categories fc_search ON f.id = fc_search.furniture_id
        LEFT JOIN categories c_search ON fc_search.category_id = c_search.id
        LEFT JOIN furniture_tags ft ON f.id = ft.furniture_id
        LEFT JOIN tags t ON ft.tag_id = t.id
        WHERE MATCH(f.name) AGAINST(? IN BOOLEAN MODE)
           OR c_search.name LIKE ?
           OR t.name LIKE ?
    ";
    
    $countParams = array_merge($favoritesParams, [$fulltextQuery, $likeQuery, $likeQuery]);
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($countParams);
    $total = (int) $stmt->fetchColumn();
    
    // Main query with relevance scoring
    $primaryLike = '%' . $originalQuery . '%';
    
    $sql = "
        SELECT DISTINCT f.id, f.name, f.price, f.image_url, f.created_at,
               CASE 
                   WHEN f.name LIKE ? THEN 1
                   WHEN MATCH(f.name) AGAINST(? IN BOOLEAN MODE) THEN 2
                   WHEN EXISTS (SELECT 1 FROM furniture_categories fc2 
                                INNER JOIN categories c2 ON fc2.category_id = c2.id 
                                WHERE fc2.furniture_id = f.id AND c2.name LIKE ?) THEN 3
                   ELSE 4
               END as relevance,
               MATCH(f.name) AGAINST(? IN BOOLEAN MODE) as ft_score
        FROM furniture f
        {$favoritesJoin}
        LEFT JOIN furniture_categories fc_search ON f.id = fc_search.furniture_id
        LEFT JOIN categories c_search ON fc_search.category_id = c_search.id
        LEFT JOIN furniture_tags ft ON f.id = ft.furniture_id
        LEFT JOIN tags t ON ft.tag_id = t.id
        WHERE MATCH(f.name) AGAINST(? IN BOOLEAN MODE)
           OR c_search.name LIKE ?
           OR t.name LIKE ?
        ORDER BY relevance ASC, ft_score DESC, f.name ASC
        LIMIT ? OFFSET ?
    ";
    
    $params = array_merge(
        [$primaryLike, $fulltextQuery, $primaryLike, $fulltextQuery],
        $favoritesParams,
        [$fulltextQuery, $likeQuery, $likeQuery],
        [$perPage, $offset]
    );
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Clean up internal fields
    foreach ($items as &$item) {
        unset($item['relevance'], $item['ft_score']);
    }
    
    // Attach categories and tags
    if (function_exists('attachCategoriesToFurniture')) {
        $items = attachCategoriesToFurniture($pdo, $items);
    }
    if (function_exists('attachTagsToFurniture')) {
        $items = attachTagsToFurniture($pdo, $items);
    }
    
    return ['items' => $items, 'total' => $total];
}

/**
 * Search using LIKE (fallback when FULLTEXT not available)
 */
function searchWithLike(
    PDO $pdo,
    array $searchTerms,
    string $originalQuery,
    string $favoritesJoin,
    array $favoritesParams,
    int $perPage,
    int $offset
): array {
    // Build OR conditions for all terms
    $searchConditions = [];
    $searchParams = [];
    
    foreach ($searchTerms as $term) {
        $termLike = '%' . $term . '%';
        $searchConditions[] = "(f.name LIKE ? OR c_search.name LIKE ? OR t.name LIKE ?)";
        $searchParams = array_merge($searchParams, [$termLike, $termLike, $termLike]);
    }
    
    $searchWhere = '(' . implode(' OR ', $searchConditions) . ')';
    
    // Count query
    $countSql = "
        SELECT COUNT(DISTINCT f.id) 
        FROM furniture f
        {$favoritesJoin}
        LEFT JOIN furniture_categories fc_search ON f.id = fc_search.furniture_id
        LEFT JOIN categories c_search ON fc_search.category_id = c_search.id
        LEFT JOIN furniture_tags ft ON f.id = ft.furniture_id
        LEFT JOIN tags t ON ft.tag_id = t.id
        WHERE {$searchWhere}
    ";
    
    $countParams = array_merge($favoritesParams, $searchParams);
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($countParams);
    $total = (int) $stmt->fetchColumn();
    
    // Main query with relevance scoring
    $primaryLike = '%' . $originalQuery . '%';
    
    $sql = "
        SELECT DISTINCT f.id, f.name, f.price, f.image_url, f.created_at,
               CASE 
                   WHEN f.name LIKE ? THEN 1
                   WHEN EXISTS (SELECT 1 FROM furniture_categories fc2 
                                INNER JOIN categories c2 ON fc2.category_id = c2.id 
                                WHERE fc2.furniture_id = f.id AND c2.name LIKE ?) THEN 2
                   ELSE 3
               END as relevance
        FROM furniture f
        {$favoritesJoin}
        LEFT JOIN furniture_categories fc_search ON f.id = fc_search.furniture_id
        LEFT JOIN categories c_search ON fc_search.category_id = c_search.id
        LEFT JOIN furniture_tags ft ON f.id = ft.furniture_id
        LEFT JOIN tags t ON ft.tag_id = t.id
        WHERE {$searchWhere}
        ORDER BY relevance ASC, f.name ASC
        LIMIT ? OFFSET ?
    ";
    
    $params = array_merge(
        [$primaryLike, $primaryLike],
        $favoritesParams,
        $searchParams,
        [$perPage, $offset]
    );
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Clean up internal fields
    foreach ($items as &$item) {
        unset($item['relevance']);
    }
    
    // Attach categories and tags
    if (function_exists('attachCategoriesToFurniture')) {
        $items = attachCategoriesToFurniture($pdo, $items);
    }
    if (function_exists('attachTagsToFurniture')) {
        $items = attachTagsToFurniture($pdo, $items);
    }
    
    return ['items' => $items, 'total' => $total];
}

// ============================================
// SYNONYM MANAGEMENT (Admin)
// ============================================

/**
 * Get all synonyms for admin management
 */
function getSynonymsList(PDO $pdo, int $page = 1, int $perPage = 50, ?string $search = null): array
{
    $offset = ($page - 1) * $perPage;
    
    $where = '';
    $params = [];
    
    if ($search !== null && $search !== '') {
        $where = 'WHERE canonical LIKE ? OR synonym LIKE ?';
        $params = ["%{$search}%", "%{$search}%"];
    }
    
    // Count
    $countSql = "SELECT COUNT(*) FROM synonyms {$where}";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();
    
    // Get items
    $sql = "
        SELECT id, canonical, synonym, weight, is_active, source, usage_count, created_at, updated_at
        FROM synonyms
        {$where}
        ORDER BY canonical ASC, weight DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $perPage;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'items' => $items,
        'pagination' => createPagination($total, $page, $perPage),
    ];
}

/**
 * Get synonym by ID
 */
function getSynonymById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM synonyms WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Create a new synonym with support for language and category
 */
function createSynonym(PDO $pdo, array $data): int
{
    // Check if new columns exist
    $hasLanguageColumn = false;
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM synonyms LIKE 'language'");
        $hasLanguageColumn = $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        $hasLanguageColumn = false;
    }
    
    if ($hasLanguageColumn) {
        $stmt = $pdo->prepare('
            INSERT INTO synonyms (canonical, synonym, weight, is_active, source, language, category_hint)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        
        $stmt->execute([
            strtolower(trim($data['canonical'])),
            strtolower(trim($data['synonym'])),
            (float) ($data['weight'] ?? 1.0),
            (int) ($data['is_active'] ?? 1),
            $data['source'] ?? 'admin',
            $data['language'] ?? 'en',
            $data['category_hint'] ?? null,
        ]);
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO synonyms (canonical, synonym, weight, is_active, source)
            VALUES (?, ?, ?, ?, ?)
        ');
        
        $stmt->execute([
            strtolower(trim($data['canonical'])),
            strtolower(trim($data['synonym'])),
            (float) ($data['weight'] ?? 1.0),
            (int) ($data['is_active'] ?? 1),
            $data['source'] ?? 'admin',
        ]);
    }
    
    // Clear cache so new synonym is picked up
    SynonymManager::clearCache();
    
    return (int) $pdo->lastInsertId();
}

/**
 * Update a synonym with support for language and category
 */
function updateSynonym(PDO $pdo, int $id, array $data): bool
{
    $fields = [];
    $params = [];
    
    if (isset($data['canonical'])) {
        $fields[] = 'canonical = ?';
        $params[] = strtolower(trim($data['canonical']));
    }
    if (isset($data['synonym'])) {
        $fields[] = 'synonym = ?';
        $params[] = strtolower(trim($data['synonym']));
    }
    if (isset($data['weight'])) {
        $fields[] = 'weight = ?';
        $params[] = (float) $data['weight'];
    }
    if (isset($data['is_active'])) {
        $fields[] = 'is_active = ?';
        $params[] = (int) $data['is_active'];
    }
    if (isset($data['language'])) {
        $fields[] = 'language = ?';
        $params[] = $data['language'];
    }
    if (array_key_exists('category_hint', $data)) {
        $fields[] = 'category_hint = ?';
        $params[] = $data['category_hint'];
    }
    
    if (empty($fields)) {
        return false;
    }
    
    $params[] = $id;
    $sql = 'UPDATE synonyms SET ' . implode(', ', $fields) . ' WHERE id = ?';
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($params);
    
    // Clear cache
    SynonymManager::clearCache();
    
    return $result;
}

/**
 * Delete a synonym
 */
function deleteSynonym(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare('DELETE FROM synonyms WHERE id = ?');
    $result = $stmt->execute([$id]);
    
    // Clear cache
    SynonymManager::clearCache();
    
    return $result;
}


