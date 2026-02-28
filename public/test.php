<?php

/**
 * WhatsApp Bot - Test Dashboard
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('NODE_SERVER_URL', 'http://localhost:3000');

function checkNodeServer()
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, NODE_SERVER_URL . '/health');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode === 200;
}

$nodeServerRunning = checkNodeServer();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Gamble Bot - Test Dashboard</title>
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
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 1.1em;
            opacity: 0.95;
        }

        .status-box {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
            vertical-align: middle;
        }

        .status-indicator.connected {
            background-color: #28a745;
            box-shadow: 0 0 10px rgba(40, 167, 69, 0.5);
        }

        .status-indicator.disconnected {
            background-color: #dc3545;
            box-shadow: 0 0 10px rgba(220, 53, 69, 0.5);
        }

        .section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .section h2 {
            color: #667eea;
            margin-bottom: 15px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }

        input,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 5px rgba(102, 126, 234, 0.3);
        }

        textarea {
            resize: vertical;
            height: 100px;
        }

        button {
            background: #667eea;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1em;
            transition: all 0.3s ease;
            margin-right: 10px;
        }

        button:hover {
            background: #764ba2;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(118, 75, 162, 0.3);
        }

        button:active {
            transform: translateY(0);
        }

        .button-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .response {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            margin-top: 15px;
            border-left: 4px solid #667eea;
            display: none;
        }

        .response.show {
            display: block;
        }

        .response pre {
            margin: 0;
            overflow-x: auto;
            font-size: 13px;
            line-height: 1.4;
        }

        .response.success {
            border-left-color: #28a745;
            background-color: #d4edda;
        }

        .response.error {
            border-left-color: #dc3545;
            background-color: #f8d7da;
        }

        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            color: #0c5460;
        }

        .qr-container {
            text-align: center;
            margin: 20px 0;
        }

        .qr-container img {
            max-width: 300px;
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 10px;
        }

        .loading {
            display: none;
            text-align: center;
            margin: 10px 0;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .alert.warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            color: #856404;
        }

        .alert.success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }

        .alert.danger {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>🤖 WhatsApp Gamble Bot</h1>
            <p>Test Dashboard & Connection Manager</p>
        </div>

        <!-- Status Section -->
        <div class="status-box">
            <h3>
                <span class="status-indicator <?php echo $nodeServerRunning ? 'connected' : 'disconnected'; ?>"></span>
                Node.js Server: <strong><?php echo $nodeServerRunning ? '🟢 Connected' : '🔴 Disconnected'; ?></strong>
            </h3>
            <?php if (!$nodeServerRunning): ?>
                <div class="alert warning" style="margin-top: 15px;">
                    <strong>⚠️ Node.js server not running!</strong><br>
                    Start the server with: <code>npm start</code> in the <code>bot-server</code> directory
                </div>
            <?php endif; ?>
        </div>

        <!-- Connection Status -->
        <div class="section">
            <h2>📱 Connection Status</h2>
            <button onclick="checkStatus()">Check Status</button>
            <div class="loading" id="statusLoading">
                <div class="spinner"></div>
            </div>
            <div class="response" id="statusResponse"></div>
        </div>

        <!-- QR Code -->
        <div class="section">
            <h2>📲 QR Code Scanner</h2>
            <p>Scan this QR code with your WhatsApp app to authenticate:</p>
            <button onclick="getQRCode()">Get QR Code</button>
            <div class="loading" id="qrLoading">
                <div class="spinner"></div>
            </div>
            <div id="qrContainer"></div>
            <div class="response" id="qrResponse"></div>
        </div>

        <!-- Send Test Message -->
        <div class="section">
            <h2>✉️ Send Test Message</h2>
            <div class="info-box">
                <strong>Instructions:</strong><br>
                1. Enter the recipient's phone number (with country code, e.g., +1234567890)<br>
                2. Enter your test message<br>
                3. Click "Send Message"
            </div>
            <div class="form-group">
                <label for="recipientPhone">Recipient Phone Number (with +):</label>
                <input type="text" id="recipientPhone" placeholder="+1234567890" value="">
            </div>
            <div class="form-group">
                <label for="messageText">Message:</label>
                <textarea id="messageText" placeholder="Enter your test message here...">Hello! This is a test message from WhatsApp Bot.</textarea>
            </div>
            <button onclick="sendMessage()">Send Message</button>
            <div class="loading" id="sendLoading">
                <div class="spinner"></div>
            </div>
            <div class="response" id="sendResponse"></div>
        </div>

        <!-- Logout -->
        <div class="section">
            <h2>⚙️ Settings</h2>
            <p>Clear session and start fresh authentication:</p>
            <button onclick="logoutBot()" style="background: #dc3545;">Logout & Reset</button>
            <div class="loading" id="logoutLoading">
                <div class="spinner"></div>
            </div>
            <div class="response" id="logoutResponse"></div>
        </div>

        <!-- Logs -->
        <div class="section">
            <h2>📋 Logs</h2>
            <button onclick="viewLogs()">View Recent Logs</button>
            <div class="response" id="logsResponse"></div>
        </div>
    </div>

    <script>
        const API_BASE_URL = '/gamble%20bot/public/api.php';

        function makeRequest(action, method = 'GET', data = null) {
            const url = `${API_BASE_URL}?action=${action}`;
            const options = {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                },
            };

            if (method === 'POST' && data) {
                options.body = JSON.stringify(data);
            }

            return fetch(url, options).then(res => res.json());
        }

        function showResponse(elementId, data, isSuccess = true) {
            const element = document.getElementById(elementId);
            element.innerHTML = `<pre>${JSON.stringify(data, null, 2)}</pre>`;
            element.className = `response show ${isSuccess ? 'success' : 'error'}`;
        }

        function showError(elementId, error) {
            const element = document.getElementById(elementId);
            element.innerHTML = `<pre>Error: ${error}</pre>`;
            element.className = 'response show error';
        }

        function toggleLoading(loadingId, show) {
            document.getElementById(loadingId).style.display = show ? 'block' : 'none';
        }

        async function checkStatus() {
            toggleLoading('statusLoading', true);
            try {
                const response = await makeRequest('status');
                toggleLoading('statusLoading', false);
                showResponse('statusResponse', response, response.success);
            } catch (error) {
                toggleLoading('statusLoading', false);
                showError('statusResponse', error.message);
            }
        }

        async function getQRCode() {
            toggleLoading('qrLoading', true);
            try {
                const response = await makeRequest('qr');
                toggleLoading('qrLoading', false);

                if (response.success && response.data && response.data.qr) {
                    const qrContainer = document.getElementById('qrContainer');
                    const qrValue = response.data.qr;

                    qrContainer.innerHTML = `
                        <div class="qr-container">
                            <p><strong>Scan this QR code with WhatsApp:</strong></p>
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(qrValue)}" alt="QR Code">
                            <p style="margin-top: 10px; font-size: 12px; color: #666;">
                                Make sure you're using your phone's WhatsApp to scan
                            </p>
                        </div>
                    `;
                }

                showResponse('qrResponse', response, response.success);
            } catch (error) {
                toggleLoading('qrLoading', false);
                showError('qrResponse', error.message);
            }
        }

        async function sendMessage() {
            const phone = document.getElementById('recipientPhone').value.trim();
            const message = document.getElementById('messageText').value.trim();

            if (!phone || !message) {
                alert('Please fill in all fields');
                return;
            }

            toggleLoading('sendLoading', true);
            try {
                const response = await makeRequest('send-message', 'POST', {
                    to: phone,
                    message: message,
                });
                toggleLoading('sendLoading', false);
                showResponse('sendResponse', response, response.success);

                if (response.success) {
                    document.getElementById('messageText').value = '';
                }
            } catch (error) {
                toggleLoading('sendLoading', false);
                showError('sendResponse', error.message);
            }
        }

        async function logoutBot() {
            if (!confirm('Are you sure? This will clear all authentication and require you to scan the QR code again.')) {
                return;
            }

            toggleLoading('logoutLoading', true);
            try {
                const response = await makeRequest('logout', 'POST');
                toggleLoading('logoutLoading', false);
                showResponse('logoutResponse', response, response.success);
            } catch (error) {
                toggleLoading('logoutLoading', false);
                showError('logoutResponse', error.message);
            }
        }

        async function viewLogs() {
            try {
                const response = await fetch('/gamble%20bot/storage/bot.log');
                const logs = await response.text();
                const element = document.getElementById('logsResponse');

                if (logs.trim()) {
                    element.innerHTML = `<pre>${logs.split('\n').reverse().slice(0, 50).join('\n')}</pre>`;
                } else {
                    element.innerHTML = '<pre>No logs yet</pre>';
                }

                element.className = 'response show';
            } catch (error) {
                showError('logsResponse', 'Could not load logs: ' + error.message);
            }
        }
    </script>
</body>

</html>