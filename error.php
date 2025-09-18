<?php
// error.php - Error handler for Apache errors
$error_code = $_GET['code'] ?? '500';
$error_title = '';
$error_message = '';
$error_suggestion = '';

switch ($error_code) {
    case '404':
        $error_title = 'Page Not Found';
        $error_message = 'The page you are looking for could not be found.';
        $error_suggestion = 'Please check the URL or return to the home page.';
        break;
    case '500':
        $error_title = 'Internal Server Error';
        $error_message = 'Something went wrong on our server.';
        $error_suggestion = 'Please try again later or contact the administrator.';
        break;
    case '403':
        $error_title = 'Access Forbidden';
        $error_message = 'You do not have permission to access this resource.';
        $error_suggestion = 'Please contact the administrator if you believe this is an error.';
        break;
    default:
        $error_title = 'Error';
        $error_message = 'An unexpected error occurred.';
        $error_suggestion = 'Please try again or contact support.';
}

http_response_code(intval($error_code));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error <?php echo htmlspecialchars($error_code); ?> - Teacher Evaluation System</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .error-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        
        .error-code {
            font-size: 4em;
            font-weight: bold;
            color: #dc3545;
            margin-bottom: 20px;
        }
        
        .error-title {
            font-size: 1.5em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .error-message {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .error-suggestion {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
            margin-bottom: 30px;
            color: #495057;
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
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #45a049 0%, #4CAF50 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.4);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3);
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #1976D2 0%, #2196F3 100%);
            box-shadow: 0 8px 25px rgba(33, 150, 243, 0.4);
        }
        
        @media (max-width: 768px) {
            .error-container {
                margin: 10px;
                padding: 20px;
            }
            
            .error-code {
                font-size: 3em;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 200px;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code"><?php echo htmlspecialchars($error_code); ?></div>
        <div class="error-title"><?php echo htmlspecialchars($error_title); ?></div>
        <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <div class="error-suggestion"><?php echo htmlspecialchars($error_suggestion); ?></div>
        
        <div class="action-buttons">
            <a href="/" class="btn">🏠 Home</a>
            <a href="login.php" class="btn btn-secondary">🔐 Login</a>
            <a href="javascript:history.back()" class="btn btn-secondary">← Go Back</a>
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #999; font-size: 0.9em;">
            <p><strong>Philippine Technological Institute of Science Arts and Trade, Inc.</strong></p>
            <p>Teacher Evaluation System</p>
        </div>
    </div>
</body>
</html>
