<?php
/**
 * GTAW Furniture Catalog - Synonym Map
 * 
 * Maps common search terms to their synonyms for improved search discovery.
 * Each key maps to an array of related terms that users might search for.
 * 
 * Format: 'canonical_term' => ['synonym1', 'synonym2', ...]
 * 
 * The search system performs:
 * - Direct lookup: If user searches "couch", also search "sofa", "settee", etc.
 * - Reverse lookup: If user searches "settee", also search "sofa" (the canonical)
 * 
 * Last updated: December 2025
 */

return [
    // =========================================
    // SEATING
    // =========================================
    'sofa' => ['couch', 'settee', 'loveseat', 'divan', 'davenport', 'sectional'],
    'couch' => ['sofa', 'settee', 'loveseat', 'sectional'],
    'chair' => ['seat', 'armchair', 'recliner', 'lounger'],
    'armchair' => ['chair', 'recliner', 'lounge chair'],
    'recliner' => ['armchair', 'lounger', 'lazy boy'],
    'stool' => ['barstool', 'bar stool', 'footstool', 'ottoman'],
    'bench' => ['seat', 'pew', 'settee'],
    'ottoman' => ['footstool', 'pouf', 'hassock'],
    'beanbag' => ['bean bag', 'floor cushion'],
    'rocker' => ['rocking chair', 'glider'],
    'swing' => ['porch swing', 'hanging chair'],
    
    // =========================================
    // TABLES
    // =========================================
    'table' => ['desk', 'counter', 'surface'],
    'desk' => ['table', 'workstation', 'bureau', 'writing desk'],
    'counter' => ['countertop', 'bar', 'worktop', 'bartop'],
    'nightstand' => ['bedside table', 'night table', 'end table', 'bedside'],
    'coffee table' => ['cocktail table', 'center table'],
    'dining table' => ['dinner table', 'kitchen table'],
    'end table' => ['side table', 'accent table', 'nightstand'],
    'console' => ['console table', 'hallway table', 'entry table'],
    'workbench' => ['work table', 'craft table', 'workshop table'],
    'picnic table' => ['outdoor table', 'patio table'],
    
    // =========================================
    // STORAGE & CABINETS
    // =========================================
    'cabinet' => ['cupboard', 'closet', 'wardrobe', 'armoire', 'storage'],
    'cupboard' => ['cabinet', 'closet', 'pantry'],
    'wardrobe' => ['closet', 'armoire', 'cabinet', 'clothes cabinet'],
    'dresser' => ['chest', 'bureau', 'drawers', 'chest of drawers'],
    'shelf' => ['shelving', 'bookshelf', 'rack', 'ledge'],
    'bookcase' => ['bookshelf', 'shelf', 'shelving', 'book rack'],
    'drawer' => ['drawers', 'chest', 'filing'],
    'locker' => ['cabinet', 'storage', 'compartment'],
    'crate' => ['box', 'container', 'storage'],
    'trunk' => ['chest', 'storage box', 'footlocker'],
    'safe' => ['vault', 'strongbox', 'lockbox'],
    'filing cabinet' => ['file cabinet', 'drawer', 'office storage'],
    'pantry' => ['cupboard', 'larder', 'food storage'],
    'hutch' => ['buffet', 'sideboard', 'china cabinet'],
    'sideboard' => ['buffet', 'credenza', 'hutch'],
    
    // =========================================
    // BEDS & BEDROOM
    // =========================================
    'bed' => ['mattress', 'bunk', 'cot', 'sleeping'],
    'mattress' => ['bed', 'foam', 'sleeping pad'],
    'bunk' => ['bunkbed', 'bunk bed', 'loft bed'],
    'crib' => ['cot', 'baby bed', 'cradle'],
    'futon' => ['sofa bed', 'sleeper', 'convertible'],
    'headboard' => ['bed frame', 'bedhead'],
    'pillow' => ['cushion', 'throw pillow'],
    'blanket' => ['comforter', 'duvet', 'throw', 'quilt'],
    
    // =========================================
    // LIGHTING
    // =========================================
    'lamp' => ['light', 'lantern', 'fixture', 'lighting'],
    'light' => ['lamp', 'lighting', 'fixture', 'chandelier', 'bulb'],
    'chandelier' => ['light', 'fixture', 'pendant', 'hanging light'],
    'sconce' => ['wall light', 'fixture', 'wall lamp'],
    'pendant' => ['hanging light', 'chandelier', 'drop light'],
    'spotlight' => ['spot', 'track light', 'accent light'],
    'floodlight' => ['flood', 'outdoor light', 'security light'],
    'neon' => ['neon sign', 'led sign', 'light sign'],
    'candle' => ['candlestick', 'taper', 'pillar'],
    'lantern' => ['lamp', 'light', 'hurricane lamp'],
    'torch' => ['flashlight', 'light'],
    'streetlight' => ['street lamp', 'lamppost', 'light pole'],
    'fairy lights' => ['string lights', 'christmas lights', 'twinkle lights'],
    
    // =========================================
    // DECOR & ART
    // =========================================
    'rug' => ['carpet', 'mat', 'runner', 'area rug'],
    'carpet' => ['rug', 'mat', 'flooring', 'floor covering'],
    'curtain' => ['drape', 'blind', 'shade', 'window treatment'],
    'blind' => ['curtain', 'shade', 'shutter', 'window blind'],
    'mirror' => ['glass', 'looking glass', 'vanity mirror'],
    'plant' => ['flower', 'pot', 'planter', 'greenery', 'houseplant'],
    'vase' => ['pot', 'planter', 'vessel', 'urn'],
    'picture' => ['painting', 'artwork', 'frame', 'poster', 'photo'],
    'painting' => ['picture', 'artwork', 'art', 'canvas'],
    'poster' => ['picture', 'print', 'artwork', 'wall art'],
    'clock' => ['timepiece', 'watch', 'wall clock'],
    'statue' => ['sculpture', 'figurine', 'bust'],
    'sculpture' => ['statue', 'figurine', 'art piece'],
    'trophy' => ['award', 'cup', 'medal'],
    'flag' => ['banner', 'pennant'],
    'tapestry' => ['wall hanging', 'textile art'],
    'wreath' => ['garland', 'decoration'],
    'ornament' => ['decoration', 'decor', 'trinket'],
    
    // =========================================
    // ELECTRONICS & APPLIANCES
    // =========================================
    'tv' => ['television', 'screen', 'monitor', 'flatscreen'],
    'television' => ['tv', 'screen', 'monitor', 'telly'],
    'monitor' => ['screen', 'tv', 'display', 'computer screen'],
    'computer' => ['pc', 'desktop', 'laptop', 'workstation'],
    'laptop' => ['computer', 'notebook', 'portable'],
    'phone' => ['telephone', 'mobile', 'cell', 'landline'],
    'radio' => ['stereo', 'speaker', 'receiver', 'boombox'],
    'speaker' => ['stereo', 'audio', 'sound system', 'subwoofer'],
    'printer' => ['copier', 'fax', 'scanner'],
    'fan' => ['ventilator', 'cooling', 'ceiling fan'],
    'ac' => ['air conditioner', 'air conditioning', 'hvac', 'cooling'],
    'heater' => ['radiator', 'heating', 'space heater'],
    'projector' => ['beamer', 'display'],
    
    // =========================================
    // KITCHEN
    // =========================================
    'fridge' => ['refrigerator', 'freezer', 'cooler', 'icebox'],
    'refrigerator' => ['fridge', 'freezer', 'cooler'],
    'stove' => ['oven', 'range', 'cooker', 'cooktop', 'burner'],
    'oven' => ['stove', 'range', 'cooker'],
    'microwave' => ['oven', 'micro'],
    'sink' => ['basin', 'washbasin', 'wash basin'],
    'dishwasher' => ['washer', 'dish washer'],
    'toaster' => ['toaster oven'],
    'blender' => ['mixer', 'food processor'],
    'kettle' => ['teapot', 'pot'],
    'coffee maker' => ['coffee machine', 'espresso', 'brewer'],
    'pot' => ['pan', 'cookware', 'saucepan'],
    'pan' => ['pot', 'skillet', 'frying pan'],
    
    // =========================================
    // BATHROOM
    // =========================================
    'toilet' => ['wc', 'commode', 'lavatory', 'loo', 'john'],
    'shower' => ['bath', 'tub', 'shower stall'],
    'bathtub' => ['tub', 'bath', 'shower', 'jacuzzi'],
    'towel' => ['cloth', 'linen', 'bath towel'],
    'soap' => ['dispenser', 'hand soap'],
    'medicine cabinet' => ['bathroom cabinet', 'vanity'],
    'vanity' => ['bathroom sink', 'wash stand'],
    
    // =========================================
    // OUTDOOR & GARDEN
    // =========================================
    'grill' => ['bbq', 'barbecue', 'smoker'],
    'bbq' => ['grill', 'barbecue'],
    'umbrella' => ['parasol', 'shade', 'beach umbrella'],
    'fence' => ['barrier', 'railing', 'wall', 'fencing'],
    'gate' => ['door', 'entrance', 'entry'],
    'pool' => ['swimming pool', 'hot tub', 'jacuzzi'],
    'fountain' => ['water feature', 'pond'],
    'hammock' => ['swing', 'lounger'],
    'planter' => ['pot', 'flower pot', 'plant pot'],
    'lawn chair' => ['deck chair', 'patio chair', 'outdoor chair'],
    'fire pit' => ['firepit', 'campfire', 'outdoor fire'],
    'shed' => ['storage shed', 'garden shed', 'outbuilding'],
    'mailbox' => ['letterbox', 'post box'],
    'birdbath' => ['bird bath', 'bird feeder'],
    'gazebo' => ['pergola', 'pavilion', 'canopy'],
    
    // =========================================
    // OFFICE & COMMERCIAL
    // =========================================
    'office chair' => ['desk chair', 'task chair', 'swivel chair'],
    'cubicle' => ['partition', 'divider', 'office divider'],
    'whiteboard' => ['board', 'marker board', 'dry erase'],
    'bulletin board' => ['cork board', 'notice board', 'pin board'],
    'podium' => ['lectern', 'stand', 'pulpit'],
    'register' => ['cash register', 'pos', 'checkout'],
    'counter' => ['checkout', 'service counter', 'reception'],
    'reception desk' => ['front desk', 'welcome desk'],
    'display case' => ['showcase', 'glass case', 'vitrine'],
    'rack' => ['shelf', 'display rack', 'stand'],
    'mannequin' => ['dummy', 'display dummy', 'form'],
    'atm' => ['cash machine', 'bank machine'],
    'vending machine' => ['vending', 'snack machine', 'drink machine'],
    
    // =========================================
    // INDUSTRIAL & UTILITY
    // =========================================
    'barrel' => ['drum', 'keg', 'cask', 'container'],
    'pallet' => ['skid', 'platform'],
    'ladder' => ['step ladder', 'steps', 'staircase'],
    'scaffold' => ['scaffolding', 'platform'],
    'toolbox' => ['tool chest', 'tool cabinet'],
    'workstation' => ['work area', 'desk', 'station'],
    'conveyor' => ['belt', 'conveyor belt'],
    'generator' => ['power generator', 'genset'],
    'tank' => ['container', 'reservoir', 'cistern'],
    'pipe' => ['piping', 'tube', 'conduit'],
    'vent' => ['ventilation', 'duct', 'air vent'],
    'dumpster' => ['bin', 'trash', 'garbage'],
    'trash can' => ['garbage can', 'bin', 'waste basket', 'rubbish bin'],
    
    // =========================================
    // MEDICAL & HOSPITAL
    // =========================================
    'hospital bed' => ['medical bed', 'patient bed', 'gurney'],
    'gurney' => ['stretcher', 'hospital bed', 'medical bed'],
    'wheelchair' => ['chair', 'mobility'],
    'iv stand' => ['drip stand', 'infusion stand'],
    'examination table' => ['exam table', 'medical table'],
    'defibrillator' => ['aed', 'defib'],
    'oxygen tank' => ['o2 tank', 'medical tank'],
    
    // =========================================
    // BAR & RESTAURANT
    // =========================================
    'bar' => ['counter', 'pub', 'bartop'],
    'barstool' => ['bar stool', 'stool', 'high chair'],
    'booth' => ['seating', 'banquette'],
    'keg' => ['barrel', 'beer keg'],
    'tap' => ['beer tap', 'draft', 'draught'],
    'menu board' => ['chalkboard', 'specials board'],
    'wine rack' => ['bottle rack', 'wine storage'],
    
    // =========================================
    // DOORS & WINDOWS
    // =========================================
    'door' => ['entry', 'entrance', 'doorway', 'gate'],
    'window' => ['glass', 'pane', 'glazing'],
    'shutter' => ['blind', 'window cover'],
    'screen' => ['screen door', 'mesh'],
    'awning' => ['canopy', 'shade', 'overhang'],
    
    // =========================================
    // WALL & FLOOR
    // =========================================
    'wallpaper' => ['wall covering', 'wall decor'],
    'tile' => ['flooring', 'ceramic', 'porcelain'],
    'hardwood' => ['wood floor', 'wooden floor', 'parquet'],
    'vinyl' => ['linoleum', 'lino', 'flooring'],
    'baseboard' => ['skirting', 'molding', 'trim'],
    'crown molding' => ['molding', 'trim', 'cornice'],
    
    // =========================================
    // MATERIALS & FINISHES
    // =========================================
    'wooden' => ['wood', 'timber', 'oak', 'pine', 'mahogany', 'walnut'],
    'wood' => ['wooden', 'timber', 'lumber'],
    'metal' => ['steel', 'iron', 'aluminum', 'chrome', 'brass'],
    'steel' => ['metal', 'iron', 'stainless'],
    'glass' => ['crystal', 'transparent', 'glazed'],
    'leather' => ['vinyl', 'faux leather', 'pleather', 'hide'],
    'fabric' => ['cloth', 'textile', 'upholstery'],
    'plastic' => ['polymer', 'acrylic', 'pvc'],
    'marble' => ['stone', 'granite', 'quartz'],
    'concrete' => ['cement', 'stone'],
    'wicker' => ['rattan', 'bamboo', 'cane'],
    'velvet' => ['velour', 'plush'],
    
    // =========================================
    // STYLES & AESTHETICS
    // =========================================
    'modern' => ['contemporary', 'minimalist', 'sleek'],
    'vintage' => ['retro', 'antique', 'classic', 'old'],
    'rustic' => ['country', 'farmhouse', 'rural', 'cottage'],
    'industrial' => ['factory', 'loft', 'urban'],
    'minimalist' => ['modern', 'simple', 'clean'],
    'traditional' => ['classic', 'conventional', 'timeless'],
    'bohemian' => ['boho', 'eclectic', 'hippie'],
    'scandinavian' => ['nordic', 'scandi', 'swedish'],
    'mid-century' => ['midcentury', 'retro', '50s', '60s'],
    'art deco' => ['deco', 'gatsby', '1920s'],
    'victorian' => ['antique', 'ornate', 'classic'],
    'coastal' => ['beach', 'nautical', 'seaside'],
    'luxury' => ['premium', 'high-end', 'deluxe', 'fancy'],
    
    // =========================================
    // COLORS
    // =========================================
    'black' => ['dark', 'ebony', 'noir'],
    'white' => ['cream', 'ivory', 'off-white', 'pearl'],
    'brown' => ['tan', 'beige', 'chocolate', 'coffee'],
    'gray' => ['grey', 'silver', 'charcoal', 'slate'],
    'red' => ['crimson', 'burgundy', 'maroon', 'scarlet'],
    'blue' => ['navy', 'azure', 'cobalt', 'teal'],
    'green' => ['olive', 'sage', 'emerald', 'forest'],
    'yellow' => ['gold', 'golden', 'mustard'],
    'orange' => ['tangerine', 'amber', 'rust'],
    'pink' => ['rose', 'blush', 'salmon'],
    'purple' => ['violet', 'plum', 'lavender', 'mauve'],
    
    // =========================================
    // SIZES
    // =========================================
    'small' => ['sm', 'mini', 'compact', 'little', 'tiny'],
    'medium' => ['md', 'mid', 'regular', 'standard'],
    'large' => ['lg', 'big', 'oversized'],
    'xl' => ['extra large', 'xxl', 'jumbo'],
    'tall' => ['high', 'long'],
    'short' => ['low', 'small'],
    'wide' => ['broad', 'spacious'],
    'narrow' => ['slim', 'thin'],
    
    // =========================================
    // COMMON ABBREVIATIONS & VARIANTS
    // =========================================
    'sm' => ['small'],
    'md' => ['medium'],
    'lg' => ['large'],
    'prop' => ['furniture', 'item', 'object'],
    'w/' => ['with'],
    'w/o' => ['without'],
];
