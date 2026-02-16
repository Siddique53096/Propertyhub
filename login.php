<?php
// login.php - User Login
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid security token. Please try again.";
    } else {
        // Sanitize inputs
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember_me = isset($_POST['remember_me']);
        
        // Validation
        if (empty($email) || empty($password)) {
            $errors[] = "Please enter both email and password";
        } else {
            // Check login attempts (prevent brute force)
            if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
                if (isset($_SESSION['last_attempt_time']) && (time() - $_SESSION['last_attempt_time']) < 900) {
                    $errors[] = "Too many failed login attempts. Please try again after 15 minutes.";
                } else {
                    // Reset attempts after timeout
                    $_SESSION['login_attempts'] = 0;
                }
            }
            
            if (empty($errors)) {
                // Fetch user from database
                $stmt = $con->prepare("SELECT id, full_name, email, password, is_active FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    
                    // Check if account is active
                    if ($user['is_active'] != 1) {
                        $errors[] = "Your account has been deactivated. Please contact support.";
                    }
                    // Verify password
                    elseif (verifyPassword($password, $user['password'])) {
                        // Password is correct - create session
                        session_regenerate_id(true); // Prevent session fixation
                        
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['full_name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['login_time'] = time();
                        
                        // Reset login attempts
                        unset($_SESSION['login_attempts']);
                        unset($_SESSION['last_attempt_time']);
                        
                        // Remember me functionality
                        if ($remember_me) {
                            $token = bin2hex(random_bytes(32));
                            setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
                            
                            // Store token in database
                            $expires = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60));
                            $ip = $_SERVER['REMOTE_ADDR'];
                            $user_agent = $_SERVER['HTTP_USER_AGENT'];
                            
                            $stmt2 = $con->prepare("INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)");
                            $stmt2->bind_param("issss", $user['id'], $token, $ip, $user_agent, $expires);
                            $stmt2->execute();
                            $stmt2->close();
                        }
                        
                        setFlashMessage('success', 'Welcome back, ' . $user['full_name'] . '!');
                        
                        // Redirect to dashboard or requested page
                        $redirect_to = isset($_GET['redirect']) ? $_GET['redirect'] : 'dashboard.php';
                        redirect($redirect_to);
                    } else {
                        // Wrong password
                        if (!isset($_SESSION['login_attempts'])) {
                            $_SESSION['login_attempts'] = 0;
                        }
                        $_SESSION['login_attempts']++;
                        $_SESSION['last_attempt_time'] = time();
                        
                        $errors[] = "Invalid email or password";
                    }
                } else {
                    // User not found
                    if (!isset($_SESSION['login_attempts'])) {
                        $_SESSION['login_attempts'] = 0;
                    }
                    $_SESSION['login_attempts']++;
                    $_SESSION['last_attempt_time'] = time();
                    
                    $errors[] = "Invalid email or password";
                }
                
                $stmt->close();
            }
        }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="ur">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Navigation -->
    <?php include 'includes/header.php'; ?>

    <!-- Flash Message -->
    <?php if ($flash): ?>
    <div class="flash-message flash-<?php echo htmlspecialchars($flash['type']); ?>">
        <?php echo htmlspecialchars($flash['message']); ?>
    </div>
    <?php endif; ?>

    <div class="auth-container">
        <div class="auth-box">
            <h2>Welcome Back!</h2>
            <p class="auth-subtitle">Login to access your account</p>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                           required placeholder="Enter your email" autocomplete="email">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" 
                           required placeholder="Enter your password" autocomplete="current-password">
                </div>
                
                <div class="form-group form-row">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember_me">
                        Remember me
                    </label>
                    <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full">Login</button>
            </form>
            
            <div class="auth-divider">
                <span>OR</span>
            </div>
            
            <div class="auth-footer">
                Don't have an account? <a href="register.php">Register here</a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
</body>
</html>