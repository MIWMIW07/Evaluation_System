<?php
// Updated index.php - Redirect to login system
session_start();



// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_type'] === 'admin') {
        header('Location: admin.php');
        exit;
    } else {
        header('Location: student_dashboard.php');
        exit;
    }
}

// If not logged in, redirect to login page
header('Location: login.php');
exit;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirecting... - Teacher Evaluation System</title>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .loading-container {
            text-align: center;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #4CAF50;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        h2 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        p {
            color: #7f8c8d;
        }
    </style>
</head>
<body>
    <div class="loading-container">
        <div class="spinner"></div>
        <h2>Redirecting to Login...</h2>
        <p>Please wait while we redirect you to the login page.</p>
        <script>
            setTimeout(function() {
                window.location.href = 'login.php';
            }, 2000);
        </script>
    </div>
</body>
</html>



