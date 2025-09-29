<?php
// Check if session is not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_type'] === 'admin') {
        header('Location: admin.php');
        exit();
    } elseif ($_SESSION['user_type'] === 'student') {
        header('Location: student_dashboard.php');
        exit();
    } else {
        // If user_type is invalid, clear session and redirect to login
        session_destroy();
        header('Location: login.php');
        exit();
    }
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
    font-family: cursive;
    background: linear-gradient(135deg, #D4AF37 0%, #800000 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
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
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
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

.btn:hover:not(.btn-loading) {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(128, 0, 0, 0.4);
}

.btn:hover:not(.btn-loading)::before {
    opacity: 1;
}

.btn:active:not(.btn-loading) {
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(128, 0, 0, 0.4);
}

.btn-loading {
    cursor: not-allowed;
    transform: none;
    box-shadow: 0 4px 15px rgba(128, 0, 0, 0.3);
}

.btn-loading::before {
    opacity: 1;
}

.loading-spinner {
    width: 18px;
    height: 18px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top: 2px solid white;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
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
    margin-top: 30px;
    color: #666;
    font-size: 12px;
    border-top: 1px solid #eee;
    padding-top: 20px;
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
        
        <!-- Display error messages if any -->
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error" data-aos="fade-down">
                <?php 
                $errors = [
                    'invalid' => 'Invalid username or password.',
                    'empty' => 'Please enter both username and password.',
                    'inactive' => 'Your account is inactive. Please contact administrator.'
                ];
                echo $errors[$_GET['error']] ?? 'An error occurred. Please try again.';
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success" data-aos="fade-down">
                <?php 
                $success = [
                    'logout' => 'You have been successfully logged out.',
                    'registered' => 'Registration successful. Please login.'
                ];
                echo $success[$_GET['success']] ?? 'Operation completed successfully.';
                ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="login_process.php" id="loginForm">
            <div class="form-group" data-aos="fade-right" data-aos-delay="800">
                <label for="username">Username</label>
                <div class="input-container">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" id="username" name="username" placeholder="Enter your username" required 
                           value="<?php echo isset($_GET['username']) ? htmlspecialchars($_GET['username']) : ''; ?>">
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
            
            <button type="submit" class="btn" id="loginButton" data-aos="zoom-in" data-aos-delay="1200">
                <span id="buttonText">Login</span>
                <div class="loading-spinner" id="loadingSpinner" style="display: none;"></div>
            </button>
        </form>
        
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

        // Login form submission with loading animation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const loginButton = document.getElementById('loginButton');
            const buttonText = document.getElementById('buttonText');
            const loadingSpinner = document.getElementById('loadingSpinner');
            
            // Basic validation
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if (!username || !password) {
                alert('Please enter both username and password.');
                e.preventDefault();
                return;
            }
            
            // Show loading state
            loginButton.classList.add('btn-loading');
            buttonText.textContent = 'Processing...';
            loadingSpinner.style.display = 'block';
            loginButton.disabled = true;
            
            // Allow form to submit normally - remove the timeout if you want actual form submission
            // For demo purposes, we'll keep the 3-second delay but you should remove this in production
            setTimeout(function() {
                // Form will submit normally after 3 seconds
            }, 3000);
        });
    </script>
</body>
</html>
