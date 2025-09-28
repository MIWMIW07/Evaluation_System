<?php
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_type'] === 'admin') {
        header('Location: admin.php');
    } elseif ($_SESSION['user_type'] === 'student') {
        header('Location: student_dashboard.php');
    } else {
        header('Location: login.php');
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Evaluation System - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #800000 0%, #500000 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        
        /* Animated background elements */
        .bg-shapes {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
        }
        
        .shape {
            position: absolute;
            border-radius: 50%;
            opacity: 0.1;
            animation: float 15s infinite linear;
        }
        
        .shape:nth-child(1) {
            width: 80px;
            height: 80px;
            background: #D4AF37;
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .shape:nth-child(2) {
            width: 120px;
            height: 120px;
            background: #800000;
            top: 60%;
            left: 80%;
            animation-delay: -5s;
        }
        
        .shape:nth-child(3) {
            width: 60px;
            height: 60px;
            background: #D4AF37;
            top: 80%;
            left: 20%;
            animation-delay: -10s;
        }
        
        .shape:nth-child(4) {
            width: 100px;
            height: 100px;
            background: #800000;
            top: 30%;
            left: 70%;
            animation-delay: -7s;
        }
        
        @keyframes float {
            0% {
                transform: translateY(0) rotate(0deg);
            }
            50% {
                transform: translateY(-20px) rotate(180deg);
            }
            100% {
                transform: translateY(0) rotate(360deg);
            }
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 450px;
            position: relative;
            z-index: 1;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .login-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .logo img {
            height: 90px;
            width: auto;
            filter: drop-shadow(0 5px 10px rgba(0,0,0,0.2));
        }
        
        .logo-text {
            margin-top: 10px;
            color: #800000;
            font-weight: 700;
            font-size: 1.4rem;
            letter-spacing: 1px;
        }
        
        h1 {
            color: #800000;
            text-align: center;
            margin-bottom: 30px;
            font-size: 28px;
            position: relative;
            padding-bottom: 15px;
        }
        
        h1:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: linear-gradient(to right, #800000, #D4AF37, #800000);
            border-radius: 3px;
        }
        
        .welcome-text {
            text-align: center;
            color: #666;
            margin-bottom: 25px;
            font-size: 16px;
            line-height: 1.5;
        }
        
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        label {
            display: block;
            color: #800000;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 15px;
        }
        
        .input-with-icon {
            position: relative;
        }
        
        .input-with-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #800000;
            font-size: 18px;
        }
        
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 15px 15px 15px 50px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
            background-color: #f9f9f9;
        }
        
        input[type="text"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: #800000;
            background-color: #fff;
            box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.1);
        }
        
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            font-size: 14px;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
        }
        
        .forgot-password {
            color: #800000;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .forgot-password:hover {
            color: #D4AF37;
            text-decoration: underline;
        }
        
        .btn {
            width: 100%;
            background: linear-gradient(135deg, #800000 0%, #A52A2A 100%);
            color: white;
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            box-shadow: 0 5px 15px rgba(128, 0, 0, 0.3);
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #A52A2A 0%, #800000 100%);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(128, 0, 0, 0.4);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 10px;
            font-weight: 500;
            position: relative;
            padding-left: 50px;
        }
        
        .alert i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .system-features {
            display: flex;
            justify-content: space-between;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #eee;
        }
        
        .feature {
            text-align: center;
            flex: 1;
            padding: 0 10px;
        }
        
        .feature i {
            font-size: 24px;
            color: #800000;
            margin-bottom: 10px;
        }
        
        .feature-text {
            font-size: 13px;
            color: #666;
        }
        
        /* Responsive adjustments */
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }
            
            .remember-forgot {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .system-features {
                flex-direction: column;
                gap: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="bg-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>
    
    <div class="login-container">
        <div class="logo">
            <img src="logo.png" alt="School Logo" onerror="this.style.display='none'">
            <div class="logo-text">Philippine Technological Institute</div>
        </div>
        
        <h1>Teacher Evaluation System</h1>
        
        <div class="welcome-text">
            Welcome back! Please sign in to access your account and evaluate your teachers.
        </div>
        
        <?php if (isset($_SESSION['logout_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php 
                echo htmlspecialchars($_SESSION['logout_message']); 
                unset($_SESSION['logout_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php 
                echo htmlspecialchars($_SESSION['error']); 
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Username:</label>
                <div class="input-with-icon">
                    <i class="fas fa-user"></i>
                    <input type="text" id="username" name="username" placeholder="Enter your username" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <div class="input-with-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>
            </div>
            
            <div class="remember-forgot">
                <div class="remember-me">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Remember me</label>
                </div>
                <a href="#" class="forgot-password">Forgot Password?</a>
            </div>
            
            <button type="submit" class="btn">
                <i class="fas fa-sign-in-alt"></i>
                Login
            </button>
        </form>
        
        <div class="system-features">
            <div class="feature">
                <i class="fas fa-shield-alt"></i>
                <div class="feature-text">Secure Login</div>
            </div>
            <div class="feature">
                <i class="fas fa-chart-bar"></i>
                <div class="feature-text">Analytics</div>
            </div>
            <div class="feature">
                <i class="fas fa-clock"></i>
                <div class="feature-text">Real-time</div>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; 2025 Philippine Technological Institute</p>
            <p>of Science Arts and Trade, Inc.</p>
        </div>
    </div>

    <script>
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('input[type="text"], input[type="password"]');
            
            inputs.forEach(input => {
                // Add focus effect
                input.addEventListener('focus', function() {
                    this.parentElement.parentElement.style.transform = 'scale(1.02)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.parentElement.style.transform = 'scale(1)';
                });
            });
            
            // Add a simple password visibility toggle (for demonstration)
            const passwordInput = document.getElementById('password');
            const lockIcon = document.querySelector('.fa-lock');
            
            lockIcon.addEventListener('click', function() {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    lockIcon.className = 'fas fa-unlock';
                } else {
                    passwordInput.type = 'password';
                    lockIcon.className = 'fas fa-lock';
                }
            });
        });
    </script>
</body>
</html>
