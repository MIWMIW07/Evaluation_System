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
        $input_name = trim($_POST['username']); // This is actually the name input
        $password = trim($_POST['password']);
        
        // Validate input
        if (empty($input_name) || empty($password)) {
            throw new Exception("Name and password are required.");
        }
        
        // First, check if it's an admin login
        $admin_stmt = query("SELECT id, username, password, user_type, full_name FROM users WHERE username = ? AND user_type = 'admin'", [$input_name]);
        $admin_user = fetch_assoc($admin_stmt);
        
        if ($admin_user && password_verify($password, $admin_user['password'])) {
            // Admin login successful
            $_SESSION['user_id'] = $admin_user['id'];
            $_SESSION['username'] = $admin_user['username'];
            $_SESSION['user_type'] = 'admin';
            $_SESSION['full_name'] = $admin_user['full_name'];
            
            query("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?", [$admin_user['id']]);
            header('Location: admin.php');
            exit;
        }
        
        // If not admin, check students table by name
        $student_stmt = query("
            SELECT 
                s.id as student_table_id,
                s.full_name,
                s.last_name,
                s.first_name,
                s.middle_name,
                sec.section_code,
                sec.section_name,
                sec.program,
                sec.year_level
            FROM students s
            JOIN sections sec ON s.section_id = sec.id
            WHERE (UPPER(s.full_name) LIKE UPPER(?) 
                   OR UPPER(s.last_name) LIKE UPPER(?)
                   OR UPPER(CONCAT(s.first_name, ' ', s.last_name)) LIKE UPPER(?)
                   OR UPPER(CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name)) LIKE UPPER(?))
              AND s.is_active = true
            LIMIT 5", 
            ["%$input_name%", "%$input_name%", "%$input_name%", "%$input_name%"]
        );
        
        $student_results = fetch_all($student_stmt);
        
        if (!empty($student_results)) {
            // If multiple matches, take the first one or implement selection logic
            $student = $student_results[0];
            
            // For students, we'll use a simple password check (you can modify this)
            // For now, let's use 'student123' as default password or the section code
            $valid_passwords = ['student123', $password, strtolower($student['section_code'])];
            
            if (in_array($password, $valid_passwords)) {
                // Create or update user record
                $user_stmt = query("SELECT id FROM users WHERE student_table_id = ?", [$student['student_table_id']]);
                $existing_user = fetch_assoc($user_stmt);
                
                if (!$existing_user) {
                    // Create new user record
                    $username = strtolower(str_replace(' ', '_', $student['full_name']));
                    $hashed_password = password_hash('student123', PASSWORD_DEFAULT);
                    
                    query("INSERT INTO users (username, password, user_type, full_name, program, section, student_table_id) VALUES (?, ?, 'student', ?, ?, ?, ?)",
                          [$username, $hashed_password, $student['full_name'], $student['program'], $student['section_code'], $student['student_table_id']]);
                    
                    $user_id = get_last_insert_id('users_id_seq');
                } else {
                    $user_id = $existing_user['id'];
                    // Update user info
                    query("UPDATE users SET full_name = ?, program = ?, section = ?, last_login = CURRENT_TIMESTAMP WHERE id = ?",
                          [$student['full_name'], $student['program'], $student['section_code'], $user_id]);
                }
                
                // Set session variables
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = strtolower(str_replace(' ', '_', $student['full_name']));
                $_SESSION['user_type'] = 'student';
                $_SESSION['full_name'] = $student['full_name'];
                $_SESSION['program'] = $student['program'];
                $_SESSION['section'] = $student['section_code'];
                $_SESSION['section_name'] = $student['section_name'];
                $_SESSION['year_level'] = $student['year_level'];
                $_SESSION['student_table_id'] = $student['student_table_id'];
                
                header('Location: student_dashboard.php');
                exit;
            } else {
                throw new Exception("Invalid password. Try 'student123' or contact your administrator.");
            }
        } else {
            throw new Exception("Student name not found. Please check your spelling or contact your administrator.");
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --maroon: #800020;
            --maroon-light: #a0002a;
            --maroon-dark: #600018;
            --golderon: #DAA520;
            --light-gold: #F5E6A8;
            --cream: #FFFEF7;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, var(--maroon) 0%, var(--maroon-dark) 50%, var(--maroon) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            position: relative;
            overflow-x: hidden;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 25% 25%, rgba(218, 165, 32, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 75% 75%, rgba(245, 230, 168, 0.08) 0%, transparent 50%);
            pointer-events: none;
        }
        
        .login-container {
            width: 100%;
            max-width: 420px;
            background: var(--cream);
            border-radius: 24px;
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            position: relative;
            z-index: 1;
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--maroon) 0%, var(--maroon-light) 100%);
            padding: 2rem 2rem 3rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(218, 165, 32, 0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }
        
        .login-header h1 {
            color: var(--cream);
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }
        
        .institution-logo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: 3px solid var(--light-gold);
            margin: 0 auto 1rem;
            display: block;
            background: white;
            padding: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .login-header p {
            color: var(--light-gold);
            font-size: 0.875rem;
            font-weight: 400;
            opacity: 0.9;
            position: relative;
            z-index: 2;
        }
        
        .login-form {
            padding: 2rem;
        }
        
        .institution-badge {
            background: linear-gradient(135deg, var(--light-gold) 0%, var(--golderon) 100%);
            color: var(--maroon-dark);
            padding: 1rem;
            border-radius: 16px;
            text-align: center;
            margin-bottom: 2rem;
            font-size: 0.875rem;
            font-weight: 500;
            line-height: 1.4;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #fee 0%, #fdd 100%);
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        
        .alert-success {
            background: linear-gradient(135deg, var(--light-gold) 0%, rgba(218, 165, 32, 0.2) 100%);
            color: var(--maroon-dark);
            border: 1px solid var(--golderon);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }
        
        .form-input {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 1px solid var(--gray-300);
            border-radius: 12px;
            font-size: 1rem;
            background: var(--gray-50);
            transition: all 0.2s ease;
            outline: none;
        }
        
        .form-input:focus {
            border-color: var(--golderon);
            background: white;
            box-shadow: 0 0 0 3px rgba(218, 165, 32, 0.1);
        }
        
        .login-button {
            width: 100%;
            background: linear-gradient(135deg, var(--maroon) 0%, var(--maroon-light) 100%);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        
        .login-button:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
        }
        
        .login-button:active {
            transform: translateY(0);
        }
        
        .login-button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .button-content {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .loading-spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: none;
        }
        
        .demo-section {
            border-top: 1px solid var(--gray-200);
            padding-top: 1.5rem;
        }
        
        .demo-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-600);
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .demo-accounts {
            display: grid;
            gap: 0.75rem;
        }
        
        .demo-account {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            padding: 0.875rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s ease;
        }
        
        .demo-account:hover {
            background: white;
            border-color: var(--golderon);
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }
        
        .demo-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .demo-credentials {
            font-family: 'SF Mono', 'Monaco', 'Cascadia Code', monospace;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-800);
        }
        
        .demo-description {
            font-size: 0.75rem;
            color: var(--gray-500);
        }
        
        .demo-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .demo-badge {
            background: var(--maroon);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        
        .demo-badge.student {
            background: var(--golderon);
            color: var(--maroon-dark);
        }
        
        .use-button {
            background: var(--golderon);
            color: var(--maroon-dark);
            border: none;
            padding: 0.375rem 0.75rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .use-button:hover {
            background: var(--maroon);
            color: white;
            transform: translateY(-1px);
        }
        
        .footer-links {
            text-align: center;
            padding: 1rem 2rem 2rem;
            border-top: 1px solid var(--gray-200);
        }
        
        .footer-link {
            color: var(--gray-500);
            text-decoration: none;
            font-size: 0.875rem;
            margin: 0 1rem;
            transition: color 0.2s ease;
        }
        
        .footer-link:hover {
            color: var(--maroon);
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-container {
            animation: fadeInUp 0.6s ease-out;
        }
        
        @media (max-width: 480px) {
            .login-container {
                margin: 0.5rem;
                border-radius: 20px;
            }
            
            .login-header {
                padding: 1.5rem 1.5rem 2rem;
            }
            
            .login-header h1 {
                font-size: 1.5rem;
            }
            
            .login-form {
                padding: 1.5rem;
            }
            
            .demo-account {
                flex-direction: column;
                text-align: center;
                gap: 0.75rem;
            }
            
            .demo-actions {
                justify-content: center;
            }
            
            .footer-links {
                padding: 1rem 1.5rem 1.5rem;
            }
            
            .footer-link {
                display: block;
                margin: 0.5rem 0;
            }
        }
        
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Welcome To</h1>
            <p>Teacher Evaluation System</p>
        </div>
        
        <div class="login-form">
            <div class="institution-badge">
                <img src="logo.png" alt="PTISAT Logo" class="institution-logo" onerror="this.style.display='none'">
                Philippine Technological Institute of Science Arts and Trade, Inc.<br>
                <strong>GMA Branch</strong> • 2nd Semester 2024-2025
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <span>⚠</span>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <span>✓</span>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        class="form-input"
                        required 
                        autocomplete="username"
                        placeholder="Enter your username"
                        value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-input"
                        required 
                        autocomplete="current-password"
                        placeholder="Enter your password"
                    >
                </div>
                
                <button type="submit" class="login-button" id="loginBtn">
                    <div class="button-content">
                        <div class="loading-spinner" id="loadingSpinner"></div>
                        <span id="btnText">Sign In</span>
                    </div>
                </button>
            </form>
            
            <div class="demo-section">
                <div class="demo-title">Demo Accounts</div>
                <div class="demo-accounts">
                    <div class="demo-account">
                        <div class="demo-info">
                            <div class="demo-credentials">admin / admin123</div>
                            <div class="demo-description">System Administrator</div>
                        </div>
                        <div class="demo-actions">
                            <span class="demo-badge">Admin</span>
                            <button type="button" class="use-button" onclick="fillLogin('admin', 'admin123')">
                                Use
                            </button>
                        </div>
                    </div>
                    
                    <div class="demo-account">
                        <div class="demo-info">
                            <div class="demo-credentials">student1 / pass123</div>
                            <div class="demo-description">Juan Dela Cruz (SHS)</div>
                        </div>
                        <div class="demo-actions">
                            <span class="demo-badge student">Student</span>
                            <button type="button" class="use-button" onclick="fillLogin('student1', 'pass123')">
                                Use
                            </button>
                        </div>
                    </div>
                    
                    <div class="demo-account">
                        <div class="demo-info">
                            <div class="demo-credentials">student3 / pass123</div>
                            <div class="demo-description">Pedro Garcia (College)</div>
                        </div>
                        <div class="demo-actions">
                            <span class="demo-badge student">Student</span>
                            <button type="button" class="use-button" onclick="fillLogin('student3', 'pass123')">
                                Use
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer-links">
            <a href="database_setup.php" class="footer-link">Database Setup</a>
            <a href="test_connection.php" class="footer-link">Test Connection</a>
        </div>
    </div>

    <script>
        // Auto-fill login credentials with smooth animation
        function fillLogin(username, password) {
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            
            // Clear existing values
            usernameInput.value = '';
            passwordInput.value = '';
            
            // Animate typing effect
            let i = 0;
            const usernameInterval = setInterval(() => {
                if (i < username.length) {
                    usernameInput.value += username[i];
                    i++;
                } else {
                    clearInterval(usernameInterval);
                    // Start password typing
                    let j = 0;
                    const passwordInterval = setInterval(() => {
                        if (j < password.length) {
                            passwordInput.value += password[j];
                            j++;
                        } else {
                            clearInterval(passwordInterval);
                        }
                    }, 50);
                }
            }, 50);
            
            // Add focus effects
            usernameInput.focus();
            setTimeout(() => {
                passwordInput.focus();
            }, username.length * 50 + 100);
        }
        
        // Enhanced form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            const spinner = document.getElementById('loadingSpinner');
            const btnText = document.getElementById('btnText');
            
            // Show loading state
            btn.disabled = true;
            spinner.style.display = 'block';
            btnText.textContent = 'Signing in...';
            
            // Add pulsing effect to button
            btn.style.animation = 'pulse 1.5s ease-in-out infinite';
        });
        
        // Enhanced keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const form = document.getElementById('loginForm');
                const activeElement = document.activeElement;
                
                if (activeElement.tagName === 'INPUT') {
                    if (form.checkValidity()) {
                        form.submit();
                    }
                }
            }
            
            // Tab navigation enhancement
            if (e.key === 'Tab') {
                const focusableElements = document.querySelectorAll(
                    'input, button, .use-button, .footer-link'
                );
                const firstElement = focusableElements[0];
                const lastElement = focusableElements[focusableElements.length - 1];
                
                if (e.shiftKey) {
                    if (document.activeElement === firstElement) {
                        e.preventDefault();
                        lastElement.focus();
                    }
                } else {
                    if (document.activeElement === lastElement) {
                        e.preventDefault();
                        firstElement.focus();
                    }
                }
            }
        });
        
        // Focus management and visual enhancements
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-focus username input
            document.getElementById('username').focus();
            
            // Add interactive hover effects for demo accounts
            const demoAccounts = document.querySelectorAll('.demo-account');
            demoAccounts.forEach(account => {
                account.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                
                account.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
            
            // Add ripple effect to buttons
            const buttons = document.querySelectorAll('button');
            buttons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.width = ripple.style.height = size + 'px';
                    ripple.style.left = x + 'px';
                    ripple.style.top = y + 'px';
                    ripple.style.position = 'absolute';
                    ripple.style.borderRadius = '50%';
                    ripple.style.background = 'rgba(255, 255, 255, 0.3)';
                    ripple.style.pointerEvents = 'none';
                    ripple.style.animation = 'ripple 0.6s ease-out';
                    
                    this.style.position = 'relative';
                    this.style.overflow = 'hidden';
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });
        });
        
        // Add CSS for ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                0% {
                    transform: scale(0);
                    opacity: 1;
                }
                100% {
                    transform: scale(2);
                    opacity: 0;
                }
            }
            
            @keyframes pulse {
                0%, 100% {
                    box-shadow: var(--shadow-lg);
                }
                50% {
                    box-shadow: var(--shadow-xl);
                    transform: translateY(-1px);
                }
            }
        `;
        document.head.appendChild(style);

        document.addEventListener('DOMContentLoaded', function() {
    const usernameInput = document.getElementById('username');
    
    if (usernameInput) {
        // Add placeholder text
        usernameInput.placeholder = 'Enter your full name (e.g. Juan Dela Cruz)';
        
        // Add input formatting
        usernameInput.addEventListener('input', function(e) {
            // Convert to title case for better matching
            let value = e.target.value;
            // Basic title case conversion
            value = value.toLowerCase().replace(/\b\w/g, l => l.toUpperCase());
            if (value !== e.target.value) {
                e.target.value = value;
            }
        });
    }
});

// Update the demo accounts section in login.php
function fillLogin(username, password) {
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    
    usernameInput.value = username;
    passwordInput.value = password;
    usernameInput.focus();
}
    </script>
</body>
</html>
