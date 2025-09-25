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

$show_preloader = false;
$redirect_url = "";

// If user is already logged in, show preloader and redirect
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_type'] === 'admin') {
        $redirect_url = 'admin.php';
    } else {
        $redirect_url = 'student_dashboard.php';
    }
    $show_preloader = true;
}

// Include database connection
require_once 'includes/db_connection.php';

$error = '';
$success = isset($success) ? $success : '';

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && !$show_preloader) {
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
            
            // Set redirect url and show preloader
            if ($user['user_type'] === 'admin') {
                $redirect_url = 'admin.php';
            } else {
                $redirect_url = 'student_dashboard.php';
            }
            $show_preloader = true;
        } else {
            throw new Exception("Invalid username or password.");
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
} 

// If login successful (existing session or just logged in), show preloader and exit
if ($show_preloader && $redirect_url) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Redirecting...</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body, html {
                width: 100%;
                height: 100%;
                background: linear-gradient(135deg, #4A0012 0%, #800020 25%, #DAA520 100%);
                overflow: hidden;
                display: flex;
                justify-content: center;
                align-items: center;
            }
            .preloader-overlay {
                position: fixed;
                top: 0; left: 0;
                width: 100vw;
                height: 100vh;
                background: linear-gradient(135deg, maroon, darkmaroon, goldenrod);
                z-index: 99999;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-direction: column;
                transition: opacity 0.4s;
            }
             /* Circular border */
    .circle-border {
      width: 170px;
      height: 170px;
      border-radius: 50%;
      border: 6px solid goldenrod;
      border-top: 6px solid gold;
      border-bottom: 6px solid maroon;
      border-left: 6px solid lightgoldenrodyellow;
      border-right: 6px solid darkred;
      display: flex;
      justify-content: center;
      align-items: center;
      animation: spinBorder 3s linear infinite, glow 2s ease-in-out infinite alternate;
      box-shadow: 0 0 20px goldenrod, 0 0 40px maroon;
    }

    /* Rotating logo */
    .circle-border img {
      width: 100px;
      height: 100px;
      animation: spinLogo 5s linear infinite;

    }

    /* Animations */
    @keyframes spinBorder {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }

    @keyframes spinLogo {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(-360deg); }
    }

    @keyframes glow {
      0% { box-shadow: 0 0 10px goldenrod, 0 0 20px maroon; }
      100% { box-shadow: 0 0 30px gold, 0 0 60px darkred; }
    }
  </style>
        </style>
    </head>
    <body>
        <div id="preloader">
    <div class="circle-border">
      <img src="logo.png" alt="Logo">
    </div>
  </div>
        <script>
