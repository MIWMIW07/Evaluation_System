<?php
session_start();
require_once 'includes/security.php';
require_once 'includes/db_connection.php';

class LoginHandler {
    private $error = '';
    private $success = '';
    private $showPreloader = false;
    private $redirectUrl = '';

    public function handleLogoutMessage() {
        if (isset($_SESSION['logout_message'])) {
            $this->success = $_SESSION['logout_message'];
            unset($_SESSION['logout_message']);
            unset($_SESSION['user_type_was']);
        }
    }

    public function checkExistingSession() {
        if (isset($_SESSION['user_id'])) {
            $this->redirectUrl = $_SESSION['user_type'] === 'admin' ? 'admin.php' : 'student_dashboard.php';
            $this->showPreloader = true;
        }
    }

    public function handleLoginSubmission() {
        if ($_SERVER["REQUEST_METHOD"] != "POST" || $this->showPreloader) {
            return;
        }

        if (!validate_csrf_token($_POST['csrf_token'])) {
            die('CSRF token validation failed');
        }

        try {
            $username = trim($_POST['username']);
            $password = trim($_POST['password']);

            if (empty($username) || empty($password)) {
                throw new Exception("Username and password are required.");
            }

            $user = $this->authenticateUser($username, $password);
            $this->createUserSession($user);
            $this->updateLastLogin($user['id']);
            
            $this->redirectUrl = $user['user_type'] === 'admin' ? 'admin.php' : 'student_dashboard.php';
            $this->showPreloader = true;

        } catch (Exception $e) {
            $this->error = $e->getMessage();
        }
    }

    private function authenticateUser($username, $password) {
        $stmt = query(
            "SELECT id, username, password, user_type, full_name, student_id, program, section 
             FROM users WHERE username = ?", 
            [$username]
        );
        $user = fetch_assoc($stmt);

        if (!$user || !password_verify($password, $user['password'])) {
            throw new Exception("Invalid username or password.");
        }

        return $user;
    }

    private function createUserSession($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['student_id'] = $user['student_id'];
        $_SESSION['program'] = $user['program'];
        $_SESSION['section'] = $user['section'];
    }

    private function updateLastLogin($userId) {
        query("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?", [$userId]);
    }

    public function shouldRedirect() {
        return $this->showPreloader && $this->redirectUrl;
    }

    public function getError() { return $this->error; }
    public function getSuccess() { return $this->success; }
    public function getRedirectUrl() { return $this->redirectUrl; }
}

// Main execution
$loginHandler = new LoginHandler();
$loginHandler->handleLogoutMessage();
$loginHandler->checkExistingSession();
$loginHandler->handleLoginSubmission();

if ($loginHandler->shouldRedirect()) {
    $this->renderPreloader($loginHandler->getRedirectUrl());
    exit;
}

$this->renderLoginPage($loginHandler->getError(), $loginHandler->getSuccess());

function renderPreloader($redirectUrl) {
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
            .circle-border {
                width: 170px;
                height: 170px;
                border-radius: 50%;
                border: 6px solid #DAA520;
                border-top: 6px solid gold;
                border-bottom: 6px solid #800020;
                border-left: 6px solid #FAFAD2;
                border-right: 6px solid #8B0000;
                display: flex;
                justify-content: center;
                align-items: center;
                animation: spinBorder 3s linear infinite, glow 2s ease-in-out infinite alternate;
                box-shadow: 0 0 20px #DAA520, 0 0 40px #800020;
            }
            .circle-border img {
                width: 100px;
                height: 100px;
                animation: spinLogo 5s linear infinite;
            }
            @keyframes spinBorder {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            @keyframes spinLogo {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(-360deg); }
            }
            @keyframes glow {
                0% { box-shadow: 0 0 10px #DAA520, 0 0 20px #800020; }
                100% { box-shadow: 0 0 30px gold, 0 0 60px #8B0000; }
            }
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
                window.location.href = "<?php echo $redirectUrl; ?>";
            }, 3000);
        </script>
    </body>
    </html>
    <?php
}

function renderLoginPage($error, $success) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login - Teacher Evaluation System</title>
        <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="css/login.css">
    </head>
    <body>
        <div class="floating-particles">
            <?php for ($i = 1; $i <= 9; $i++): ?>
                <div class="particle"></div>
            <?php endfor; ?>
        </div>

        <div class="login-container">
            <div class="login-header">
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
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required autocomplete="username"
                           placeholder="Enter your username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password"
                           placeholder="Enter your password">
                </div>
                
                <button type="submit" class="login-btn" id="loginBtn">
                    <span class="loading-spinner" id="loadingSpinner"></span>
                    <span id="btnText">Sign In</span>
                </button>
            </form>
        </div>
        
        <script src="js/login.js"></script>
    </body>
    </html>
    <?php
}
