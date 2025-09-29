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
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(-45deg, #800000, #500000, #9D0000, #5A0000);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow: auto;
            position: relative;
        }

        @keyframes gradientBG {
            0% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
            100% {
                background-position: 0% 50%;
            }
        }

        /* Floating elements for visual interest */
        .floating-elements {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
        }

        .floating-element {
            position: absolute;
            background: rgba(212, 175, 55, 0.1);
            border-radius: 50%;
            animation: float 20s infinite linear;
        }

        .floating-element:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 20%;
            left: 10%;
            animation-duration: 25s;
        }

        .floating-element:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 60%;
            left: 80%;
            animation-duration: 30s;
        }

        .floating-element:nth-child(3) {
            width: 60px;
            height: 60px;
            top: 80%;
            left: 20%;
            animation-duration: 20s;
        }

        .floating-element:nth-child(4) {
            width: 100px;
            height: 100px;
            top: 10%;
            left: 70%;
            animation-duration: 35s;
        }

        @keyframes float {
            0% {
                transform: translateY(0) rotate(0deg);
            }
            100% {
                transform: translateY(-1000px) rotate(720deg);
            }
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 480px;
            position: relative;
            z-index: 1;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(212, 175, 55, 0.3);
            transition: transform 0.3s ease;
            display: flex;
            flex-direction: column;
            margin: 20px 0;
        }

        .login-container:hover {
            transform: translateY(-5px);
        }

        .header-container {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #D4AF37;
            position: relative;
        }

        .header-container::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 25%;
            width: 50%;
            height: 2px;
            background: linear-gradient(90deg, transparent, #D4AF37, transparent);
        }

        .school-name-box {
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(212, 175, 55, 0.15);
            padding: 12px;
            border-radius: 12px;
            border-left: 4px solid #D4AF37;
            margin-top: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .school-name {
            font-family: cursive;
            background: linear-gradient(135deg, #D4AF37 0%, #800000 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 600;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .logo {
            height: 50px;
            width: auto;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }

        h1 {
            color: #800000;
            font-size: 28px;
            line-height: 1.3;
            margin-bottom: 5px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            letter-spacing: 1px;
        }

        .system-subtitle {
            color: #D4AF37;
            font-size: 20px;
            font-weight: 600;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            letter-spacing: 1px;
        }

        /* Enhanced Form Styles */
        .form-container {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 8px 25px rgba(128, 0, 0, 0.1);
            border: 1px solid rgba(212, 175, 55, 0.2);
            margin-bottom: 15px;
            flex-grow: 1;
            /* Removed scroll properties */
            overflow: visible;
        }

        .form-header {
            text-align: center;
            margin-bottom: 20px;
            color: #800000;
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .form-header i {
            color: #D4AF37;
            font-size: 20px;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-label {
            display: block;
            color: #800000;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color 0.3s ease;
        }

        .form-label i {
            color: #D4AF37;
            font-size: 16px;
            width: 20px;
        }

        .input-container {
            position: relative;
        }

        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 14px 15px 14px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
            background-color: rgba(255, 255, 255, 0.9);
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            color: #333;
        }

        input[type="text"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: #D4AF37;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2);
            background-color: white;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #800000;
            font-size: 17px;
            transition: color 0.3s;
        }

        input:focus + .input-icon {
            color: #D4AF37;
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #800000;
            cursor: pointer;
            font-size: 17px;
            transition: color 0.3s;
        }

        .password-toggle:hover {
            color: #D4AF37;
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            font-size: 14px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #800000;
            cursor: pointer;
        }

        .remember-me input {
            accent-color: #D4AF37;
        }

        .btn {
            width: 100%;
            background: linear-gradient(135deg, #D4AF37 0%, #800000 100%);
            color: white;
            padding: 15px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.4s ease;
            margin-top: 10px;
            box-shadow: 0 6px 15px rgba(128, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
            z-index: 1;
            letter-spacing: 1px;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #800000 0%, #D4AF37 100%);
            z-index: -1;
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(128, 0, 0, 0.4);
        }

        .btn:hover::before {
            opacity: 1;
        }

        .btn:active {
            transform: translateY(-1px);
            box-shadow: 0 5px 10px rgba(128, 0, 0, 0.4);
        }

        .btn i {
            margin-left: 8px;
            transition: transform 0.3s;
        }

        .btn:hover i {
            transform: translateX(5px);
        }

        .alert {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 8px;
            font-weight: 500;
        }

        .alert-success {
            background-color: rgba(212, 175, 55, 0.2);
            color: #800000;
            border: 1px solid #D4AF37;
        }

        .alert-error {
            background-color: rgba(128, 0, 0, 0.1);
            color: #800000;
            border: 1px solid #800000;
        }

        .footer {
            text-align: center;
            margin-top: 15px;
            color: #666;
            font-size: 12px;
            border-top: 1px solid rgba(212, 175, 55, 0.3);
            padding-top: 15px;
        }

        .copyright {
            color: #800000;
            font-weight: 500;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 20px 15px;
            }
            
            h1 {
                font-size: 24px;
            }
            
            .system-subtitle {
                font-size: 18px;
            }
            
            .school-name {
                font-size: 13px;
            }
            
            .form-container {
                padding: 15px;
            }
            
            .form-header {
                font-size: 16px;
            }
            
            .form-options {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="floating-elements">
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
    </div>
    
    <div class="login-container" data-aos="fade-up" data-aos-duration="1000">
        <div class="header-container" data-aos="fade-down" data-aos-delay="300">
            <h1>Teacher Evaluation</h1>
            <div class="system-subtitle">System</div>
            <div class="school-name-box" data-aos="zoom-in" data-aos-delay="500">
                <div class="school-name">
                    <img src="logo.png" alt="School Logo" class="logo" onerror="this.style.display='none'">
                    Philippine Technological Institute of Science Arts and Trade, Inc.
                </div>
            </div>
        </div>
        
        <div class="form-container" data-aos="fade-in" data-aos-delay="600">
            <div class="form-header">
                <i class="fas fa-sign-in-alt"></i>
                <span>Login to Your Account</span>
            </div>
            
            <form method="POST" action="login.php">
                <div class="form-group" data-aos="fade-right" data-aos-delay="700">
                    <label for="username" class="form-label">
                        <i class="fas fa-user"></i>
                        Username
                    </label>
                    <div class="input-container">
                        <input type="text" id="username" name="username" placeholder="Enter your username" required value="TOQUECHRISTOPHERGLEN">
                        <i class="fas fa-user input-icon"></i>
                    </div>
                </div>
                
                <div class="form-group" data-aos="fade-right" data-aos-delay="900">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock"></i>
                        Password
                    </label>
                    <div class="input-container">
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        <i class="fas fa-lock input-icon"></i>
                        <i class="fas fa-eye password-toggle" id="passwordToggle"></i>
                    </div>
                </div>
                
                <button type="submit" class="btn">
                    Sign In <i class="fas fa-arrow-right"></i>
                </button>
            </form>
        </div>
        
        <div class="footer">
            <p class="copyright">&copy; 2025 Philippine Technological Institute of Science Arts and Trade, Inc.</p>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            once: true,
            duration: 1000,
            easing: 'ease-out-cubic'
        });

        // Toggle password visibility
        document.getElementById('passwordToggle').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle eye icon
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>
