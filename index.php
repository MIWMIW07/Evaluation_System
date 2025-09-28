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
            background: linear-gradient(135deg, #800000 0%, #500000 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 450px;
            position: relative;
        }
        
        .header-container {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #D4AF37;
        }
        
        .school-name-box {
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(212, 175, 55, 0.2);
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #D4AF37;
            margin-top: 15px;
        }
        
        .school-name {
            color: #800000;
            font-weight: 600;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .logo {
            height: 60px;
            width: auto;
        }
        
        h1 {
            color: #800000;
            font-size: 28px;
            line-height: 1.3;
            margin-bottom: 5px;
        }
        
        .system-subtitle {
            color: #800000;
            font-size: 20px;
            font-weight: 500;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            color: #800000;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
        }
        
        .input-container {
            position: relative;
        }
        
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #D4AF37;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
            background-color: rgba(212, 175, 55, 0.05);
        }
        
        input[type="text"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: #800000;
            box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.1);
            background-color: white;
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #800000;
            font-size: 18px;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #800000;
            cursor: pointer;
            font-size: 18px;
        }
        
        .btn {
            width: 100%;
            background: linear-gradient(135deg, #D4AF37 0%, #800000 100%);
            color: white;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.4s ease;
            margin-top: 10px;
            box-shadow: 0 4px 15px rgba(128, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
            z-index: 1;
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
            box-shadow: 0 8px 20px rgba(128, 0, 0, 0.4);
        }
        
        .btn:hover::before {
            opacity: 1;
        }
        
        .btn:active {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(128, 0, 0, 0.4);
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            opacity: 1;
        }
        
        .alert.hidden {
            opacity: 0;
            height: 0;
            padding: 0;
            margin: 0;
            overflow: hidden;
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
            margin-top: 30px;
            color: #666;
            font-size: 12px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        
        /* Loading Spinner Styles */
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(255, 255, 255, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 15px;
            z-index: 10;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .loading-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid rgba(212, 175, 55, 0.3);
            border-radius: 50%;
            border-top-color: #D4AF37;
            animation: spin 1s ease-in-out infinite;
        }
        
        .loading-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        
        .loading-text {
            margin-top: 15px;
            color: #800000;
            font-weight: 600;
            text-align: center;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .form-container {
            position: relative;
        }
        
        .form-loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(255, 255, 255, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 10px;
            z-index: 5;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .form-loading-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }
            
            h1 {
                font-size: 24px;
            }
            
            .system-subtitle {
                font-size: 18px;
            }
            
            .school-name {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="loading-overlay" id="loadingOverlay">
            <div class="loading-content">
                <div class="spinner"></div>
                <div class="loading-text" id="loadingText">Processing...</div>
            </div>
        </div>
        
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
        
        <div class="form-container">
            <div class="form-loading-overlay" id="formLoadingOverlay">
                <div class="loading-content">
                    <div class="spinner"></div>
                    <div class="loading-text">Processing...</div>
                </div>
            </div>
            
            <form method="POST" id="loginForm">
                <div class="form-group" data-aos="fade-right" data-aos-delay="800">
                    <label for="username">Username</label>
                    <div class="input-container">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" id="username" name="username" placeholder="Enter your username" required>
                    </div>
                </div>
                
                <div class="form-group" data-aos="fade-right" data-aos-delay="1000">
                    <label for="password">Password</label>
                    <div class="input-container">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        <i class="fas fa-eye password-toggle" id="passwordToggle"></i>
                    </div>
                </div>
                
                <button type="submit" class="btn" id="loginButton">Login</button>
            </form>
        </div>
        
        <div class="footer">
            <p>&copy; 2025 Philippine Technological Institute of Science Arts and Trade, Inc.</p>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            once: true,
            duration: 800,
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

        // Form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const formLoadingOverlay = document.getElementById('formLoadingOverlay');
            const alertBox = document.getElementById('alertBox');
            const loginButton = document.getElementById('loginButton');
            
            // Show loading state in the form area
            formLoadingOverlay.classList.add('active');
            loginButton.disabled = true;
            
            // Simulate login process with 3-second delay
            setTimeout(function() {
                // Hide loading state
                formLoadingOverlay.classList.remove('active');
                loginButton.disabled = false;
                
                // Check credentials (demo logic)
                if (username === "admin" && password === "password") {
                    // Success case
                    alertBox.className = "alert alert-success";
                    alertBox.textContent = "Login successful! Redirecting to dashboard...";
                    alertBox.classList.remove('hidden');
                    
                    // In a real app, you would redirect to the dashboard here
                    // window.location.href = "dashboard.html";
                } else {
                    // Error case
                    alertBox.className = "alert alert-error";
                    alertBox.textContent = "Invalid username or password. Please try again.";
                    alertBox.classList.remove('hidden');
                }
            }, 3000);
        });
    </script>
</body>
</html>
