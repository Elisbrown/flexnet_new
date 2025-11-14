<?php
/**
 * User App Configuration
 * Database connection and global settings
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'mysql');
define('DB_NAME', 'flexnet');

// App Configuration
define('APP_NAME', 'Flexnet');
define('APP_URL', 'http://' . $_SERVER['HTTP_HOST']);
define('APP_VERSION', '1.0.0');

// Fapshi Payment Gateway Configuration
define('FAPSHI_API_USER', 'replace_me_with_apiuser');
define('FAPSHI_API_KEY', 'replace_me_with_apikey');
define('FAPSHI_MIN_AMOUNT', 100); // Minimum 100 XAF

// Session Configuration (already in session.php)
// define('USER_SESSION_TIMEOUT', 15552000); // 6 months

// Asset URLs
define('FAVICON_APPLE', '/favicon/apple-touch-icon.png');
define('FAVICON_ANDROID_192', '/favicon/android-chrome-192x192.png');
define('FAVICON_ANDROID_512', '/favicon/android-chrome-512x512.png');

// Meta Tags
define('APP_DESCRIPTION', 'Manage your internet subscription with flexibility');
define('APP_KEYWORDS', 'internet, subscription, billing, Flexnet');
define('APP_THEME_COLOR', '#27e46a');
define('APP_BG_COLOR', '#050505');

// Payment Channels
$PAYMENT_CHANNELS = [
    'ORANGE_MONEY' => 'Orange Money',
    'MTN_MOMO' => 'MTN Mobile Money',
    'CARD' => 'Credit/Debit Card'
];

// Subscription Types
$SUBSCRIPTION_TYPES = [
    'MONTHLY' => 'Monthly',
    'QUARTERLY' => 'Quarterly',
    'ANNUALLY' => 'Annually'
];

?>
