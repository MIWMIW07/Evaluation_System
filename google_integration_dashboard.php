<?php
//google_integration_dashboard.php
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
            display: inline-block;
            text-decoration: none;
        }
        
        .btn:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
        }
        
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        
        .btn-warning { background: #ffc107; color: #333; }
        .btn-warning:hover { background: #e0a800; }
        
        .btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }
        
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
        
        .result-warning { 
            background: #fff3cd; 
            border-left-color: #ffc107; 
            color: #856404;
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
        
        .loading {
            color: #6c757d;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔗 Google Integration Dashboard</h1>
            <p>Connect your Teacher Evaluation System with Google Sheets and Google Drive</p>
        </div>
        
        <!-- Debug Mode Toggle -->
        <div class="card">
            <h3>🔧 Debug Mode</h3>
            <p>Use debug mode to troubleshoot connection issues:</p>
            <button class="btn btn-warning" onclick="toggleDebugMode()">
                <span id="debugModeText">Enable Debug Mode</span>
            </button>
            <div id="debugInfo" style="display: none; margin-top: 15px;">
                <p><strong>Debug mode provides detailed error information and diagnostics.</strong></p>
            </div>
        </div>
        
        <div class="card">
            <h3>📊 System Status</h3>
            <div id="systemStatus">
                <p class="loading">Loading system status...</p>
            </div>
            <button class="btn" onclick="refreshSystemStatus()">Refresh Status</button>
        </div>
        
        <div class="card">
            <h3>🔄 Data Synchronization</h3>
            <p>Synchronize student and teacher data from Google Sheets:</p>
            <button class="btn" onclick="testConnection()">Test Google Connection</button>
            <button class="btn btn-success" onclick="syncData()">Sync Data from Google Sheets</button>
            <div id="syncResult"></div>
        </div>
        
        <div class="card">
            <h3>📄 Report Generation</h3>
            <p>Generate evaluation reports and save to Google Drive:</p>
            <button class="btn btn-success" onclick="generateReports()">Generate All Reports to Google Drive</button>
            <div id="reportResult"></div>
        </div>

        <div class="card">
            <h3>🔍 Debug Report Location</h3>
            <p>Check where your reports are being saved:</p>
            <button class="btn btn-warning" onclick="debugReports()">Debug Report Location</button>
            <div id="debugReportResult"></div>
        </div>

        <div class="card">
    <h3>🐛 Debug & Troubleshooting</h3>
    <p>Diagnose report generation issues:</p>
    <button class="btn btn-warning" onclick="runDetailedDebug()">Run Detailed Debug</button>
    <button class="btn" onclick="testSingleReport()">Test Single Report</button>
    <div id="debugResults"></div>
</div>

        
        <div class="card">
            <h3>💾 Backup & Restore</h3>
            <button class="btn" onclick="createBackup()">Create Database Backup</button>
            <button class="btn" onclick="listBackups()">View Available Backups</button>
            <div id="backupResult"></div>
        </div>
        
        <div class="card">
            <h3>📈 System Statistics</h3>
            <div id="systemStats">
                <p class="loading">Loading statistics...</p>
            </div>
            <button class="btn" onclick="refreshStats()">Refresh Statistics</button>
        </div>
        
        <div class="card">
            <h3>📋 Activity Log</h3>
            <div id="activityLog" style="max-height: 300px; overflow-y: auto;">
                <p class="loading">Loading activity log...</p>
            </div>
            <button class="btn" onclick="refreshActivityLog()">Refresh Log</button>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="admin.php" class="btn">← Back to Admin Dashboard</a>
        </div>
    </div>

    <script>
        let debugMode = false;
        
        // Load initial data
        document.addEventListener('DOMContentLoaded', function() {
            loadSystemStatus();
            loadSystemStats();
            loadActivityLog();
        });
        
        function toggleDebugMode() {
            debugMode = !debugMode;
            const debugText = document.getElementById('debugModeText');
            const debugInfo = document.getElementById('debugInfo');
            
            if (debugMode) {
                debugText.textContent = 'Disable Debug Mode';
                debugInfo.style.display = 'block';
            } else {
                debugText.textContent = 'Enable Debug Mode';
                debugInfo.style.display = 'none';
            }
        }
        
        function getApiEndpoint() {
            return debugMode ? 'debug_google_api.php' : 'google_integration_api.php';
        }
        
        function showResult(elementId, data, loading = false) {
            const element = document.getElementById(elementId);
            
            if (loading) {
                element.innerHTML = '<p class="loading">Processing...</p>';
                return;
            }
            
            let resultClass = 'result-box ';
            let icon = '';
            
            if (data.success) {
                resultClass += 'result-success';
                icon = '✅ ';
            } else {
                resultClass += 'result-error';
                icon = '❌ ';
            }
            
            let html = `<div class="${resultClass}">`;
            
            if (data.success) {
                html += `<p>${icon}${data.message || 'Operation completed successfully!'}</p>`;
                
                // Show additional success data
                if (data.students) html += `<p>Students: ${data.students.message || JSON.stringify(data.students)}</p>`;
                if (data.teachers) html += `<p>Teachers: ${data.teachers.message || JSON.stringify(data.teachers)}</p>`;
                if (data.teachers_processed) html += `<p>Teachers Processed: ${data.teachers_processed}</p>`;
                if (data.individual_reports) html += `<p>Individual Reports: ${data.individual_reports}</p>`;
                if (data.summary_reports) html += `<p>Summary Reports: ${data.summary_reports}</p>`;
                if (data.backup_file) html += `<p>Backup File: ${data.backup_file}</p>`;
                if (data.file_size) html += `<p>File Size: ${data.file_size}</p>`;
                
            } else {
                html += `<p>${icon}${data.error || 'Operation failed'}</p>`;
            }
            
            // Show debug information if in debug mode
            if (debugMode && (data.debug_info || data.checks || data.environment || data.details)) {
                html += '<div class="debug-details">';
                html += '<strong>Debug Information:</strong><br>';
                
                if (data.debug_info) {
                    html += 'File: ' + data.debug_info.file + ', Line: ' + data.debug_info.line + '<br>';
                }
                
                if (data.checks) {
                    html += '<strong>System Checks:</strong><br>';
                    for (const [key, value] of Object.entries(data.checks)) {
                        html += `${key}: ${value ? '✅' : '❌'}<br>`;
                    }
                }
                
                if (data.environment) {
                    html += '<strong>Environment:</strong><br>';
                    for (const [key, value] of Object.entries(data.environment)) {
                        html += `${key}: ${value}<br>`;
                    }
                }
                
                if (data.details) {
                    html += '<strong>Details:</strong><br>';
                    for (const [key, value] of Object.entries(data.details)) {
                        html += `${key}: ${value}<br>`;
                    }
                }
                
                html += '</div>';
            }
            
            html += '</div>';
            element.innerHTML = html;
        }
        
        async function makeApiCall(action, resultElementId = null) {
            if (resultElementId) {
                showResult(resultElementId, {}, true);
            }
            
            try {
                const formData = new FormData();
                formData.append('action', action);
                
                const response = await fetch(getApiEndpoint(), {
                    method: 'POST',
                    body: formData
                });
                
                const responseText = await response.text();
                console.log('Raw response:', responseText);
                
                // Try to parse as JSON
                try {
                    const data = JSON.parse(responseText);
                    return data;
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    return {
                        success: false,
                        error: 'Invalid JSON response from server',
                        raw_response: responseText.substring(0, 500),
                        parse_error: parseError.message
                    };
                }
                
            } catch (error) {
                console.error('Network error:', error);
                return {
                    success: false,
                    error: 'Network error: ' + error.message
                };
            }
        }
        
        async function loadSystemStatus() {
            const data = await makeApiCall('system_status');
            
            if (data.success) {
                document.getElementById('systemStatus').innerHTML = `
                    <p><span class="status-indicator ${data.database_ok ? 'status-online' : 'status-offline'}"></span> Database: ${data.database_ok ? 'Online' : 'Offline'}</p>
                    <p><span class="status-indicator ${data.sheets_ok ? 'status-online' : 'status-offline'}"></span> Google Sheets: ${data.sheets_ok ? 'Online' : 'Offline'}</p>
                    <p><span class="status-indicator ${data.drive_ok ? 'status-online' : 'status-offline'}"></span> Google Drive: ${data.drive_ok ? 'Online' : 'Offline'}</p>
                    <p><strong>Last Check:</strong> ${data.timestamp}</p>
                `;
            } else {
                showResult('systemStatus', data);
            }
        }
        
        async function testConnection() {
            const data = await makeApiCall('test_connection', 'syncResult');
            showResult('syncResult', data);
        }
        
        async function syncData() {
            const data = await makeApiCall('sync_data', 'syncResult');
            showResult('syncResult', data);
            
            if (data.success) {
                // Refresh stats after successful sync
                loadSystemStats();
                loadActivityLog();
            }
        }
        
        async function generateReports() {
            const data = await makeApiCall('generate_reports', 'reportResult');
            showResult('reportResult', data);
            
            if (data.success) {
                loadActivityLog();
            }
        }
        
        async function loadSystemStats() {
    const data = await makeApiCall('get_stats');
    
    if (data.success) {
        document.getElementById('systemStats').innerHTML = `
            <p>📊 Evaluations: ${data.evaluations}</p>
            <p>⭐ Average Rating: ${data.avg_rating}/5.0</p>
            <p>👨‍🏫 Teacher Assignments: ${data.teacher_assignments}</p>
            <p>📈 Completion Rate: ${data.completion_rate}%</p>
            <p>🔗 System: Hybrid (Google Sheets + PostgreSQL)</p>
        `;
    } else {
        showResult('systemStats', data);
    }
}
        
        async function loadActivityLog() {
            const data = await makeApiCall('get_activity_log');
            
            if (data.success && data.activities) {
                const logHTML = data.activities.map(activity => 
                    `<div class="log-entry log-${activity.status}">
                        [${activity.timestamp}] ${activity.action}: ${activity.description}
                     </div>`
                ).join('');
                document.getElementById('activityLog').innerHTML = logHTML;
            } else {
                showResult('activityLog', data);
            }
        }
        
        async function createBackup() {
            const data = await makeApiCall('create_backup', 'backupResult');
            showResult('backupResult', data);
            
            if (data.success) {
                loadActivityLog();
            }
        }
        
        // Refresh functions
        function refreshSystemStatus() {
            loadSystemStatus();
        }
        
        function refreshStats() {
            loadSystemStats();
        }
        
        function refreshActivityLog() {
            loadActivityLog();
        }
        
        function listBackups() {
            const resultDiv = document.getElementById('backupResult');
            resultDiv.innerHTML = `
                <div class="result-box result-warning">
                    <p>⚠️ Backup listing feature not implemented yet.</p>
                    <p>Check the 'backups' folder in your project directory for backup files.</p>
                </div>
            `;
        }

        async function debugReports() {
    const resultDiv = document.getElementById('debugReportResult');
    resultDiv.innerHTML = '<p class="loading">Checking report location...</p>';
    
    try {
        const response = await fetch('debug_reports.php');
        const html = await response.text();
        resultDiv.innerHTML = `<div class="debug-details">${html}</div>`;
    } catch (error) {
        resultDiv.innerHTML = `<div class="result-box result-error">Error: ${error.message}</div>`;
    }
    }
async function runDetailedDebug() {
    const resultDiv = document.getElementById('debugResults');
    resultDiv.innerHTML = '<p class="loading">Running detailed debug...</p>';
    
    try {
        const response = await fetch('detailed_debug_reports.php');
        const html = await response.text();
        resultDiv.innerHTML = `<div class="debug-details">${html}</div>`;
    } catch (error) {
        resultDiv.innerHTML = `<div class="result-box result-error">Debug error: ${error.message}</div>`;
    }
}

async function testSingleReport() {
    const resultDiv = document.getElementById('debugResults');
    resultDiv.innerHTML = '<p class="loading">Testing single report generation...</p>';
    
    try {
        const response = await fetch('test_single_report.php');
        const html = await response.text();
        resultDiv.innerHTML = `<div class="debug-details">${html}</div>`;
    } catch (error) {
        resultDiv.innerHTML = `<div class="result-box result-error">Test error: ${error.message}</div>`;
    }
}
    </script>
</body>
</html>