setTimeout(() => {
  document.getElementById("preloader").style.display = "none";
  // Redirect after preloader
  window.location.href = "<?php echo $redirect_url; ?>";
}, 3000);
</script>

    </body>
    </html>
    <?php
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Teacher Evaluation System</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gold: #D4AF37;
            --light-gold: #F7E98E;
            --maroon: #800020;
            --dark-maroon: #4A0012;
            --goldenrod: #DAA520;
            --cream: #FFF8DC;
            --shadow-light: rgba(212, 175, 55, 0.2);
            --shadow-dark: rgba(74, 0, 18, 0.3);
            --text-dark: #2C1810;
            --text-light: #8B7355;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--dark-maroon) 0%, var(--maroon) 25%, var(--goldenrod) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
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
                radial-gradient(circle at 20% 50%, rgba(212, 175, 55, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(247, 233, 142, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 80%, rgba(218, 165, 32, 0.1) 0%, transparent 50%);
            animation: backgroundFloat 20s ease-in-out infinite;
        }
        
        @keyframes backgroundFloat {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-10px) rotate(1deg); }
            66% { transform: translateY(5px) rotate(-1deg); }
        }
        
        .login-container {
            max-width: 480px;
            width: 100%;
            background: linear-gradient(145deg, rgba(255, 248, 220, 0.95), rgba(255, 248, 220, 0.9));
            backdrop-filter: blur(20px);
            border: 1px solid rgba(212, 175, 55, 0.3);
            padding: 50px 45px;
            border-radius: 25px;
            box-shadow: 
                0 25px 50px var(--shadow-dark),
                0 0 0 1px rgba(212, 175, 55, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            position: relative;
            z-index: 1;
            animation: containerSlideIn 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        @keyframes containerSlideIn {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        @keyframes shimmer {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 40px;
            animation: headerFadeIn 1s ease-out 0.3s both;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        @keyframes headerFadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header img.logo {
            height: 60px;
            width: auto;
            animation: logoBounce 1s ease-out 0.5s both;
        }
        
        @keyframes logoBounce {
            0% {
                opacity: 0;
                transform: scale(0.8) translateY(-20px);
            }
            50% {
                transform: scale(1.1) translateY(5px);
            }
            100% {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
        
        .login-header h1 {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            color: var(--dark-maroon);
            margin-bottom: 15px;
            background: linear-gradient(135deg, var(--maroon), var(--primary-gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            position: relative;
        }
        
        .login-header p {
            color: var(--text-light);
            font-size: 1.1em;
            font-weight: 400;
            letter-spacing: 0.5px;
        }
        
        .institution-info {
            background: linear-gradient(135deg, var(--cream) 0%, rgba(212, 175, 55, 0.1) 100%);
            border: 2px solid var(--primary-gold);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 35px;
            text-align: center;
            position: relative;
            animation: institutionSlideIn 1s ease-out 0.5s both;
            overflow: hidden;
        }
        
        .institution-info::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, var(--primary-gold), var(--goldenrod), var(--primary-gold));
            border-radius: 15px;
            z-index: -1;
            animation: borderGlow 4s ease-in-out infinite;
        }
        
        @keyframes borderGlow {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        @keyframes institutionSlideIn {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .institution-info h3 {
            font-family: 'Playfair Display', serif;
            color: var(--maroon);
            font-size: 1.2em;
            font-weight: 600;
            margin-bottom: 8px;
            line-height: 1.3;
        }
        
        .institution-info p {
            color: var(--text-dark);
            font-size: 0.95em;
            font-weight: 500;
        }
        
        .form-group {
            margin-bottom: 25px;
            animation: formGroupSlideIn 0.6s ease-out both;
        }
        
        .form-group:nth-child(2) { animation-delay: 0.7s; }
        .form-group:nth-child(3) { animation-delay: 0.8s; }
        
        @keyframes formGroupSlideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--dark-maroon);
            font-size: 0.95em;
            letter-spacing: 0.3px;
        }
        
        .form-group input {
            width: 100%;
            padding: 18px 20px;
            border: 2px solid rgba(212, 175, 55, 0.3);
            border-radius: 12px;
            font-size: 16px;
            font-family: 'Inter', sans-serif;
            background: rgba(255, 248, 220, 0.8);
            color: var(--text-dark);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        
        .form-group input:focus {
            border-color: var(--primary-gold);
            outline: none;
            box-shadow: 
                0 0 0 4px rgba(212, 175, 55, 0.2),
                0 8px 25px rgba(212, 175, 55, 0.15);
            background: var(--cream);
            transform: translateY(-2px);
        }
        
        .form-group input::placeholder {
            color: var(--text-light);
            transition: opacity 0.3s ease;
        }
        
        .form-group input:focus::placeholder {
            opacity: 0.6;
        }
        
        /* Bounce animation for completed inputs */
        .form-group input.bounce {
            animation: inputBounce 0.5s ease;
        }
        
        @keyframes inputBounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }
        
        .login-btn {
            width: 100%;
            background: linear-gradient(135deg, var(--maroon) 0%, var(--primary-gold) 50%, var(--maroon) 100%);
            background-size: 200% 200%;
            color: white;
            padding: 18px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            letter-spacing: 0.5px;
            transition: all 0.4s ease;
            box-shadow: 
                0 8px 25px var(--shadow-dark),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            margin-bottom: 25px;
            position: relative;
            overflow: hidden;
            animation: btnSlideIn 0.6s ease-out 0.9s both;
        }
        
        @keyframes btnSlideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.6s ease;
        }
        
        .login-btn:hover {
            background-position: 100% 100%;
            transform: translateY(-3px);
            box-shadow: 
                0 15px 35px var(--shadow-dark),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
        }
        
        .login-btn:hover::before {
            left: 100%;
        }
        
        .login-btn:active {
            transform: translateY(-1px);
        }
        
        .login-btn:disabled {
            opacity: 0.8;
            cursor: not-allowed;
            transform: none;
        }
        
        .alert {
            padding: 18px 20px;
            margin-bottom: 25px;
            border-radius: 12px;
            font-weight: 500;
            border: 1px solid;
            animation: alertSlideIn 0.5s ease-out;
        }
        
        @keyframes alertSlideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .alert-error {
            color: var(--dark-maroon);
            background: linear-gradient(135deg, #ffe6e6, #ffd6d6);
            border-color: var(--maroon);
            border-left: 4px solid var(--maroon);
        }
        
        .alert-success {
            color: var(--dark-maroon);
            background: linear-gradient(135deg, var(--light-gold), var(--cream));
            border-color: var(--primary-gold);
            border-left: 4px solid var(--primary-gold);
        }
        
        .footer-links {
            text-align: center;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid rgba(212, 175, 55, 0.3);
            animation: footerSlideIn 0.6s ease-out 1.5s both;
        }
        
        @keyframes footerSlideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .footer-links a {
            color: var(--maroon);
            text-decoration: none;
            font-size: 0.9em;
            font-weight: 500;
            margin: 0 15px;
            transition: all 0.3s ease;
            padding: 8px 12px;
            border-radius: 6px;
        }
        
        .footer-links a:hover {
            color: var(--primary-gold);
            background: rgba(212, 175, 55, 0.1);
            transform: translateY(-1px);
        }
        
        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid #ffffff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Floating particles animation */
        .floating-particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
        }
        
        .particle {
            position: absolute;
            background: var(--primary-gold);
            border-radius: 50%;
            opacity: 0.1;
            animation: float 20s infinite linear;
        }
        
        .particle:nth-child(1) {
            left: 10%; width: 4px; height: 4px;
            animation-delay: 0s;
        }
        
        .particle:nth-child(2) {
            left: 20%; width: 6px; height: 6px;
            animation-delay: 2s;
        }
        
        .particle:nth-child(3) {
            left: 30%; width: 3px; height: 3px;
            animation-delay: 4s;
        }
        
        .particle:nth-child(4) {
            left: 40%; width: 5px; height: 5px;
            animation-delay: 6s;
        }
        
        .particle:nth-child(5) {
            left: 50%; width: 4px; height: 4px;
            animation-delay: 8s;
        }
        
        .particle:nth-child(6) {
            left: 60%; width: 6px; height: 6px;
            animation-delay: 10s;
        }
        
        .particle:nth-child(7) {
            left: 70%; width: 3px; height: 3px;
            animation-delay: 12s;
        }
        
        .particle:nth-child(8) {
            left: 80%; width: 5px; height: 5px;
            animation-delay: 14s;
        }
        
        .particle:nth-child(9) {
            left: 90%; width: 4px; height: 4px;
            animation-delay: 16s;
        }
        
        @keyframes float {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 0.1;
            }
            90% {
                opacity: 0.1;
            }
            100% {
                transform: translateY(-100px) rotate(360deg);
                opacity: 0;
            }
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 35px 25px;
                margin: 10px;
                border-radius: 20px;
            }
            
            .login-header {
                flex-direction: column;
                text-align: center;
            }
            
            .login-header h1 {
                font-size: 1.8em;
            }
            
            .demo-account {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .demo-account-info {
                margin-bottom: 0;
            }
            
            .footer-links a {
                display: block;
                margin: 5px 0;
            }
        }
    </style>
</head>
<body>
    <!-- Floating particles -->
    <div class="floating-particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>

    <div class="login-container">
        <div class="login-header">
            <!-- Add your logo image here - update the src path as needed -->
            <img src="logo.png" alt="School Logo" class="logo">
            <div>
                <h1>Academic Portal</h1>
                <p>Teacher Evaluation System</p>
            </div>
        </div>
        
        <div class="institution-info">
            <h3>Philippine Technological Institute of Science Arts and Trade, Inc.</h3>
            <p><strong>GMA Branch</strong> • 2nd Semester 2024-2025</p>
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
                <label for="username"> Username</label>
                <input type="text" 
                       id="username" 
                       name="username" 
                       required 
                       autocomplete="username"
                       placeholder="Enter your username"
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password"> Password</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       required 
                       autocomplete="current-password"
                       placeholder="Enter your password">
            </div>
            
            <button type="submit" class="login-btn" id="loginBtn">
                <span class="loading-spinner" id="loadingSpinner"></span>
                <span id="btnText"> Sign In</span>
            </button>
        </form>
        
    <script>
        // Auto-fill login credentials with enhanced animation
        function fillLogin(username, password) {
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            
            // Clear inputs first
            usernameInput.value = '';
            passwordInput.value = '';
            
            // Type animation effect
            typeWriter(usernameInput, username, () => {
                setTimeout(() => {
                    typeWriter(passwordInput, password, () => {
                        // Add bounce animation after both fields are filled
                        setTimeout(() => {
                            usernameInput.classList.add('bounce');
                            passwordInput.classList.add('bounce');
                            
                            // Remove the class after animation completes
                            setTimeout(() => {
                                usernameInput.classList.remove('bounce');
                                passwordInput.classList.remove('bounce');
                            }, 500);
                        }, 300);
                    });
                }, 300);
            });
            
            // Add visual feedback
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.style.borderColor = 'var(--primary-gold)';
                input.style.boxShadow = '0 0 20px rgba(212, 175, 55, 0.4)';
                setTimeout(() => {
                    input.style.borderColor = 'rgba(212, 175, 55, 0.3)';
                    input.style.boxShadow = 'none';
                }, 3000);
            });
        }
        
        // Typewriter effect
        function typeWriter(element, text, callback) {
            element.focus();
            let i = 0;
            const timer = setInterval(() => {
                if (i < text.length) {
                    element.value += text.charAt(i);
                    i++;
                } else {
                    clearInterval(timer);
                    if (callback) callback();
                }
            }, 100);
        }
        
        // Form submission with enhanced loading state
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            const spinner = document.getElementById('loadingSpinner');
            const btnText = document.getElementById('btnText');
            
            // Show loading state with animation
            btn.disabled = true;
            btn.style.transform = 'scale(0.98)';
            spinner.style.display = 'inline-block';
            btnText.textContent = 'Authenticating...';
            
            // Add pulse effect
            btn.style.animation = 'pulse 1.5s infinite';
            
            // If there's an error, the page will reload and reset the button
            // For successful login, user will be redirected
        });
        
        // Enhanced Enter key support
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const form = document.getElementById('loginForm');
                if (form.checkValidity()) {
                    form.submit();
                }
            }
        });
        
        // Enhanced page load animations
        document.addEventListener('DOMContentLoaded', function() {
            // Stagger input field animations
            const formGroups = document.querySelectorAll('.form-group');
            formGroups.forEach((group, index) => {
                group.style.animationDelay = `${0.7 + index * 0.1}s`;
            });
            
            // Focus on username input with delay
            setTimeout(() => {
                const usernameInput = document.getElementById('username');
                usernameInput.focus();
                
                // Add gentle glow when focused
                usernameInput.addEventListener('focus', function() {
                    this.style.animation = 'inputGlow 2s ease-in-out infinite';
                });
                
                usernameInput.addEventListener('blur', function() {
                    this.style.animation = 'none';
                });
            }, 1000);
            
            // Add input completion detection for bounce animation
            const inputs = document.querySelectorAll('#username, #password');
            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    if (this.value.length > 0) {
                        this.classList.add('bounce');
                        
                        // Remove the class after animation completes
                        setTimeout(() => {
                            this.classList.remove('bounce');
                        }, 500);
                    }
                });
            });
        });
        
        // Add input interaction effects
        document.querySelectorAll('.form-group input').forEach(input => {
            input.addEventListener('input', function() {
                if (this.value.length > 0) {
                    this.style.borderColor = 'var(--primary-gold)';
                    this.style.boxShadow = '0 0 0 3px rgba(212, 175, 55, 0.1)';
                } else {
                    this.style.borderColor = 'rgba(212, 175, 55, 0.3)';
                    this.style.boxShadow = 'none';
                }
            });
            
            // Add subtle bounce on focus
            input.addEventListener('focus', function() {
                this.style.animation = 'inputBounce 0.6s ease-out';
            });
        });
        
        // Demo account hover effects
        document.querySelectorAll('.demo-account').forEach(account => {
            account.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-3px) scale(1.02)';
                this.style.boxShadow = '0 12px 30px rgba(212, 175, 55, 0.25)';
            });
            
            account.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
                this.style.boxShadow = '0 4px 15px rgba(212, 175, 55, 0.1)';
            });
        });
        
        // Enhanced button interactions
        document.querySelectorAll('.use-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                // Ripple effect
                const ripple = document.createElement('span');
                ripple.style.position = 'absolute';
                ripple.style.borderRadius = '50%';
                ripple.style.background = 'rgba(255, 255, 255, 0.6)';
                ripple.style.transform = 'scale(0)';
                ripple.style.animation = 'ripple 0.6s linear';
                ripple.style.left = '50%';
                ripple.style.top = '50%';
                ripple.style.width = '20px';
                ripple.style.height = '20px';
                ripple.style.marginLeft = '-10px';
                ripple.style.marginTop = '-10px';
                
                this.style.position = 'relative';
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });
        
        // Parallax effect for background particles
        document.addEventListener('mousemove', function(e) {
            const particles = document.querySelectorAll('.particle');
            const x = e.clientX / window.innerWidth;
            const y = e.clientY / window.innerHeight;
            
            particles.forEach((particle, index) => {
                const speed = (index + 1) * 0.5;
                const xPos = x * speed * 10;
                const yPos = y * speed * 10;
                
                particle.style.transform += ` translate(${xPos}px, ${yPos}px)`;
            });
        });
        
        // Add success animation for successful fills
        function showSuccessAnimation() {
            const container = document.querySelector('.login-container');
            container.style.animation = 'successPulse 0.8s ease-out';
            
            setTimeout(() => {
                container.style.animation = '';
            }, 800);
        }
        
        // Custom CSS animations via JavaScript
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.02); }
                100% { transform: scale(1); }
            }
            
            @keyframes inputGlow {
                0%, 100% { box-shadow: 0 0 5px rgba(212, 175, 55, 0.3); }
                50% { box-shadow: 0 0 20px rgba(212, 175, 55, 0.5); }
            }
            
            @keyframes inputBounce {
                0% { transform: translateY(0); }
                30% { transform: translateY(-2px); }
                100% { transform: translateY(0); }
            }
            
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
            
            @keyframes successPulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); border-color: var(--primary-gold); }
                100% { transform: scale(1); }
            }
        `;
        document.head.appendChild(style);
        
        // Enhanced credential filling with success animation
        window.fillLogin = function(username, password) {
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            
            // Clear inputs first
            usernameInput.value = '';
            passwordInput.value = '';
            
            // Type animation effect
            typeWriter(usernameInput, username, () => {
                setTimeout(() => {
                    typeWriter(passwordInput, password, () => {
                        showSuccessAnimation();
                        
                        // Add bounce animation after both fields are filled
                        setTimeout(() => {
                            usernameInput.classList.add('bounce');
                            passwordInput.classList.add('bounce');
                            
                            // Remove the class after animation completes
                            setTimeout(() => {
                                usernameInput.classList.remove('bounce');
                                passwordInput.classList.remove('bounce');
                            }, 500);
                        }, 300);
                    });
                }, 300);
            });
            
            // Add visual feedback
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.style.borderColor = 'var(--primary-gold)';
                input.style.boxShadow = '0 0 20px rgba(212, 175, 55, 0.4)';
                setTimeout(() => {
                    input.style.borderColor = 'rgba(212, 175, 55, 0.3)';
                    input.style.boxShadow = 'none';
                }, 3000);
            });
        };
        
        // Page visibility animation
        let observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        });
        
        // Observe elements for scroll animations (if needed for mobile)
        document.querySelectorAll('.demo-account').forEach(account => {
            observer.observe(account);
        });
        
        // Add smooth scroll behavior for any internal links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>
