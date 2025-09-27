<?php
session_start();
require_once __DIR__ . '/includes/db_connection.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // ✅ Successful login
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['user_type'] = $user['user_type'];

                // Update last login timestamp
                $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
                    ->execute([$user['id']]);

                // Log success
                logActivity("login", "User '{$username}' logged in successfully", "success", $user['id']);

                header("Location: " . ($user['user_type'] === 'admin' ? 'admin.php' : 'student_dashboard.php'));
                exit;
            } else {
                // ❌ Failed login
                logActivity("login", "Failed login attempt for username '{$username}'", "error", null);
                $error = "Invalid username or password.";
            }
        } catch (PDOException $e) {
            logActivity("login", "Database error during login: " . $e->getMessage(), "error", null);
            $error = "An error occurred. Please try again later.";
        }
    } else {
        $error = "Please enter both username and password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Philtech GMA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #4b0d0e 0%, #3a0a0b 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .login-container {
            display: flex;
            max-width: 900px;
            width: 100%;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
        }
        
        .login-left {
            flex: 1;
            background: linear-gradient(135deg, #800020 0%, #5a0018 100%);
            color: white;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-right {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .logo-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #D4AF37 0%, #FFD700 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 24px;
            color: #800020;
            font-weight: bold;
        }
        
        .logo-text {
            font-size: 24px;
            font-weight: bold;
            color: #FFD700;
        }
        
        .welcome-text {
            margin-bottom: 30px;
        }
        
        .welcome-text h2 {
            font-size: 28px;
            margin-bottom: 10px;
            color: #FFD700;
        }
        
        .features {
            list-style: none;
            margin-top: 30px;
        }
        
        .features li {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .features i {
            color: #FFD700;
            margin-right: 10px;
            font-size: 18px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h2 {
            color: #800020;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: #666;
        }
        
        .input-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .input-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        
        .input-group input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .input-group input:focus {
            border-color: #D4AF37;
            outline: none;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2);
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 40px;
            color: #800020;
            font-size: 18px;
        }
        
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .remember {
            display: flex;
            align-items: center;
        }
        
        .remember input {
            margin-right: 5px;
        }
        
        .forgot {
            color: #800020;
            text-decoration: none;
        }
        
        .forgot:hover {
            text-decoration: underline;
        }
        
        .login-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #D4AF37 0%, #FFD700 100%);
            border: none;
            border-radius: 8px;
            color: #800020;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 20px;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.4);
        }
        
        .demo-accounts {
            background: #f9f9f9;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .demo-accounts h4 {
            margin-bottom: 10px;
            color: #800020;
            font-size: 16px;
        }
        
        .demo-account {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .demo-account:last-child {
            margin-bottom: 0;
            border-bottom: none;
            padding-bottom: 0;
        }
        
        .demo-info {
            font-size: 14px;
        }
        
        .demo-type {
            background: #800020;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .use-btn {
            background: #D4AF37;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .error-message {
            background: #ffe6e6;
            color: #cc0000;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #cc0000;
            display: none;
        }
        
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
            }
            
            .login-left {
                padding: 30px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-left">
            <div class="logo">
                <div class="logo-icon">P</div>
                <div class="logo-text">Philtech GMA</div>
            </div>
            
            <div class="welcome-text">
                <h2>Welcome Back!</h2>
                <p>Sign in to access the Teacher Evaluation System and continue making a difference in education.</p>
            </div>
        </div>
        
        <div class="login-right">
            <div class="login-header">
                <h2>Sign In</h2>
                <p>Enter your credentials to access your account</p>
            </div>
            
            <div class="error-message" id="errorMessage">
                Invalid username or password. Please try again.
            </div>
            
            <form id="loginForm">
                <div class="input-group">
                    <label for="username">Username</label>
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" id="username" name="username" placeholder="Enter your username" required>
                </div>
                
                <div class="input-group">
                    <label for="password">Password</label>
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>
                
                <div class="remember-forgot">
                    <div class="remember">
                        <input type="checkbox" id="remember">
                        <label for="remember">Remember me</label>
                    </div>
                    <a href="#" class="forgot">Forgot password?</a>
                </div>
                
                <button type="submit" class="login-btn">Sign In</button>
            </form>
    </div>

    <script>
        function fillCredentials(username, password) {
            document.getElementById('username').value = username;
            document.getElementById('password').value = password;
            
            // Visual feedback
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.style.borderColor = '#D4AF37';
                setTimeout(() => {
                    input.style.borderColor = '#e0e0e0';
                }, 1000);
            });
        }
        
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            
            const isValid = validCredentials.some(cred => 
                cred.user === username && cred.pass === password
            );
            
            if (isValid) {
                // Simulate successful login
                document.getElementById('errorMessage').style.display = 'none';
                alert('Login successful! Redirecting to dashboard...');
                // In a real application, you would redirect to the dashboard
                // window.location.href = 'dashboard.php';
            } else {
                document.getElementById('errorMessage').style.display = 'block';
            }
        });
        
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.querySelector('.input-icon').style.color = '#D4AF37';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.querySelector('.input-icon').style.color = '#800020';
                });
            });
        });
    </script>
</body>
</html>
