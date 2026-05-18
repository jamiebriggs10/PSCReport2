<?php
/**
 * Logout Page
 * Presswick Sailing Club Issue Reporting System
 */

require_once 'includes/auth.php';

// Logout user
logoutUser();

// Set flash message
$_SESSION['flash_message'] = 'You have been successfully logged out.';
$_SESSION['flash_type'] = 'success';

// Redirect to login page
header('Location: ' . getFullUrl('login.php'));
exit;
?>