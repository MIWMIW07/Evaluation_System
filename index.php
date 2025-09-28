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
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #D4AF37;
        }
        
        .logo-container {
            flex-shrink: 0;
            margin-right: 20px;
        }
        
        .logo-container img {
            height: 80px;
            width: auto;
            border: 2px solid #D4AF37;
            border-radius: 8px;
            padding: 5px;
            background: white;
        }
        
        .title-container {
            flex-grow: 1;
        }
        
        h1 {
            color: #800000;
            font-size: 22px;
            line-height: 1.3;
            margin-bottom: 5px;
        }
        
        .school-name {
            color: #800000;
            font-weight: 600;
            font-size: 14px;
            background: rgba(212, 175, 55, 0.2);
            padding: 8px 12px;
            border-radius: 6px;
            border-left: 3px solid #D4AF37;
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
            padding: 12px 15px 12px 40px;
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
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #800000;
        }
        
        .btn {
            width: 100%;
            background: linear-gradient(135deg, #800000 0%, #A52A2A 100%);
            color: white;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            box-shadow: 0 4px 6px rgba(128, 0, 0, 0.2);
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #A52A2A 0%, #800000 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(128, 0, 0, 0.3);
        }
        
        .btn:active {
            transform: translateY(0);
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
            .header-container {
                flex-direction: column;
                text-align: center;
            }
            
            .logo-container {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .login-container {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="header-container">
            <div class="logo-container">
                <img src="logo.png" alt="School Logo" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHZpZXdCb3g9IjAgMCA4MCA4MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjgwIiBoZWlnaHQ9IjgwIiByeD0iOCIgZmlsbD0iI0Q0QUYzNyIvPgo8cGF0aCBkPSJNMjYgMzJIMjhWMzRIMjZWMzJaIiBmaWxsPSIjODAwMDAwIi8+CjxwYXRoIGQ9Ik0zMiAzMkgzNFYzNEgzMlYzMloiIGZpbGw9IiM4MDAwMDAiLz4KPHBhdGggZD0iTTM4IDMySDQwVjM0SDM4VjMyWiIgZmlsbD0iIzgwMDAwMCIvPgo8cGF0aCBkPSJNNDQgMzJINDZWMzRINDRWMzJaIiBmaWxsPSIjODAwMDAwIi8+CjxwYXRoIGQ9Ik0yNiAzOEgyOFY0MEgyNlYzOFoiIGZpbGw9IiM4MDAwMDAiLz4KPHBhdGggZD0iTTMyIDM4SDM0VjQwSDMyVjM4WiIgZmlsbD0iIzgwMDAwMCIvPgo8cGF0aCBkPSJNMzggMzhINDBWNDBIMzhWMzhaIiBmaWxsPSIjODAwMDAwIi8+CjxwYXRoIGQ9Ik00NCAzOEg0NlY0MEg0NFYzOFoiIGZpbGw9IiM4MDAwMDAiLz4KPHBhdGggZD0iTTI2IDQ0SDI4VjQ2SDI2VjQ0WiIgZmlsbD0iIzgwMDAwMCIvPgo8cGF0aCBkPSJNMzIgNDRIMzRWNDZIMzJWNDRaIiBmaWxsPSIjODAwMDAwIi8+CjxwYXRoIGQ9Ik0zOCA0NEg0MFY0NkgzOFY0NFoiIGZpbGw9IiM4MDAwMDAiLz4KPHBhdGggZD0iTTQ0IDQ0SDQ2VjQ2SDQ0VjQ0WiIgZmlsbD0iIzgwMDAwMCIvPgo8cGF0aCBkPSJNMjYgNTBIMjhWNTJIMjZWNTBaIiBmaWxsPSIjODAwMDAwIi8+CjxwYXRoIGQ9Ik0zMiA1MEgzNFY1MkgzMlY1MFoiIGZpbGw9IiM4MDAwMDAiLz4KPHBhdGggZD0iTTM4IDUwSDQwVjUySDM4VjUwWiIgZmlsbD0iIzgwMDAwMCIvPgo8cGF0aCBkPSJNNDQgNTBINDZWNTJINDRWNTBaIiBmaWxsPSIjODAwMDAwIi8+CjxwYXRoIGQ9Ik0yNiA1NkgyOFY1OEgyNlY1NloiIGZpbGw9IiM4MDAwMDAiLz4KPHBhdGggZD0iTTMyIDU2SDM0VjU4SDMyVjU2WiIgZmlsbD0iIzgwMDAwMCIvPgo8cGF0aCBkPSJNMzggNTZINDBWNTgzOFY1NloiIGZpbGw9IiM4MDAwMDAiLz4KPHBhdGggZD0iTTQ0IDU2SDQ2VjU4SDQ0VjU2WiIgZmlsbD0iIzgwMDAwMCIvPgo8L3N2Zz4K';">
            </div>
            <div class="title-container">
                <h1>Teacher Evaluation System</h1>
                <div class="school-name">Philippine Technological Institute of Science Arts and Trade, Inc.</div>
            </div>
        </div>
        
        <?php if (isset($_SESSION['logout_message'])): ?>
            <div class="alert alert-success">
                <?php 
                echo htmlspecialchars($_SESSION['logout_message']); 
                unset($_SESSION['logout_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php 
                echo htmlspecialchars($_SESSION['error']); 
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-container">
                    <div class="input-icon">👤</div>
                    <input type="text" id="username" name="username" placeholder="Enter your username" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-container">
                    <div class="input-icon">🔒</div>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>
            </div>
            
            <button type="submit" class="btn">Login</button>
        </form>
        
        <div class="footer">
            <p>&copy; 2025 Philippine Technological Institute of Science Arts and Trade, Inc.</p>
        </div>
    </div>
</body>
</html>
