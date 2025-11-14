<?php
/**
 * Database Connection Handler
 * Handles all database connections and global connection instance
 */

// Database connection credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'mysql');
define('DB_NAME', 'flexnet');
define('DB_PORT', 3306);

// Create connection with error handling
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
}

// Set charset
$conn->set_charset("utf8mb4");

// Enable error reporting for development (disable in production)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Make connection available globally
$GLOBALS['db_connection'] = $conn;

/**
 * Get the database connection instance
 * @return mysqli
 */
function getDBConnection() {
    return $GLOBALS['db_connection'];
}
?>
