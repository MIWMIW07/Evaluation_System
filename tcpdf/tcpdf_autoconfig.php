<?php
// TCPDF Autoconfiguration
if (!defined('K_TCPDF_EXTERNAL_CONFIG')) {
    
    define('K_TCPDF_EXTERNAL_CONFIG', true);
    
    // Installation path
    define('K_PATH_MAIN', '/var/www/html/tcpdf/');
    
    // URL path
    define('K_PATH_URL', '/');
    
    // Fonts path
    define('K_PATH_FONTS', K_PATH_MAIN . 'fonts/');
    
    // Cache directory path
    define('K_PATH_CACHE', K_PATH_MAIN . 'cache/');
    
    // Images path
    define('K_PATH_IMAGES', K_PATH_MAIN . 'images/');
    
    // Blank image
    define('K_BLANK_IMAGE', K_PATH_IMAGES . '_blank.png');
    
    // Page formats
    define('PDF_PAGE_FORMAT', 'A4');
    define('PDF_PAGE_ORIENTATION', 'P');
    
    // Cell height ratio
    define('K_CELL_HEIGHT_RATIO', 1.25);
    
    // Title magnification factor
    define('K_TITLE_MAGNIFICATION', 1.3);
}
?>
