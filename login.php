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

    public function renderPreloader() {
        if (!$this->shouldRedirect()) {
            return;
        }
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
                    window.location.href = "<?php echo $this->redirectUrl; ?>";
                }, 3000);
            </script>
        </body>
        </html>
        <?php
    }

    public function renderLoginPage() {
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
                
                .login-container {
                    max-width: 480px;
                    width: 100%;
                    background: linear-gradient(145deg, rgba(255, 248, 220, 0.95), rgba(255, 248, 220, 0.9));
                    backdrop-filter: blur(20px);
                    border: 1px solid rgba(212, 175, 55, 0.3);
                    padding: 50px 45px;
                    border-radius: 25px;
                    box-shadow: 0 25px 50px var(--shadow-dark);
                    position: relative;
                    z-index: 1;
                }
                
                .login-header {
                    text-align: center;
                    margin-bottom: 40px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 15px;
                }
                
                .login-header h1 {
                    font-family: 'Playfair Display', serif;
                    font-weight: 700;
                    color: var(--dark-maroon);
                    margin-bottom: 15px;
                }
                
                .institution-info {
                    background: linear-gradient(135deg, var(--cream) 0%, rgba(212, 175, 55, 0.1) 100%);
                    border: 2px solid var(--primary-gold);
                    border-radius: 15px;
                    padding: 25px;
                    margin-bottom: 35px;
                    text-align: center;
                }
                
                .institution-info h3 {
                    font-family: 'Playfair Display', serif;
                    color: var(--maroon);
                    font-size: 1.2em;
                    margin-bottom: 8px;
                }
                
                .form-group {
                    margin-bottom: 25px;
                }
                
                .form-group label {
                    display: block;
                    margin-bottom: 10px;
                    font-weight: 600;
                    color: var(--dark-maroon);
                }
                
                .form-group input {
                    width: 100%;
                    padding: 18px 20px;
                    border: 2px solid rgba(212, 175, 55, 0.3);
                    border-radius: 12px;
                    font-size: 16px;
                    background: rgba(255, 248, 220, 0.8);
                    transition: all 0.3s ease;
                }
                
                .form-group input:focus {
                    border-color: var(--primary-gold);
                    outline: none;
                    box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.2);
                }
                
                .login-btn {
                    width: 100%;
                    background: linear-gradient(135deg, var(--maroon) 0%, var(--primary-gold) 100%);
                    color: white;
                    padding: 18px;
                    border: none;
                    border-radius: 12px;
                    cursor: pointer;
                    font-size: 16px;
                    font-weight: 600;
                    transition: all 0.3s ease;
                }
                
                .alert {
                    padding: 18px 20px;
                    margin-bottom: 25px;
                    border-radius: 12px;
                    font-weight: 500;
                }
                
                .alert-error {
                    color: var(--dark-maroon);
                    background: linear-gradient(135deg, #ffe6e6, #ffd6d6);
                    border: 1px solid var(--maroon);
                }
                
                .alert-success {
                    color: var(--dark-maroon);
                    background: linear-gradient(135deg, var(--light-gold), var(--cream));
                    border: 1px solid var(--primary-gold);
                }
                
                @media (max-width: 480px) {
                    .login-container {
                        padding: 35px 25px;
                        margin: 10px;
                    }
                }
            </style>
        </head>
        <body>
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
                
                <?php if (!empty($this->error)): ?>
                    <div class="alert alert-error">❌ <?php echo htmlspecialchars($this->error); ?></div>
                <?php endif; ?>
                
                <?php if (!empty($this->success)): ?>
                    <div class="alert alert-success">✅ <?php echo htmlspecialchars($this->success); ?></div>
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
                    
                    <button type="submit" class="login-btn">Sign In</button>
                </form>
            </div>
        </body>
        </html>
        <?php
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
    $loginHandler->renderPreloader();
    exit;
}

$loginHandler->renderLoginPage();
