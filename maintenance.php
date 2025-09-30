<?php
// Fix for header warning
ob_start();
session_start();
require_once 'includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}
ob_end_clean();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - Teacher Evaluation System</title>
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
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 3px solid #667eea;
            padding-bottom: 25px;
        }
        
        .card {
            background: #f8f9fa;
            padding: 25px;
            margin-bottom: 25px;
            border-radius: 10px;
            border-left: 5px solid #667eea;
        }
        
        .btn {
            background: #667eea;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            margin: 5px;
            transition: all 0.3s ease;
            display: inline-block;
            text-decoration: none;
        }
        
        .btn:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
        }
        
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        
        .btn-warning { background: #ffc107; color: #333; }
        .btn-warning:hover { background: #e0a800; }
        
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        
        .btn-info { background: #17a2b8; }
        .btn-info:hover { background: #138496; }
        
        .loading {
            color: #6c757d;
            font-style: italic;
        }
        
        .result-box {
            margin-top: 15px;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #ddd;
        }
        
        .result-success { 
            background: #d4edda; 
            border-left-color: #28a745; 
            color: #155724;
        }
        
        .result-error { 
            background: #f8d7da; 
            border-left-color: #dc3545; 
            color: #721c24;
        }
        
        .debug-details {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
            font-family: monospace;
            font-size: 0.85em;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .back-button {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Back Button -->
        <div class="back-button">
            <a href="admin.php" class="btn">← Back to Dashboard</a>
        </div>

        <!-- Header -->
        <div class="header">
            <h1>🔧 System Maintenance</h1>
            <p>Database setup, Google integration, and system diagnostics</p>
        </div>

        <!-- Database Setup -->
        <div class="card">
            <h3>🗃️ Database Setup</h3>
            <p>Create or update the database tables for the evaluation system:</p>
            <button class="btn btn-success" onclick="setupDatabase()">Run Database Setup</button>
            <div id="databaseResult"></div>
        </div>

        <!-- Google Integration -->
        <div class="card">
            <h3>🔗 Google Integration</h3>
            <p>Test and manage Google Sheets and Drive integration:</p>
            <button class="btn btn-info" onclick="testGoogleConnection()">Test Google Connection</button>
            <button class="btn btn-warning" onclick="syncGoogleData()">Sync Google Sheets Data</button>
            <div id="googleResult"></div>
        </div>

        <!-- System Diagnostics -->
        <div class="card">
            <h3>🐛 System Diagnostics</h3>
            <p>Run diagnostics to identify and fix system issues:</p>
            <button class="btn btn-warning" onclick="runSystemDiagnostics()">Run System Diagnostics</button>
            <button class="btn btn-info" onclick="checkDatabaseData()">Check Database Data</button>
            <div id="diagnosticsResult"></div>
        </div>
    </div>

    <script>
        // Database Setup
        async function setupDatabase() {
            const resultDiv = document.getElementById('databaseResult');
            resultDiv.innerHTML = '<p class="loading">Setting up database...</p>';
            
            try {
                const response = await fetch('database_setup.php');
                const html = await response.text();
                resultDiv.innerHTML = `<div class="debug-details">${html}</div>`;
            } catch (error) {
                resultDiv.innerHTML = `<div class="result-box result-error">Error: ${error.message}</div>`;
            }
        }

        // Google Integration Functions
        async function testGoogleConnection() {
            const resultDiv = document.getElementById('googleResult');
            resultDiv.innerHTML = '<p class="loading">Testing Google connection...</p>';
            
            try {
                const response = await fetch('google_integration_api.php?action=test_connection');
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = `<div class="result-box result-success">${data.message}</div>`;
                } else {
                    resultDiv.innerHTML = `<div class="result-box result-error">${data.error}</div>`;
                }
            } catch (error) {
                resultDiv.innerHTML = `<div class="result-box result-error">Network error: ${error.message}</div>`;
            }
        }

        async function syncGoogleData() {
            const resultDiv = document.getElementById('googleResult');
            resultDiv.innerHTML = '<p class="loading">Syncing data from Google Sheets...</p>';
            
            try {
                const response = await fetch('google_integration_api.php?action=sync_data');
                const data = await response.json();
                
                if (data.success) {
                    let html = `<div class="result-box result-success">`;
                    html += `<p>✅ ${data.message}</p>`;
                    if (data.students) html += `<p>Students: ${data.students.message || 'Synced'}</p>`;
                    if (data.teachers) html += `<p>Teachers: ${data.teachers.message || 'Synced'}</p>`;
                    html += `</div>`;
                    resultDiv.innerHTML = html;
                } else {
                    resultDiv.innerHTML = `<div class="result-box result-error">${data.error}</div>`;
                }
            } catch (error) {
                resultDiv.innerHTML = `<div class="result-box result-error">Network error: ${error.message}</div>`;
            }
        }

        // System Diagnostics
        async function runSystemDiagnostics() {
            const resultDiv = document.getElementById('diagnosticsResult');
            resultDiv.innerHTML = '<p class="loading">Running system diagnostics...</p>';
            
            try {
                const response = await fetch('detailed_debug_reports.php');
                const html = await response.text();
                resultDiv.innerHTML = `<div class="debug-details">${html}</div>`;
            } catch (error) {
                resultDiv.innerHTML = `<div class="result-box result-error">Error: ${error.message}</div>`;
            }
        }

        async function checkDatabaseData() {
            const resultDiv = document.getElementById('diagnosticsResult');
            resultDiv.innerHTML = '<p class="loading">Checking database data...</p>';
            
            try {
                const response = await fetch('database_setup.php');
                const html = await response.text();
                resultDiv.innerHTML = `<div class="debug-details">${html}</div>`;
            } catch (error) {
                resultDiv.innerHTML = `<div class="result-box result-error">Error: ${error.message}</div>`;
            }
        }
    </script>
</body>
</html>
