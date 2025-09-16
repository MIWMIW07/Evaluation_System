<?php
// login.php - Enhanced login system
session_start();

require_once 'security.php';

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
require_once 'db_connection.php';

$error = '';
$success = '';

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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            max-width: 450px;
            width: 100%;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            transform: translateY(-20px);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 35px;
            padding-bottom: 25px;
            border-bottom: 3px solid #4CAF50;
        }
        
        .login-header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 1.8em;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .login-header p {
            color: #7f8c8d;
            font-size: 0.95em;
        }
        
        .institution-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            text-align: center;
            border-left: 4px solid #2196F3;
        }
        
        .institution-info h3 {
            color: #1976D2;
            font-size: 0.9em;
            margin-bottom: 5px;
        }
        
        .institution-info p {
            color: #666;
            font-size: 0.85em;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.95em;
        }
        
        .form-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #fafafa;
        }
        
        .form-group input:focus {
            border-color: #4CAF50;
            outline: none;
            box-shadow: 0 0 15px rgba(76, 175, 80, 0.2);
            background: white;
            transform: translateY(-1px);
        }
        
        .login-btn {
            width: 100%;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 15px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
            margin-bottom: 20px;
        }
        
        .login-btn:hover {
            background: linear-gradient(135deg, #45a049 0%, #4CAF50 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.4);
        }
        
        .login-btn:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .alert-error {
            color: #721c24;
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border-left: 4px solid #dc3545;
        }
        
        .alert-success {
            color: #155724;
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border-left: 4px solid #28a745;
        }
        
        .demo-accounts {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin-top: 25px;
            border-left: 4px solid #ffc107;
        }
        
        .demo-accounts h4 {
            color: #856404;
            margin-bottom: 15px;
            text-align: center;
            font-size: 0.95em;
        }
        
        .demo-account {
            background: white;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .demo-account:last-child {
            margin-bottom: 0;
        }
        
        .demo-account-info {
            flex: 1;
        }
        
        .demo-account-type {
            background: #e74c3c;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .demo-account-type.admin {
            background: #e74c3c;
        }
        
        .demo-account-type.student {
            background: #3498db;
        }
        
        .demo-credentials {
            font-family: 'Courier New', monospace;
            color: #2c3e50;
            font-size: 0.85em;
        }
        
        .use-btn {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8em;
            transition: all 0.3s ease;
        }
        
        .use-btn:hover {
            background: #138496;
            transform: translateY(-1px);
        }
        
        .footer-links {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .footer-links a {
            color: #2196F3;
            text-decoration: none;
            font-size: 0.9em;
            margin: 0 10px;
            transition: color 0.3s ease;
        }
        
        .footer-links a:hover {
            color: #1976D2;
            text-decoration: underline;
        }
        
        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid #ffffff;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 25px;
                margin: 10px;
            }
            
            .login-header h1 {
                font-size: 1.5em;
            }
            
            .demo-account {
                flex-direction: column;
                text-align: center;
            }
            
            .demo-account-info {
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🎓 Login System</h1>
            <p>Teacher Evaluation System</p>
        </div>
        
        <div class="institution-info">
            <h3>Philippine Technological Institute of Science Arts and Trade, Inc.</h3>
            <p>GMA-BRANCH (2nd Semester 2024-2025)</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <div class="form-group">
                <label for="username">👤 Username</label>
                <input type="text" 
                       id="username" 
                       name="username" 
                       required 
                       autocomplete="username"
                       placeholder="Enter your username"
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password">🔒 Password</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       required 
                       autocomplete="current-password"
                       placeholder="Enter your password">
            </div>
            
            <button type="submit" class="login-btn" id="loginBtn">
                <span class="loading-spinner" id="loadingSpinner"></span>
                <span id="btnText">🚀 Sign In</span>
            </button>
        </form>
        
        <div class="demo-accounts">
            <h4>🧪 Demo Accounts</h4>
            
            <div class="demo-account">
                <div class="demo-account-info">
                    <div class="demo-credentials"><strong>admin</strong> / admin123</div>
                    <small>System Administrator</small>
                </div>
                <div>
                    <span class="demo-account-type admin">Admin</span>
                    <button type="button" class="use-btn" onclick="fillLogin('admin', 'admin123')">Use</button>
                </div>
            </div>
            
            <div class="demo-account">
                <div class="demo-account-info">
                    <div class="demo-credentials"><strong>student1</strong> / pass123</div>
                    <small>Juan Dela Cruz (SHS)</small>
                </div>
                <div>
                    <span class="demo-account-type student">Student</span>
                    <button type="button" class="use-btn" onclick="fillLogin('student1', 'pass123')">Use</button>
                </div>
            </div>
            
            <div class="demo-account">
                <div class="demo-account-info">
                    <div class="demo-credentials"><strong>student3</strong> / pass123</div>
                    <small>Pedro Garcia (College)</small>
                </div>
                <div>
                    <span class="demo-account-type student">Student</span>
                    <button type="button" class="use-btn" onclick="fillLogin('student3', 'pass123')">Use</button>
                </div>
            </div>
        </div>
        
        <div class="footer-links">
            <a href="database_setup.php">🔧 Database Setup</a>
            <a href="test_connection.php">🔍 Test Connection</a>
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
                input.style.borderColor = '#4CAF50';
                input.style.boxShadow = '0 0 10px rgba(76, 175, 80, 0.3)';
                setTimeout(() => {
                    input.style.borderColor = '#ddd';
                    input.style.boxShadow = 'none';
                }, 2000);
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
            
            // If there's an error, the page will reload and reset the button
            // For successful login, user will be redirected
        });
        
        // Add Enter key support for better UX
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const form = document.getElementById('loginForm');
                if (form.checkValidity()) {
                    form.submit();
                }
            }
        });
        
        // Add some visual effects
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.login-container');
            container.style.opacity = '0';
            container.style.transform = 'translateY(-30px)';
            
            setTimeout(() => {
                container.style.transition = 'all 0.6s ease';
                container.style.opacity = '1';
                container.style.transform = 'translateY(0)';
            }, 100);
        });
        
        // Focus on username input when page loads
        window.addEventListener('load', function() {
            document.getElementById('username').focus();
        });
    </script>
</body>
</html>
