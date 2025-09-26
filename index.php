<?php
// index.php - Teacher Evaluation System
session_start();

// Include security and database connections
require_once 'includes/security.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_type'] === 'admin') {
        header('Location: admin.php');
        exit();
    } else {
        header('Location: student_dashboard.php');
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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 24px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }

        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e1e1;
            border-radius: 10px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
            margin-top: 10px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }

        .error {
            background: #ffe6e6;
            color: #d63031;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #fab1a0;
        }

        .info-box {
            background: #e8f4f8;
            border: 1px solid #74b9ff;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            text-align: left;
        }

        .info-box h3 {
            color: #0984e3;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .info-box p {
            color: #636e72;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .status {
            margin-top: 20px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 10px;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">TES</div>
        <h1>Teacher Evaluation System</h1>
        <p class="subtitle">Please sign in to continue</p>

        <?php if (isset($_GET['error'])): ?>
            <div class="error">
                <?php 
                switch($_GET['error']) {
                    case 'invalid':
                        echo 'Invalid username or password.';
                        break;
                    case 'required':
                        echo 'Please fill in all fields.';
                        break;
                    case 'system':
                        echo 'System error. Please try again later.';
                        break;
                    default:
                        echo 'Login failed. Please try again.';
                }
                ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn">Sign In</button>
        </form>

        <div class="info-box">
            <h3>Login Instructions:</h3>
            <p><strong>Students:</strong> Username is LASTNAME + FIRSTNAME (no spaces, all caps)</p>
            <p><strong>Password:</strong> pass123</p>
            <p><strong>Admin:</strong> Username: admin, Password: admin123</p>
        </div>

        <div class="status">
            <strong>System Status:</strong> ✅ Online | 
            PHP <?php echo phpversion(); ?> | 
            <?php echo date('Y-m-d H:i:s'); ?>
        </div>
    </div>
</body>
</html>
