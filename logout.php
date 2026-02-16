<?php
// logout.php - User Logout
require_once 'config.php';

// Check if user is logged in
if (isLoggedIn()) {
    $user_id = getCurrentUserId();
    
    // Delete remember me token if exists
    if (isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        
        // Delete from database
        $stmt = $con->prepare("DELETE FROM user_sessions WHERE session_token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->close();
        
        // Delete cookie
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }
    
    // Destroy session
    $_SESSION = array();
    
    // Delete session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Destroy session
    session_destroy();
    
    // Start new session for flash message
    session_start();
    setFlashMessage('success', 'You have been logged out successfully!');
}

// Redirect to homepage
redirect('index.php');
?>