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
            background: linear-gradient(135deg, #8B0000 0%, #4A0000 50%, #2B0000 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            top: -200px;
            right: -200px;
            animation: float 20s ease-in-out infinite;
        }

        body::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            bottom: -150px;
            left: -150px;
            animation: float 25s ease-in-out infinite reverse;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(50px, 50px) rotate(180deg); }
        }

        .login-container {
            background: #F5F5F0;
            padding: 50px 45px;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 480px;
            position: relative;
            z-index: 1;
        }

        .header-container {
            text-align: center;
            margin-bottom: 35px;
            padding-bottom: 25px;
            position: relative;
        }

        .header-container::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: linear-gradient(90deg, transparent, #D4AF37, transparent);
            border-radius: 2px;
        }

        .school-name-box {
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(212, 175, 55, 0.08) 0%, rgba(139, 0, 0, 0.05) 100%);
            padding: 18px 20px;
            border-radius: 12px;
            border: 1px solid rgba(212, 175, 55, 0.3);
            margin-top: 20px;
            position: relative;
            overflow: hidden;
        }

        .school-name-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(212, 175, 55, 0.15), transparent);
            transition: left 0.8s ease;
        }

        .school-name-box:hover::before {
            left: 100%;
        }

        .school-name {
            font-family: 'Georgia', serif;
            background: linear-gradient(135deg, #D4AF37 0%, #B8941F 50%, #8B0000 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 700;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            line-height: 1.4;
        }

        .logo {
            height: 70px;
            width: auto;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
            transition: transform 0.3s ease;
        }

        .logo:hover {
            transform: scale(1.05);
        }

        h1 {
            background: linear-gradient(135deg, #8B0000 0%, #5A0000 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 32px;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .system-subtitle {
            background: linear-gradient(135deg, #D4AF37 0%, #B8941F 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 22px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            background: linear-gradient(135deg, #8B0000 0%, #5A0000 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.3px;
        }

        .input-container {
            position: relative;
        }

        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 14px 15px 14px 48px;
            border: 2px solid rgba(212, 175, 55, 0.3);
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: linear-gradient(135deg, rgba(212, 175, 55, 0.03) 0%, rgba(255, 255, 255, 1) 100%);
            color: #2B0000;
        }

        input[type="text"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: #D4AF37;
            box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.15),
                        0 8px 16px rgba(139, 0, 0, 0.1);
            background: white;
            transform: translateY(-2px);
        }

        input::placeholder {
            color: rgba(139, 0, 0, 0.4);
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: linear-gradient(135deg, #D4AF37 0%, #8B0000 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 18px;
            transition: transform 0.3s ease;
        }

        .input-container:focus-within .input-icon {
            transform: translateY(-50%) scale(1.1);
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: linear-gradient(135deg, #D4AF37 0%, #8B0000 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            cursor: pointer;
            font-size: 18px;
            transition: transform 0.2s ease;
        }

        .password-toggle:hover {
            transform: translateY(-50%) scale(1.15);
        }

        .btn {
            width: 100%;
            background: linear-gradient(135deg, #D4AF37 0%, #B8941F 50%, #8B0000 100%);
            color: white;
            padding: 16px;
            border: none;
            border-radius: 12px;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s ease;
            margin-top: 15px;
            box-shadow: 0 6px 20px rgba(212, 175, 55, 0.4),
                        0 0 0 1px rgba(212, 175, 55, 0.5);
            position: relative;
            overflow: hidden;
            z-index: 1;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.3) 0%, transparent 70%);
            transform: translate(-50%, -50%);
            transition: width 0.6s ease, height 0.6s ease;
            z-index: -1;
        }

        .btn::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #8B0000 0%, #5A0000 50%, #D4AF37 100%);
            opacity: 0;
            z-index: -2;
            transition: opacity 0.6s ease;
        }

        .btn:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 12px 35px rgba(212, 175, 55, 0.6),
                        0 0 40px rgba(212, 175, 55, 0.3);
        }

        .btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn:hover::after {
            opacity: 1;
        }

        .btn:active {
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(212, 175, 55, 0.4);
        }

        .btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .footer {
            text-align: center;
            margin-top: 35px;
            color: #666;
            font-size: 12px;
            border-top: 1px solid rgba(212, 175, 55, 0.2);
            padding-top: 25px;
            line-height: 1.6;
        }

        .footer p {
            background: linear-gradient(135deg, #8B0000 0%, #D4AF37 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 500;
        }

        @keyframes shimmer {
            0% { background-position: -1000px 0; }
            100% { background-position: 1000px 0; }
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 40px 30px;
            }
            
            h1 {
                font-size: 28px;
            }
            
            .system-subtitle {
                font-size: 20px;
            }
            
            .school-name {
                font-size: 13px;
            }

            .logo {
                height: 60px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container" data-aos="fade-up" data-aos-duration="800">
        <div class="header-container" data-aos="fade-down" data-aos-delay="200">
            <h1>Teacher Evaluation</h1>
            <div class="system-subtitle">System</div>
            <div class="school-name-box" data-aos="zoom-in" data-aos-delay="400">
                <div class="school-name">
                    <img src="logo.png" alt="School Logo" class="logo" onerror="this.style.display='none'">
                    Philippine Technological Institute of Science Arts and Trade, Inc.
                </div>
            </div>
        </div>
        
        <form method="POST" action="login.php">
            <div class="form-group" data-aos="fade-right" data-aos-delay="600">
                <label for="username">Username</label>
                <div class="input-container">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" id="username" name="username" placeholder="Enter your username" required>
                </div>
            </div>
            
            <div class="form-group" data-aos="fade-right" data-aos-delay="800">
                <label for="password">Password</label>
                <div class="input-container">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    <i class="fas fa-eye password-toggle" id="passwordToggle"></i>
                </div>
            </div>
            
            <button type="submit" class="btn">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>
        
        <div class="footer">
            <p>&copy; 2025 Philippine Technological Institute of Science Arts and Trade, Inc.</p>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            once: true,
            duration: 800,
            easing: 'ease-out-cubic'
        });

        document.querySelector("form").addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.querySelector(".btn");
            const form = this;
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';

             setTimeout(function() {
                form.submit();
            }, 1000);
        });

        document.getElementById('passwordToggle').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>
