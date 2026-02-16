<?php
/**
 * config.php - Database Configuration & Security Settings
 * PropertyHub - Pakistan Real Estate Platform
 */

// Error reporting (Development mode - Production mein off kar dein)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

// ============================================
// DATABASE CONFIGURATION
// ============================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'propertyhub');

// ============================================
// SITE CONFIGURATION
// ============================================
define('SITE_NAME', 'PropertyHub');
define('SITE_URL', 'http://localhost/propertyhub');
define('UPLOAD_PATH', 'uploads/properties/');

// ============================================
// SECURITY SETTINGS
// ============================================
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 900);
define('SESSION_TIMEOUT', 3600);

// ============================================
// ✅ DATABASE CONNECTION (YOUR STYLE IMPLEMENTED)
// ============================================

$con = mysqli_connect("localhost", "root", "", "propertyhub");
if (!$con) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset UTF-8
mysqli_set_charset($con, "utf8mb4");

// ============================================
// SECURITY FUNCTIONS
// ============================================

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    return true;
}

function sanitizeInput($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = sanitizeInput($value);
        }
        return $data;
    }

    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePhone($phone) {
    $phone = preg_replace('/[\s\-]/', '', $phone);
    return preg_match('/^(\+92|0)?[0-9]{10}$/', $phone);
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
}

function redirect($url) {
    if (!headers_sent()) {
        header("Location: " . $url);
    } else {
        echo "<script>window.location.href='" . $url . "';</script>";
    }
    exit();
}

function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['error_message'] = "Please login to continue";
        redirect('login.php');
    }
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function formatPrice($price) {
    if ($price >= 10000000) {
        $crore = $price / 10000000;
        return 'PKR ' . number_format($crore, 2) . ' Crore';
    } elseif ($price >= 100000) {
        $lac = $price / 100000;
        return 'PKR ' . number_format($lac, 2) . ' Lac';
    } else {
        return 'PKR ' . number_format($price, 0, '.', ',');
    }
}

function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $difference = time() - $timestamp;

    if ($difference < 60) {
        return $difference . ' seconds ago';
    } elseif ($difference < 3600) {
        $minutes = floor($difference / 60);
        return $minutes . ' minutes ago';
    } elseif ($difference < 86400) {
        $hours = floor($difference / 3600);
        return $hours . ' hours ago';
    } elseif ($difference < 604800) {
        $days = floor($difference / 86400);
        return $days . ' days ago';
    } else {
        return date('d M Y', $timestamp);
    }
}

function setFlashMessage($type, $message) {
    $_SESSION['flash_type'] = $type;
    $_SESSION['flash_message'] = $message;
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = [
            'type' => $_SESSION['flash_type'],
            'message' => $_SESSION['flash_message']
        ];
        unset($_SESSION['flash_type']);
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

function logActivity($user_id, $action, $details = '') {
    global $con;

    if (!$con) return false;

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    $stmt = mysqli_prepare($con, "INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "issss", $user_id, $action, $details, $ip, $user_agent);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $result;
}

function checkSessionTimeout() {
    if (isLoggedIn()) {
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
                session_destroy();
                setFlashMessage('error', 'Session expired');
                redirect('login.php');
            }
        }
        $_SESSION['last_activity'] = time();
    }
}

function cleanOldSessions() {
    global $con;
    mysqli_query($con, "DELETE FROM user_sessions WHERE expires_at < NOW()");
}

function uploadFile($file, $target_dir = 'uploads/properties/') {
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
    $max_size = 5 * 1024 * 1024;

    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File too large'];
    }

    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }

    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    $filename = uniqid() . '_' . basename($file['name']);
    $target_file = $target_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return ['success' => true, 'filename' => $filename];
    }

    return ['success' => false];
}

function deleteFile($filepath) {
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    return false;
}

function getUserInfo($user_id) {
    global $con;

    $stmt = mysqli_prepare($con, "SELECT id, full_name, email FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}

// ============================================
// AUTO RUN
// ============================================
checkSessionTimeout();

if (rand(1, 100) == 1) {
    cleanOldSessions();
}
?>
