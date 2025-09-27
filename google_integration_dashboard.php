<?php
session_start();
require_once 'includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Integration Dashboard</title>
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
        }
        
        .btn:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
        }
        
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-online { background: #28a745; }
        .status-offline { background: #dc3545; }
        .status-warning { background: #ffc107; }
        
        .log-entry {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            font-family: monospace;
            font-size: 0.9em;
        }
        
        .log-success { color: #28a745; }
        .log-error { color: #dc3545; }
        .log-warning { color: #ffc107; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔗 Google Integration Dashboard</h1>
            <p>Connect your Teacher Evaluation System with Google Sheets and Google Drive</p>
        </div>
        
        <div class="card">
            <h3>📊 System Status</h3>
            <div id="systemStatus">
                <p>Loading system status...</p>
            </div>
        </div>
        
        <div class="card">
            <h3>🔄 Data Synchronization</h3>
            <p>Synchronize student and teacher data from Google Sheets:</p>
            <button class="btn" onclick="testConnection()">Test Google Connection</button>
            <button class="btn btn-success" onclick="syncData()">Sync Data from Google Sheets</button>
            <div id="syncResult" style="margin-top: 15px;"></div>
        </div>
        
        <div class="card">
            <h3>📄 Report Generation</h3>
            <p>Generate evaluation reports and save to Google Drive:</p>
            <button class="btn btn-success" onclick="generateReports()">Generate All Reports to Google Drive</button>
            <div id="reportResult" style="margin-top: 15px;"></div>
        </div>
        
        <div class="card">
            <h3>💾 Backup & Restore</h3>
            <button class="btn" onclick="createBackup()">Create Database Backup</button>
            <button class="btn" onclick="listBackups()">View Available Backups</button>
            <div id="backupResult" style="margin-top: 15px;"></div>
        </div>
        
        <div class="card">
            <h3>📈 System Statistics</h3>
            <div id="systemStats">
                <p>Loading statistics...</p>
            </div>
        </div>
        
        <div class="card">
            <h3>📋 Activity Log</h3>
            <div id="activityLog" style="max-height: 300px; overflow-y: auto;">
                <p>Loading activity log...</p>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="admin.php" class="btn">← Back to Admin Dashboard</a>
        </div>
    </div>

    <script>
        // Load initial data
        document.addEventListener('DOMContentLoaded', function() {
            loadSystemStatus();
            loadSystemStats();
            loadActivityLog();
        });
        
        async function loadSystemStatus() {
            try {
                const response = await fetch('google_integration_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=system_status'
                });
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('systemStatus').innerHTML = `
                        <p><span class="status-indicator ${data.database_ok ? 'status-online' : 'status-offline'}"></span> Database: ${data.database_ok ? 'Online' : 'Offline'}</p>
                        <p><span class="status-indicator ${data.sheets_ok ? 'status-online' : 'status-offline'}"></span> Google Sheets: ${data.sheets_ok ? 'Online' : 'Offline'}</p>
                        <p><span class="status-indicator ${data.drive_ok ? 'status-online' : 'status-offline'}"></span> Google Drive: ${data.drive_ok ? 'Online' : 'Offline'}</p>
                        <p><strong>Last Check:</strong> ${data.timestamp}</p>
                    `;
                }
            } catch (error) {
                document.getElementById('systemStatus').innerHTML = '<p class="log-error">Error loading system status</p>';
            }
        }
        
        async function testConnection() {
    const resultDiv = document.getElementById('syncResult');
    resultDiv.innerHTML = '<p class="log-warning">Testing connection...</p>';
    
    try {
        const response = await fetch('debug_google_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=test_connection'
        });
        
        // Log the raw response
        const responseText = await response.text();
        console.log('Raw response:', responseText);
        
        try {
            const data = JSON.parse(responseText);
            console.log('Parsed JSON:', data);
            
            if (data.success) {
                resultDiv.innerHTML = `<p class="log-success">✅ Connection successful!</p>`;
            } else {
                resultDiv.innerHTML = `<p class="log-error">❌ Connection failed: ${data.error}</p>`;
            }
        } catch (parseError) {
            resultDiv.innerHTML = `
                <p class="log-error">❌ JSON Parse Error: ${parseError.message}</p>
                <pre style="background:#f5f5f5;padding:10px;font-size:12px;overflow:auto;">${responseText}</pre>
            `;
        }
    } catch (error) {
        resultDiv.innerHTML = `<p class="log-error">❌ Network Error: ${error.message}</p>`;
    }
}
        async function syncData() {
            const resultDiv = document.getElementById('syncResult');
            resultDiv.innerHTML = '<p class="log-warning">Synchronizing data...</p>';
            
            try {
                const response = await fetch('google_integration_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=sync_data'
                });
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <p class="log-success">✅ Data sync completed!</p>
                        <p>Students: ${data.students.message}</p>
                        <p>Teachers: ${data.teachers.message}</p>
                    `;
                    loadSystemStats(); // Refresh stats
                    loadActivityLog(); // Refresh activity log
                } else {
                    resultDiv.innerHTML = `<p class="log-error">❌ Sync failed: ${data.error}</p>`;
                }
            } catch (error) {
                resultDiv.innerHTML = `<p class="log-error">❌ Error: ${error.message}</p>`;
            }
        }
        
        async function generateReports() {
            const resultDiv = document.getElementById('reportResult');
            resultDiv.innerHTML = '<p class="log-warning">Generating reports...</p>';
            
            try {
                const response = await fetch('google_integration_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=generate_reports'
                });
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <p class="log-success">✅ Reports generated successfully!</p>
                        <p>Teachers Processed: ${data.teachers_processed}</p>
                        <p>Individual Reports: ${data.individual_reports}</p>
                        <p>Summary Reports: ${data.summary_reports}</p>
                        <p>Folders Created: ${data.folders_created}</p>
                    `;
                    loadActivityLog(); // Refresh activity log
                } else {
                    resultDiv.innerHTML = `<p class="log-error">❌ Report generation failed: ${data.error}</p>`;
                }
            } catch (error) {
                resultDiv.innerHTML = `<p class="log-error">❌ Error: ${error.message}</p>`;
            }
        }
        
        async function loadSystemStats() {
            try {
                const response = await fetch('google_integration_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_stats'
                });
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('systemStats').innerHTML = `
                        <p>📊 Evaluations: ${data.evaluations}</p>
                        <p>⭐ Average Rating: ${data.avg_rating}/5.0</p>
                        <p>👥 Students: ${data.students}</p>
                        <p>👨‍🏫 Teachers: ${data.teachers}</p>
                        <p>📈 Completion Rate: ${data.completion_rate}%</p>
                        <p>💾 Database Size: ${data.db_size}</p>
                    `;
                }
            } catch (error) {
                document.getElementById('systemStats').innerHTML = '<p class="log-error">Error loading statistics</p>';
            }
        }
        
        async function loadActivityLog() {
            try {
                const response = await fetch('google_integration_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_activity_log'
                });
                const data = await response.json();
                
                if (data.success) {
                    const logHTML = data.activities.map(activity => 
                        `<div class="log-entry log-${activity.status}">
                            [${activity.timestamp}] ${activity.action}: ${activity.description}
                         </div>`
                    ).join('');
                    document.getElementById('activityLog').innerHTML = logHTML;
                }
            } catch (error) {
                document.getElementById('activityLog').innerHTML = '<p class="log-error">Error loading activity log</p>';
            }
        }
        
        async function createBackup() {
            const resultDiv = document.getElementById('backupResult');
            resultDiv.innerHTML = '<p class="log-warning">Creating backup...</p>';
            
            try {
                const response = await fetch('google_integration_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=create_backup'
                });
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <p class="log-success">✅ Backup created successfully!</p>
                        <p>File: ${data.backup_file}</p>
                        <p>Size: ${data.file_size}</p>
                    `;
                    loadActivityLog();
                } else {
                    resultDiv.innerHTML = `<p class="log-error">❌ Backup failed: ${data.error}</p>`;
                }
            } catch (error) {
                resultDiv.innerHTML = `<p class="log-error">❌ Error: ${error.message}</p>`;
            }
        }
    </script>
</body>
</html>
