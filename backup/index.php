<?php
/**
 * User App Router
 * Redirects to appropriate page based on first visit and login status
 */

require_once 'includes/session.php';

// Initialize session
initUserSession();

// Check if user is logged in
if (isUserLoggedIn()) {
    // User is logged in
    if (requiresPinChange()) {
        // Force PIN change
        header('Location: change-pin.php');
    } else {
        // Go to dashboard
        header('Location: dashboard.php');
    }
} else {
    // User is not logged in
    // Show login page (onboarding is shown first on new visits)
    header('Location: login.php');
}
exit;
?>
