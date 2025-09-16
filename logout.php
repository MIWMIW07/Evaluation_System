<?php
// logout.php - Handle user logout
session_start();

// Store user info for goodbye message
$user_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'User';
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';

// Destroy all session data
session_unset();
session_destroy();

// Start a new session for the goodbye message
session_start();
$_SESSION['logout_message'] = "👋 Goodbye, " . htmlspecialchars($user_name) . "! You have been logged out successfully.";
$_SESSION['user_type_was'] = $user_type;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logged Out - Teacher Evaluation System</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .logout-container {
            max-width: 500px;
            width: 100%;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            text-align: center;
        }
        
        .logout-icon {
            font-size: 4em;
            margin-bottom: 20px;
            color: #4CAF50;
        }
        
        .logout-header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 1.8em;
        }
        
        .logout-header p {
            color: #7f8c8d;
            margin-bottom: 25px;
        }
        
        .logout-message {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            padding: 20px;
            border-radius: 10px;
            border-left: 5px solid #28a745;
            margin-bottom: 30px;
            font-weight: 500;
        }
        
        .security-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 4px solid #2196F3;
        }
        
        .security-info h4 {
            color: #1976D2;
            margin-bottom: 10px;
        }
        
        .security-info ul {
            list-style: none;
            padding: 0;
        }
        
        .security-info li {
            padding: 5px 0;
            color: #666;
        }
        
        .security-info li:before {
            content: "✓ ";
            color: #4CAF50;
            font-weight: bold;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #45a049 0%, #4CAF50 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.4);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #1976D2 0%, #2196F3 100%);
            box-shadow: 0 8px 25px rgba(33, 150, 243, 0.4);
        }
        
        .footer-info {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 0.9em;
            color: #666;
        }
        
        .countdown {
            font-size: 0.9em;
            color: #666;
            margin-top: 15px;
        }
        
        @media (max-width: 480px) {
            .logout-container {
                padding: 25px;
                margin: 10px;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 250px;
                margin: 5px 0;
            }
            
            .logout-icon {
                font-size: 3em;
            }
        }
        
        .fade-in {
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
            transform: translateY(20px);
        }
        
        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="logout-container fade-in">
        <div class="logout-icon">👋</div>
        
        <div class="logout-header">
            <h1>Successfully Logged Out</h1>
            <p>Teacher Evaluation System</p>
        </div>
        
        <?php if (isset($_SESSION['logout_message'])): ?>
            <div class="logout-message">
                <?php echo $_SESSION['logout_message']; ?>
            </div>
        <?php endif; ?>
        
        <div class="security-info">
            <h4>🔒 Security Information</h4>
            <ul>
                <li>Your session has been securely terminated</li>
                <li>All personal data has been cleared</li>
                <li>You can safely close this browser</li>
                <li>Remember to log out from shared computers</li>
            </ul>
        </div>
        
        <div class="action-buttons">
            <a href="login.php" class="btn">🔑 Login Again</a>
            <?php if (isset($_SESSION['user_type_was']) && $_SESSION['user_type_was'] === 'admin'): ?>
                <a href="admin.php" class="btn btn-primary">📊 Admin Dashboard</a>
            <?php endif; ?>
        </div>
        
        <div class="countdown" id="redirectCountdown">
            Redirecting to login page in <span id="timer">10</span> seconds...
        </div>
        
        <div class="footer-info">
            <p><strong>Philippine Technological Institute of Science Arts and Trade, Inc.</strong></p>
            <p>GMA-BRANCH • Teacher Evaluation System</p>
            <p style="margin-top: 10px;">
                Session ended: <?php echo date('F j, Y \a\t g:i A'); ?>
            </p>
        </div>
    </div>

    <script>
        // Countdown timer for auto-redirect
        let timeLeft = 10;
        const timerElement = document.getElementById('timer');
        const countdownElement = document.getElementById('redirectCountdown');
        
        function updateTimer() {
            timerElement.textContent = timeLeft;
            
            if (timeLeft <= 0) {
                countdownElement.innerHTML = 'Redirecting now...';
                window.location.href = 'login.php';
                return;
            }
            
            timeLeft--;
        }
        
        // Start the countdown
        const timerInterval = setInterval(updateTimer, 1000);
        
        // Stop countdown if user interacts with the page
        document.addEventListener('click', function() {
            clearInterval(timerInterval);
            countdownElement.style.display = 'none';
        });
        
        document.addEventListener('keypress', function() {
            clearInterval(timerInterval);
            countdownElement.style.display = 'none';
        });
        
        // Add some visual effects
        document.addEventListener('DOMContentLoaded', function() {
            // Animate security info items
            const listItems = document.querySelectorAll('.security-info li');
            listItems.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateX(-20px)';
                item.style.transition = 'all 0.3s ease';
                
                setTimeout(() => {
                    item.style.opacity = '1';
                    item.style.transform = 'translateX(0)';
                }, 300 + (index * 100));
            });
            
            // Add click effects to buttons
            document.querySelectorAll('.btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    // Create ripple effect
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.width = ripple.style.height = size + 'px';
                    ripple.style.left = x + 'px';
                    ripple.style.top = y + 'px';
                    ripple.classList.add('ripple');
                    
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });
        });
        
        // Clear any remaining session data (client-side cleanup)
        if (typeof(Storage) !== "undefined") {
            localStorage.clear();
            sessionStorage.clear();
        }
    </script>
    
    <style>
        .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            transform: scale(0);
            animation: ripple-animation 0.6s linear;
            pointer-events: none;
        }
        
        @keyframes ripple-animation {
            to {
                transform: scale(2);
                opacity: 0;
            }
        }
        
        .btn {
            position: relative;
            overflow: hidden;
        }
    </style>
</body>
</html>

<?php
// Clear the logout message after displaying
unset($_SESSION['logout_message']);
unset($_SESSION['user_type_was']);
?>
