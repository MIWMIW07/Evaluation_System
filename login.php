<?php
// login.php - Minimalist, Premium Login
session_start();

require_once 'includes/security.php';

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

require_once 'includes/db_connection.php';

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!validate_csrf_token($_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }
    try {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        if (empty($username) || empty($password)) {
            throw new Exception("Email/Username and password are required.");
        }
        $stmt = query("SELECT id, username, password, user_type FROM users WHERE username = ? OR email = ?", [$username, $username]);
        $user = fetch_assoc($stmt);
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_type'] = $user['user_type'];
            query("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?", [$user['id']]);
            if ($user['user_type'] === 'admin') {
                header('Location: admin.php');
                exit;
            } else {
                header('Location: student_dashboard.php');
                exit;
            }
        } else {
            throw new Exception("Invalid credentials.");
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
    <title>Login • Evaluation System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css?family=Inter:400,500,600&display=swap" rel="stylesheet">
    <style>
        :root {
            --maroon: #7B1F25;
            --dark-maroon: #58131e;
            --gold: #FFD700;
            --light-gold: #FFF8DC;
            --accent: #c3a86b;
            --white: #fff;
            --shadow: 0 4px 32px 0 rgba(123,31,37,0.09), 0 1.5px 4px 0 rgba(0,0,0,0.07);
        }
        html, body {
            height: 100%;
        }
        body {
            min-height: 100vh;
            background: radial-gradient(ellipse at 70% 40%, var(--gold) 0%, var(--maroon) 100%), linear-gradient(120deg, var(--light-gold) 0%, var(--gold) 100%);
            background-blend-mode: multiply;
            font-family: 'Inter', system-ui, sans-serif;
            color: var(--dark-maroon);
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-card {
            background: var(--white);
            border-radius: 22px;
            box-shadow: var(--shadow);
            padding: 2.6rem 2.2rem 2rem 2.2rem;
            max-width: 360px;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }
        .login-card::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: 0;
            background: linear-gradient(120deg,rgba(255,215,0,0.09),rgba(123,31,37,0.07));
            border-radius: 22px;
        }
        .login-logo {
            width: 54px;
            height: 54px;
            background: linear-gradient(135deg, var(--maroon), var(--gold));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.1rem;
            box-shadow: 0 2px 10px rgba(123,31,37,0.14);
            z-index: 2;
            font-size: 2.1rem;
            color: var(--white);
        }
        .login-title {
            font-size: 1.36rem;
            font-weight: 600;
            letter-spacing: -0.01em;
            color: var(--maroon);
            margin-bottom: 0.3rem;
            z-index: 2;
        }
        .login-desc {
            font-size: 1rem;
            color: var(--dark-maroon);
            opacity: 0.7;
            margin-bottom: 1.5rem;
            z-index: 2;
            text-align: center;
        }
        form {
            width: 100%;
            z-index: 2;
        }
        .form-group {
            margin-bottom: 1.2rem;
            position: relative;
        }
        .form-label {
            display: block;
            font-size: 0.97rem;
            font-weight: 500;
            margin-bottom: 0.37rem;
            color: var(--maroon);
        }
        .form-input {
            width: 100%;
            padding: 0.76rem 2.3rem 0.76rem 2.25rem;
            border-radius: 10px;
            border: 1.5px solid #e5d7b0;
            font-size: 1.02rem;
            background: var(--light-gold);
            color: var(--dark-maroon);
            transition: border 0.2s;
            outline: none;
        }
        .form-input:focus {
            border-color: var(--maroon);
            background: #fff8e1;
        }
        .form-icon {
            position: absolute;
            top: 50%;
            left: 0.9rem;
            transform: translateY(-50%);
            color: var(--maroon);
            font-size: 1.14rem;
            opacity: 0.79;
            pointer-events: none;
        }
        .login-btn {
            width: 100%;
            margin-top: 0.1rem;
            background: linear-gradient(90deg, var(--maroon), var(--gold) 110%);
            color: var(--white);
            border: none;
            border-radius: 10px;
            padding: 0.8rem 0;
            font-size: 1.08rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            box-shadow: 0 2px 14px 0 rgba(123,31,37,0.08);
            cursor: pointer;
            transition: background 0.18s, transform 0.15s;
        }
        .login-btn:hover, .login-btn:focus {
            background: linear-gradient(90deg, var(--gold) 0%, var(--maroon) 110%);
            color: var(--maroon);
            transform: translateY(-1.5px) scale(1.01);
        }
        .link-row {
            display: flex;
            justify-content: space-between;
            margin-top: 0.95rem;
            font-size: 0.98em;
            width: 100%;
            z-index: 2;
        }
        .form-link {
            color: var(--gold);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.16s;
        }
        .form-link:hover {
            color: var(--maroon);
            text-decoration: underline;
        }
        .signup-row {
            margin-top: 1.3rem;
            text-align: center;
            width: 100%;
            font-size: 1em;
            z-index: 2;
        }
        .signup-link {
            color: var(--maroon);
            text-decoration: none;
            font-weight: 600;
        }
        .signup-link:hover {
            color: var(--gold);
            text-decoration: underline;
        }
        .alert {
            width: 100%;
            padding: 0.85rem 1rem;
            border-radius: 10px;
            margin-bottom: 1.2rem;
            font-size: 0.98em;
            font-weight: 500;
            z-index: 2;
            text-align: center;
        }
        .alert-error {
            background: linear-gradient(90deg, #f3c2c2 0%, #ffe5e5 100%);
            color: #8e2929;
            border-left: 4px solid #b81e1e;
        }
        .alert-success {
            background: linear-gradient(90deg, #e0ffd5 0%, #fffbe5 100%);
            color: #25601e;
            border-left: 4px solid #74b81e;
        }
        @media (max-width: 600px) {
            body {
                padding: 1.5rem;
            }
            .login-card {
                padding: 1.5rem 0.7rem 1.1rem 0.7rem;
                max-width: 98vw;
            }
            .login-title {
                font-size: 1.15rem;
            }
        }
        /* Background illustration (SVG) */
        .bg-illustration {
            position: absolute;
            top: -25px; left: -40px;
            z-index: 0;
            width: 160px; height: 160px;
            pointer-events: none;
            opacity: 0.13;
            filter: blur(1px);
        }
        .bg-illustration-right {
            position: absolute;
            bottom: -38px; right: -45px;
            width: 130px; height: 120px;
            z-index: 0;
            opacity: 0.11;
            filter: blur(1.5px);
        }
    </style>
</head>
<body>
    <svg class="bg-illustration" viewBox="0 0 120 120" fill="none">
        <ellipse cx="60" cy="60" rx="59" ry="53" fill="#FFD700"/>
        <ellipse cx="40" cy="60" rx="24" ry="20" fill="#7B1F25"/>
    </svg>
    <svg class="bg-illustration-right" viewBox="0 0 120 120" fill="none">
        <ellipse cx="60" cy="60" rx="59" ry="53" fill="#7B1F25"/>
        <ellipse cx="80" cy="75" rx="24" ry="20" fill="#FFD700"/>
    </svg>
    <div class="login-card">
        <div class="login-logo">
            <span>🎓</span>
        </div>
        <div class="login-title">Sign In</div>
        <div class="login-desc">Teacher Evaluation System</div>
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <div class="form-group">
                <span class="form-icon">
                    <!-- User Icon -->
                    <svg width="20" height="20" fill="none" style="vertical-align:middle" viewBox="0 0 20 20"><circle cx="10" cy="7" r="4.1" stroke="currentColor" stroke-width="1.3"/><path d="M2.5 17c0-3.5 3-5.5 7.5-5.5s7.5 2 7.5 5.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                </span>
                <label for="username" class="form-label">Email or Username</label>
                <input type="text" name="username" id="username" class="form-input" required autocomplete="username" placeholder="Enter email or username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <span class="form-icon">
                    <!-- Lock Icon -->
                    <svg width="20" height="20" fill="none" style="vertical-align:middle" viewBox="0 0 20 20"><rect x="4" y="9" width="12" height="7" rx="2" stroke="currentColor" stroke-width="1.3"/><path d="M7 9V7a3 3 0 1 1 6 0v2" stroke="currentColor" stroke-width="1.3"/></svg>
                </span>
                <label for="password" class="form-label">Password</label>
                <input type="password" name="password" id="password" class="form-input" required autocomplete="current-password" placeholder="Enter your password">
            </div>
            <button type="submit" class="login-btn">Login</button>
            <div class="link-row">
                <a href="forgot_password.php" class="form-link">Forgot Password?</a>
                <a href="signup.php" class="form-link">Sign Up</a>
            </div>
        </form>
        <div class="signup-row">
            Don't have an account?
            <a href="signup.php" class="signup-link">Create one</a>
        </div>
    </div>
    <script>
        // Center card fade-in
        document.addEventListener('DOMContentLoaded', function() {
            const card = document.querySelector('.login-card');
            card.style.opacity = '0';
            card.style.transform = 'scale(0.97) translateY(18px)';
            setTimeout(() => {
                card.style.transition = 'all 0.7s cubic-bezier(.19,1,.22,1)';
                card.style.opacity = '1';
                card.style.transform = 'scale(1) translateY(0)';
            }, 120);
            document.getElementById('username').focus();
        });
    </script>
</body>
</html>
