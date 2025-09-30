<?php
// /var/www/html/tcpdf/tcpdf_autoconfig.php
if (!defined('K_TCPDF_EXTERNAL_CONFIG')) {
    
    // DOCUMENT_ROOT fix for IIS Servers
    if (empty($_SERVER['DOCUMENT_ROOT'])) {
        if (isset($_SERVER['SCRIPT_FILENAME'])) {
            $_SERVER['DOCUMENT_ROOT'] = str_replace( '\\', '/', substr($_SERVER['SCRIPT_FILENAME'], 0, 0-strlen($_SERVER['PHP_SELF'])));
        } elseif (isset($_SERVER['PATH_TRANSLATED'])) {
            $_SERVER['DOCUMENT_ROOT'] = str_replace( '\\', '/', substr(str_replace('\\\\', '\\', $_SERVER['PATH_TRANSLATED']), 0, 0-strlen($_SERVER['PHP_SELF'])));
        } else {
            // Define here your DOCUMENT_ROOT path if the previous fails
            $_SERVER['DOCUMENT_ROOT'] = '/var/www/html';
        }
    }
    
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
    
    // Creator
    define('PDF_CREATOR', 'TCPDF');
    
    // Author
    define('PDF_AUTHOR', 'TCPDF');
    
    // Header
    define('PDF_HEADER_TITLE', 'TCPDF Example');
    define('PDF_HEADER_STRING', "by Nicola Asuni - Tecnick.com\nwww.tcpdf.org");
    define('PDF_HEADER_LOGO', 'tcpdf_logo.jpg');
    define('PDF_HEADER_LOGO_WIDTH', 30);
    
    // Margins
    define('PDF_MARGIN_LEFT', 15);
    define('PDF_MARGIN_TOP', 27);
    define('PDF_MARGIN_RIGHT', 15);
    define('PDF_MARGIN_BOTTOM', 25);
    
    // Cell height ratio
    define('K_CELL_HEIGHT_RATIO', 1.25);
    
    // Title magnification factor
    define('K_TITLE_MAGNIFICATION', 1.3);
    
    // Small ratio
    define('K_SMALL_RATIO', 2/3);
    
    // Thai characters
    define('K_THAI_TOPCHARS', true);
    
    // TCPDF methods
    define('K_TCPDF_CALLS_IN_HTML', false);
    
    // Memory limit
    define('K_TCPDF_MEMORY_LIMIT', '256M');
}
?>
