<?php

/**
 * WhatsApp Gamble Bot - PHP API
 * Handles communication between Node.js Baileys server and PHP code
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configuration
define('NODE_SERVER_URL', 'http://localhost:3000');
define('LOG_FILE', __DIR__ . '/../storage/bot.log');

// Create storage directory if it doesn't exist
if (!is_dir(__DIR__ . '/../storage')) {
    mkdir(__DIR__ . '/../storage', 0755, true);
}

/**
 * Log messages
 */
function logMessage($message, $data = [])
{
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message";

    if (!empty($data)) {
        $logEntry .= " | " . json_encode($data);
    }

    $logEntry .= "\n";

    if (is_writable(dirname(LOG_FILE))) {
        file_put_contents(LOG_FILE, $logEntry, FILE_APPEND);
    }
}

/**
 * Send JSON response
 */
function sendResponse($success, $message, $data = [])
{
    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s'),
    ];

    if (!empty($data)) {
        $response['data'] = $data;
    }

    echo json_encode($response);
    exit;
}

/**
 * Make HTTP request to Node.js server
 */
function nodeRequest($endpoint, $method = 'GET', $data = [])
{
    $url = NODE_SERVER_URL . $endpoint;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        logMessage("CURL Error on $endpoint", ['error' => $curlError]);
        return ['error' => $curlError, 'code' => 0];
    }

    return [
        'response' => json_decode($response, true),
        'code' => $httpCode,
    ];
}

/**
 * Get connection status
 */
function getStatus()
{
    $result = nodeRequest('/status');

    if (isset($result['error'])) {
        return sendResponse(false, 'Cannot connect to Node.js server', ['error' => $result['error']]);
    }

    $data = $result['response'];
    logMessage('Status check', $data);

    sendResponse(true, 'Connection status retrieved', $data);
}

/**
 * Get QR Code
 */
function getQrCode()
{
    $result = nodeRequest('/qr');

    if (isset($result['error'])) {
        return sendResponse(false, 'Cannot retrieve QR code', ['error' => $result['error']]);
    }

    if ($result['code'] === 404) {
        return sendResponse(false, 'QR Code not available - server may already be connected', []);
    }

    $data = $result['response'];
    logMessage('QR Code requested');

    sendResponse(true, 'QR Code retrieved', $data);
}

/**
 * Send message
 */
function sendMessage()
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['to']) || !isset($input['message'])) {
        logMessage('Send message failed', ['error' => 'Missing parameters']);
        return sendResponse(false, 'Missing "to" or "message" parameter');
    }

    $result = nodeRequest('/send-message', 'POST', [
        'to' => $input['to'],
        'message' => $input['message'],
    ]);

    if (isset($result['error'])) {
        logMessage('Send message error', ['error' => $result['error']]);
        return sendResponse(false, 'Failed to send message', ['error' => $result['error']]);
    }

    if ($result['code'] !== 200) {
        $errorMsg = $result['response']['error'] ?? 'Unknown error';
        logMessage('Send message failed', ['code' => $result['code'], 'error' => $errorMsg]);
        return sendResponse(false, 'Failed to send message', ['error' => $errorMsg]);
    }

    logMessage('Message sent', ['to' => $input['to']]);
    sendResponse(true, 'Message sent successfully', $result['response']);
}

/**
 * Process incoming message from Node.js
 */
function processMessage()
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['from']) || !isset($input['message'])) {
        return sendResponse(false, 'Invalid message data');
    }

    try {
        // Extract WhatsApp ID and username
        $whatsappId = $input['from'];
        $messageText = $input['message'];
        $username = $input['username'] ?? 'User';
        $repliedTo = $input['repliedTo'] ?? null;

        logMessage('Incoming message', [
            'from' => $whatsappId,
            'message' => $messageText,
            'repliedTo' => $repliedTo,
        ]);

        // Load and process through Bot
        require_once __DIR__ . '/../src/Bot.php';
        $bot = new Bot();
        $response = $bot->handleMessage($messageText, $whatsappId, $username, $repliedTo);

        logMessage('Response generated', [
            'to' => $whatsappId,
            'response' => substr($response, 0, 100)
        ]);

        // Return the response to Node.js so it can send it back
        sendResponse(true, 'Message processed successfully', [
            'botResponse' => $response,
            'to' => $whatsappId,
        ]);
    } catch (Exception $e) {
        logMessage('Error processing message', ['error' => $e->getMessage()]);
        sendResponse(false, 'Error processing message', ['error' => $e->getMessage()]);
    }
}

/**
 * Logout and reset
 */
function logout()
{
    $result = nodeRequest('/logout', 'POST');

    if (isset($result['error'])) {
        return sendResponse(false, 'Cannot connect to Node.js server');
    }

    logMessage('Bot logged out');
    sendResponse(true, 'Logged out successfully', $result['response']);
}

/**
 * Route requests
 */
// Get action from URL, POST data, or JSON body
$action = $_GET['action'] ?? $_POST['action'] ?? null;

// If not found, try JSON body
if (!$action) {
    $jsonInput = json_decode(file_get_contents('php://input'), true);
    $action = $jsonInput['action'] ?? null;
}

try {
    logMessage('API Request', ['action' => $action, 'method' => $_SERVER['REQUEST_METHOD']]);

    switch ($action) {
        case 'status':
            getStatus();
            break;

        case 'qr':
            getQrCode();
            break;

        case 'send-message':
            sendMessage();
            break;

        case 'process_message':
            processMessage();
            break;

        case 'logout':
            logout();
            break;

        default:
            sendResponse(false, 'Unknown action: ' . $action);
    }
} catch (Exception $e) {
    logMessage('API Error', ['error' => $e->getMessage()]);
    sendResponse(false, 'Internal server error', ['error' => $e->getMessage()]);
}
