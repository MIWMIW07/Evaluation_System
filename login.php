<?php
// login.php - Enhanced login system
session_start();

require_once 'includes/security.php';

// Check for logout message
if (isset($_SESSION['logout_message'])) {
    $success = $_SESSION['logout_message'];
    unset($_SESSION['logout_message']);
    
    // Also check if user was admin to show appropriate redirect option
    $was_admin = isset($_SESSION['user_type_was']) && $_SESSION['user_type_was'] === 'admin';
    unset($_SESSION['user_type_was']);
}

// If user is already logged in, redirect appropriately
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_type'] === 'admin') {
        header('Location: admin.php');
        exit;
    } else {
        header('Location: student_dashboard.php');
        exit;
    }
}

// Include database connection
require_once 'includes/db_connection.php';

$error = '';
$success = isset($success) ? $success : '';

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!validate_csrf_token($_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }
    
    try {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        
        // Validate input
        if (empty($username) || empty($password)) {
            throw new Exception("Username and password are required.");
        }
        
        // Check user in database
        $stmt = query("SELECT id, username, password, user_type, full_name, student_id, program, section FROM users WHERE username = ?", [$username]);
        $user = fetch_assoc($stmt);
        
        if ($user && password_verify($password, $user['password'])) {
            // Valid login - create session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['student_id'] = $user['student_id'];
            $_SESSION['program'] = $user['program'];
            $_SESSION['section'] = $user['section'];
            
            // Update last login time
            query("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?", [$user['id']]);
            
            // Redirect based on user type
            if ($user['user_type'] === 'admin') {
                header('Location: admin.php');
                exit;
            } else {
                header('Location: student_dashboard.php');
                exit;
            }
        } else {
            throw new Exception("Invalid username or password.");
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Teacher Evaluation System</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: #333;
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            max-width: 420px;
            width: 100%;
            background: white;
            padding: 40px 35px;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 35px;
        }
        
        .login-header h1 {
            color: #800020; /* Maroon */
            margin-bottom: 12px;
            font-size: 1.8em;
            font-weight: 600;
        }
        
        .login-header p {
            color: #6c757d;
            font-size: 0.95em;
        }
        
        .institution-info {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eaeaea;
        }
        
        .institution-info h3 {
            color: #800020; /* Maroon */
            font-size: 0.95em;
            margin-bottom: 5px;
        }
        
        .institution-info p {
            color: #6c757d;
            font-size: 0.85em;
        }
        
        .form-group {
            margin-bottom: 22px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #495057;
            font-size: 0.95em;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 15px;
            transition: all 0.2s ease;
            background: #fafafa;
        }
        
        .form-group input:focus {
            border-color: #D4AF37; /* Gold */
            outline: none;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15);
            background: white;
        }
        
        .login-btn {
            width: 100%;
            background: #800020; /* Maroon */
            color: white;
            padding: 14px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.2s ease;
            margin-top: 10px;
        }
        
        .login-btn:hover {
            background: #600018; /* Darker maroon */
            transform: translateY(-1px);
        }
        
        .alert {
            padding: 12px 16px;
            margin-bottom: 20px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.9em;
        }
        
        .alert-error {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
        }
        
        .demo-accounts {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-top: 25px;
        }
        
        .demo-accounts h4 {
            color: #495057;
            margin-bottom: 15px;
            text-align: center;
            font-size: 0.95em;
            font-weight: 600;
        }
        
        .demo-account {
            background: white;
            padding: 12px 14px;
            border-radius: 6px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .demo-account:last-child {
            margin-bottom: 0;
        }
        
        .demo-account-info {
            flex: 1;
        }
        
        .demo-account-type {
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .demo-account-type.admin {
            background: #800020; /* Maroon */
        }
        
        .demo-account-type.student {
            background: #D4AF37; /* Gold */
        }
        
        .demo-credentials {
            font-family: 'Courier New', monospace;
            color: #2c3e50;
            font-size: 0.85em;
        }
        
        .use-btn {
            background: transparent;
            color: #800020; /* Maroon */
            border: 1px solid #800020;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8em;
            transition: all 0.2s ease;
        }
        
        .use-btn:hover {
            background: #800020;
            color: white;
        }
        
        .footer-links {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eaeaea;
        }
        
        .footer-links a {
            color: #6c757d;
            text-decoration: none;
            font-size: 0.9em;
            margin: 0 10px;
            transition: color 0.2s ease;
        }
        
        .footer-links a:hover {
            color: #800020; /* Maroon */
            text-decoration: underline;
        }
        
        .loading-spinner {
            display: none;
            width: 18px;
            height: 18px;
            border: 2px solid #ffffff;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 8px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 25px 20px;
            }
            
            .login-header h1 {
                font-size: 1.5em;
            }
            
            .demo-account {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            
            .demo-account-info {
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Teacher Evaluation System</h1>
            <p>Sign in to your account</p>
        </div>
        
        <div class="institution-info">
            <h3>Philippine Technological Institute of Science Arts and Trade, Inc.</h3>
            <p>GMA-BRANCH (2nd Semester 2024-2025)</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" 
                       id="username" 
                       name="username" 
                       required 
                       autocomplete="username"
                       placeholder="Enter your username"
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       required 
                       autocomplete="current-password"
                       placeholder="Enter your password">
            </div>
            
            <button type="submit" class="login-btn" id="loginBtn">
                <span class="loading-spinner" id="loadingSpinner"></span>
                <span id="btnText">Sign In</span>
            </button>
        </form>
        
        <div class="demo-accounts">
            <h4>Demo Accounts</h4>
            
            <div class="demo-account">
                <div class="demo-account-info">
                    <div class="demo-credentials">admin / admin123</div>
                    <small>System Administrator</small>
                </div>
                <div>
                    <span class="demo-account-type admin">Admin</span>
                    <button type="button" class="use-btn" onclick="fillLogin('admin', 'admin123')">Use</button>
                </div>
            </div>
            
            <div class="demo-account">
                <div class="demo-account-info">
                    <div class="demo-credentials">student1 / pass123</div>
                    <small>Juan Dela Cruz (SHS)</small>
                </div>
                <div>
                    <span class="demo-account-type student">Student</span>
                    <button type="button" class="use-btn" onclick="fillLogin('student1', 'pass123')">Use</button>
                </div>
            </div>
        </div>
        
        <div class="footer-links">
            <a href="database_setup.php">Database Setup</a>
            <a href="test_connection.php">Test Connection</a>
        </div>
    </div>

    <script>
        // Auto-fill login credentials
        function fillLogin(username, password) {
            document.getElementById('username').value = username;
            document.getElementById('password').value = password;
            
            // Add visual feedback
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.style.borderColor = '#D4AF37';
                input.style.boxShadow = '0 0 0 3px rgba(212, 175, 55, 0.2)';
                setTimeout(() => {
                    input.style.borderColor = '#ddd';
                    input.style.boxShadow = 'none';
                }, 1500);
            });
        }
        
        // Form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            const spinner = document.getElementById('loadingSpinner');
            const btnText = document.getElementById('btnText');
            
            // Show loading state
            btn.disabled = true;
            spinner.style.display = 'inline-block';
            btnText.textContent = 'Signing in...';
        });
        
        // Focus on username input when page loads
        window.addEventListener('load', function() {
            document.getElementById('username').focus();
        });
    </script>
</body>
</html>
