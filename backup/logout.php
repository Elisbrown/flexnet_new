<?php
/**
 * Logout Page
 * Clears user session and redirects to login
 */

require_once 'includes/session.php';

// Logout user
logoutUser();

// Redirect to login page
header('Location: login.php');
exit;
?>
