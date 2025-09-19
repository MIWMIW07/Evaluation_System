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
:root {
            --maroon: #7B1F25;
            --dark-maroon: #58131e;
            --gold: #FFD700;
            --light-gold: #FFF8DC;
            --accent: #c3a86b;
            --white: #fff;
            --shadow: 0 4px 32px 0 rgba(123,31,37,0.09), 0 1.5px 4px 0 rgba(0,0,0,0.07);
        }
        html, body {
            height: 100%;
        }
        body {
            min-height: 100vh;
            background: radial-gradient(ellipse at 70% 40%, var(--gold) 0%, var(--maroon) 100%), linear-gradient(120deg, var(--light-gold) 0%, var(--gold) 100%);
            background-blend-mode: multiply;
            font-family: 'Inter', system-ui, sans-serif;
            color: var(--dark-maroon);
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-card {
            background: var(--white);
            border-radius: 22px;
            box-shadow: var(--shadow);
            padding: 2.6rem 2.2rem 2rem 2.2rem;
            max-width: 360px;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }
        .login-card::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: 0;
            background: linear-gradient(120deg,rgba(255,215,0,0.09),rgba(123,31,37,0.07));
            border-radius: 22px;
        }
        .login-logo {
            width: 54px;
            height: 54px;
            background: linear-gradient(135deg, var(--maroon), var(--gold));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.1rem;
            box-shadow: 0 2px 10px rgba(123,31,37,0.14);
            z-index: 2;
            font-size: 2.1rem;
            color: var(--white);
        }
        .login-title {
            font-size: 1.36rem;
            font-weight: 600;
            letter-spacing: -0.01em;
            color: var(--maroon);
            margin-bottom: 0.3rem;
            z-index: 2;
        }
        .login-desc {
            font-size: 1rem;
            color: var(--dark-maroon);
            opacity: 0.7;
            margin-bottom: 1.5rem;
            z-index: 2;
            text-align: center;
        }
        form {
            width: 100%;
            z-index: 2;
        }
        .form-group {
            margin-bottom: 1.2rem;
            position: relative;
        }
        .form-label {
            display: block;
            font-size: 0.97rem;
            font-weight: 500;
            margin-bottom: 0.37rem;
            color: var(--maroon);
        }
        .form-input {
            width: 100%;
            padding: 0.76rem 2.3rem 0.76rem 2.25rem;
            border-radius: 10px;
            border: 1.5px solid #e5d7b0;
            font-size: 1.02rem;
            background: var(--light-gold);
            color: var(--dark-maroon);
            transition: border 0.2s;
            outline: none;
        }
        .form-input:focus {
            border-color: var(--maroon);
            background: #fff8e1;
        }
        .form-icon {
            position: absolute;
            top: 50%;
            left: 0.9rem;
            transform: translateY(-50%);
            color: var(--maroon);
            font-size: 1.14rem;
            opacity: 0.79;
            pointer-events: none;
        }
        .login-btn {
            width: 100%;
            margin-top: 0.1rem;
            background: linear-gradient(90deg, var(--maroon), var(--gold) 110%);
            color: var(--white);
            border: none;
            border-radius: 10px;
            padding: 0.8rem 0;
            font-size: 1.08rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            box-shadow: 0 2px 14px 0 rgba(123,31,37,0.08);
            cursor: pointer;
            transition: background 0.18s, transform 0.15s;
        }
        .login-btn:hover, .login-btn:focus {
            background: linear-gradient(90deg, var(--gold) 0%, var(--maroon) 110%);
            color: var(--maroon);
            transform: translateY(-1.5px) scale(1.01);
        }
        .link-row {
            display: flex;
            justify-content: space-between;
            margin-top: 0.95rem;
            font-size: 0.98em;
            width: 100%;
            z-index: 2;
        }
        .form-link {
            color: var(--gold);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.16s;
        }
        .form-link:hover {
            color: var(--maroon);
            text-decoration: underline;
        }
        .signup-row {
            margin-top: 1.3rem;
            text-align: center;
            width: 100%;
            font-size: 1em;
            z-index: 2;
        }
        .signup-link {
            color: var(--maroon);
            text-decoration: none;
            font-weight: 600;
        }
        .signup-link:hover {
            color: var(--gold);
            text-decoration: underline;
        }
        .alert {
            width: 100%;
            padding: 0.85rem 1rem;
            border-radius: 10px;
            margin-bottom: 1.2rem;
            font-size: 0.98em;
            font-weight: 500;
            z-index: 2;
            text-align: center;
        }
        .alert-error {
            background: linear-gradient(90deg, #f3c2c2 0%, #ffe5e5 100%);
            color: #8e2929;
            border-left: 4px solid #b81e1e;
        }
        .alert-success {
            background: linear-gradient(90deg, #e0ffd5 0%, #fffbe5 100%);
            color: #25601e;
            border-left: 4px solid #74b81e;
        }
        @media (max-width: 600px) {
            body {
                padding: 1.5rem;
            }
            .login-card {
                padding: 1.5rem 0.7rem 1.1rem 0.7rem;
                max-width: 98vw;
            }
            .login-title {
                font-size: 1.15rem;
            }
        }
        /* Background illustration (SVG) */
        .bg-illustration {
            position: absolute;
            top: -25px; left: -40px;
            z-index: 0;
            width: 160px; height: 160px;
            pointer-events: none;
            opacity: 0.13;
            filter: blur(1px);
        }
        .bg-illustration-right {
            position: absolute;
            bottom: -38px; right: -45px;
            width: 130px; height: 120px;
            z-index: 0;
            opacity: 0.11;
            filter: blur(1.5px);
        }
    </style>
</head>
<body>
    <svg class="bg-illustration" viewBox="0 0 120 120" fill="none">
        <ellipse cx="60" cy="60" rx="59" ry="53" fill="#FFD700"/>
        <ellipse cx="40" cy="60" rx="24" ry="20" fill="#7B1F25"/>
    </svg>
    <svg class="bg-illustration-right" viewBox="0 0 120 120" fill="none">
        <ellipse cx="60" cy="60" rx="59" ry="53" fill="#7B1F25"/>
        <ellipse cx="80" cy="75" rx="24" ry="20" fill="#FFD700"/>
    </svg>
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
