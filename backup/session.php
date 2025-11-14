<?php
/**
 * User App Session Management
 * Handles user authentication, session creation, and 6-month session timeout
 */

// 6 months = 6 * 30 * 24 * 60 * 60 = 15,552,000 seconds
define('USER_SESSION_TIMEOUT', 15552000);
define('USER_SESSION_NAME', 'flexnet_user');

/**
 * Initialize secure user session
 */
function initUserSession() {
    // Only configure session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_name(USER_SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => USER_SESSION_TIMEOUT,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
}

/**
 * Check if user is logged in
 * @return bool
 */
function isUserLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['phone']);
}

/**
 * Create user session after successful login
 * @param int $user_id
 * @param string $phone
 * @param int $household_id
 * @param string $name
 * @param bool $requires_pin_change
 */
function createUserSession($user_id, $phone, $household_id, $name, $requires_pin_change = false) {
    $_SESSION['user_id'] = $user_id;
    $_SESSION['phone'] = $phone;
    $_SESSION['household_id'] = $household_id;
    $_SESSION['name'] = $name;
    $_SESSION['requires_pin_change'] = $requires_pin_change;
    $_SESSION['login_time'] = time();
}

/**
 * Get current logged-in user ID
 * @return int|null
 */
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current logged-in user phone
 * @return string|null
 */
function getUserPhone() {
    return $_SESSION['phone'] ?? null;
}

/**
 * Get current user's household ID
 * @return int|null
 */
function getUserHouseholdId() {
    return $_SESSION['household_id'] ?? null;
}

/**
 * Get current user's name
 * @return string|null
 */
function getUserName() {
    return $_SESSION['name'] ?? null;
}

/**
 * Check if user requires PIN change (first login)
 * @return bool
 */
function requiresPinChange() {
    return $_SESSION['requires_pin_change'] ?? false;
}

/**
 * Clear PIN change requirement
 */
function clearPinChangeFlag() {
    $_SESSION['requires_pin_change'] = false;
}

/**
 * Require user to be logged in
 * If not logged in, redirect to login page
 */
function requireUserAuth() {
    initUserSession();
    
    if (!isUserLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Require PIN change (first login only)
 * If not required, redirect to dashboard
 */
function requirePinChange() {
    initUserSession();
    
    if (!isUserLoggedIn()) {
        header('Location: login.php');
        exit;
    }
    
    if (!requiresPinChange()) {
        header('Location: dashboard.php');
        exit;
    }
}

/**
 * Logout user and destroy session
 */
function logoutUser() {
    // Clear session variables
    $_SESSION = [];
    
    // Destroy session
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    
    // Clear session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
}

/**
 * Get session remaining time in seconds
 * @return int
 */
function getSessionRemainingTime() {
    if (!isUserLoggedIn()) {
        return 0;
    }
    
    $login_time = $_SESSION['login_time'] ?? time();
    $elapsed = time() - $login_time;
    $remaining = USER_SESSION_TIMEOUT - $elapsed;
    
    return max(0, $remaining);
}

/**
 * Check if session is about to expire (less than 24 hours remaining)
 * @return bool
 */
function isSessionExpiringSoon() {
    $remaining = getSessionRemainingTime();
    $one_day_seconds = (24 * 60 * 60);
    
    return $remaining < $one_day_seconds;
}

/**
 * Extend session (reset login time)
 */
function extendUserSession() {
    if (isUserLoggedIn()) {
        $_SESSION['login_time'] = time();
    }
}

// Initialize session on include
initUserSession();
?>
