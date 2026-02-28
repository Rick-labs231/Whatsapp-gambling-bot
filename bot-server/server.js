const express = require('express');
const cors = require('cors');
const fs = require('fs');
const path = require('path');
const axios = require('axios');
const {
    default: makeWASocket,
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion,
} = require('@whiskeysockets/baileys');
const qrcode = require('qrcode-terminal');
const pino = require('pino');

const app = express();
app.use(cors());
app.use(express.json());

// Configuration
const PORT = process.env.PORT || 3000;
const sessionsDir = path.join(__dirname, 'sessions');
const AUTH_FILE = path.join(sessionsDir, 'creds.json');

// Create sessions directory if it doesn't exist
if (!fs.existsSync(sessionsDir)) {
    fs.mkdirSync(sessionsDir, { recursive: true });
}

// Logger
const logger = pino.default({ level: 'info' });

// Global variables
let sock = null;
let qrCodeData = null;
let connectionStatus = 'disconnected';
let botNumber = null;

/**
 * Initialize WhatsApp connection
 */
async function connectToWhatsApp() {
    const { state, saveCreds } = await useMultiFileAuthState(sessionsDir);
    
    try {
        const { version } = await fetchLatestBaileysVersion();

        sock = makeWASocket({
            version,
            logger,
            printQRInTerminal: false,
            browser: ['Ubuntu', 'Chrome', '120.0.6099.129'],
            auth: state,
            defaultQueryTimeoutMs: undefined,
        });

        /**
         * Handle QR Code
         */
        sock.ev.on('connection.update', async (update) => {
            const { connection, lastDisconnect, qr } = update;

            if (qr) {
                qrCodeData = qr;
                console.log('\n📱 QR Code Generated! Please scan with your WhatsApp phone.');
                qrcode.generate(qr, { small: true });
            }

            if (connection === 'open') {
                connectionStatus = 'connected';
                botNumber = sock.user?.id?.replace(/:.*@.*/, '');
                console.log('\n✅ WhatsApp Connected Successfully!');
                console.log(`Bot Number: ${botNumber}`);
            }

            if (connection === 'close') {
                const shouldRetry = 
                    (lastDisconnect?.error)?.output?.statusCode !==
                    DisconnectReason.loggedOut;

                connectionStatus = 'disconnected';
                console.log(
                    'connection closed due to ',
                    lastDisconnect.error,
                    ', reconnecting ',
                    shouldRetry
                );

                if (shouldRetry) {
                    connectToWhatsApp();
                } else {
                    console.log('⚠️ WhatsApp logged out. Please authenticate again.');
                }
            }
        });

        /**
         * Handle incoming messages
         */
        sock.ev.on('messages.upsert', async (m) => {
            try {
                const message = m.messages[0];

                if (!message.message) return;
                if (message.key.fromMe) return;

                const chat = message.key.remoteJid;
                const messageText = 
                    message.message.conversation ||
                    message.message.extendedTextMessage?.text ||
                    '';
                
                // Extract sender's name from WhatsApp
                const senderName = message.pushName || chat.split('@')[0] || 'User';

                // Check if this is a reply to another message
                let repliedTo = null;
                const contextInfo = message.message.extendedTextMessage?.contextInfo;
                
                if (contextInfo && contextInfo.quotedMessage) {
                    // This message is replying to another message
                    const quotedMessage = contextInfo.quotedMessage;
                    const quotedSenderJid = contextInfo.participant || chat;
                    
                    repliedTo = {
                        jid: quotedSenderJid,
                        participantName: message.message.extendedTextMessage?.contextInfo?.quotedMessage?.text?.substring(0, 50) || 'Unknown',
                    };
                    
                    console.log(`📨 New Reply from ${senderName} to ${quotedSenderJid}`);
                } else {
                    console.log(`\n📨 New Message from ${senderName} (${chat}):`);
                }
                
                console.log(`Message: ${messageText}`);

                // Process message through PHP
                try {
                    const phpResponse = await axios.post('http://localhost/gamble%20bot/public/api.php?action=process_message', {
                        from: chat,
                        message: messageText,
                        username: senderName,
                        repliedTo: repliedTo, // Pass reply info if this is a reply
                    });

                    if (phpResponse.data && phpResponse.data.data && phpResponse.data.data.botResponse) {
                        const botResponse = phpResponse.data.data.botResponse;
                        
                        console.log('✅ Response from bot');

                        // Wait a bit before sending response
                        await new Promise(resolve => setTimeout(resolve, 1000));

                        // Send response back to WhatsApp
                        console.log(`📤 Sending response to ${senderName}...`);
                        await sock.sendMessage(chat, { text: botResponse });
                        console.log(`✅ Response sent!\n`);
                    } else {
                        console.error('❌ No bot response received from PHP');
                    }

                } catch (error) {
                    console.error('❌ Error processing message:', error.message);
                    // Try to send error message
                    try {
                        await new Promise(resolve => setTimeout(resolve, 500));
                        await sock.sendMessage(chat, { text: '⚠️ Error processing message. Please try again.' });
                    } catch (e) {
                        console.error('Failed to send error message');
                    }
                }
            } catch (error) {
                console.error('❌ Error handling message:', error);
            }
        });

        /**
         * Handle credentials update
         */
        sock.ev.on('creds.update', saveCreds);

    } catch (error) {
        console.error('WhatsApp Connection Error:', error);
        setTimeout(connectToWhatsApp, 5000);
    }
}

/**
 * API Routes
 */

// Status endpoint
app.get('/status', (req, res) => {
    res.json({
        status: connectionStatus,
        botNumber: botNumber,
        timestamp: new Date(),
    });
});

// Get QR Code
app.get('/qr', (req, res) => {
    if (!qrCodeData) {
        return res.status(404).json({
            error: 'QR Code not available. Server may already be connected.',
        });
    }
    res.json({ qr: qrCodeData });
});

// Send message endpoint
app.post('/send-message', async (req, res) => {
    try {
        const { to, message } = req.body;

        if (!to || !message) {
            return res.status(400).json({
                error: 'Missing "to" or "message" parameter',
            });
        }

        if (connectionStatus !== 'connected' || !sock) {
            return res.status(503).json({
                error: 'WhatsApp connection is not active',
            });
        }

        // Format phone number if needed
        let formattedTo = to;
        if (!to.includes('@')) {
            formattedTo = to.replace(/\D/g, '') + '@s.whatsapp.net';
        }

        // Add small delay to prevent rate limiting
        await new Promise(resolve => setTimeout(resolve, 500));

        await sock.sendMessage(formattedTo, { text: message });

        console.log(`✅ Message sent to ${formattedTo}`);

        res.json({
            success: true,
            message: `Message sent to ${to}`,
            timestamp: new Date(),
        });
    } catch (error) {
        console.error('Send message error:', error);
        res.status(500).json({
            error: error.message || 'Failed to send message',
        });
    }
});

// Reset connection (logout)
app.post('/logout', async (req, res) => {
    try {
        if (sock) {
            await sock.logout();
        }

        // Delete credentials
        if (fs.existsSync(AUTH_FILE)) {
            fs.unlinkSync(AUTH_FILE);
        }

        connectionStatus = 'disconnected';
        qrCodeData = null;
        botNumber = null;

        res.json({
            success: true,
            message: 'Logged out successfully. Please reconnect.',
        });
    } catch (error) {
        res.status(500).json({
            error: error.message || 'Failed to logout',
        });
    }
});

// Health check
app.get('/health', (req, res) => {
    res.json({ ok: true });
});

/**
 * Start Server
 */
async function startServer() {
    try {
        // Check if already authenticated
        if (fs.existsSync(AUTH_FILE)) {
            console.log('📱 Found existing session. Connecting...');
        } else {
            console.log('📱 No session found. Waiting for QR Code scan...');
        }

        // Connect to WhatsApp
        await connectToWhatsApp();

        // Start Express server
        app.listen(PORT, () => {
            console.log(`\n✅ Server running on http://localhost:${PORT}`);
            console.log('Endpoints:');
            console.log(`  GET  /status - Check connection status`);
            console.log(`  GET  /qr - Get QR code`);
            console.log(`  POST /send-message - Send WhatsApp message`);
            console.log(`  POST /logout - Logout and reset`);
            console.log(`  GET  /health - Health check\n`);
        });
    } catch (error) {
        console.error('Failed to start server:', error);
        process.exit(1);
    }
}

// Start the server
startServer();

// Graceful shutdown
process.on('SIGINT', async () => {
    console.log('\n\nGracefully shutting down...');
    if (sock) {
        try {
            await sock.end();
        } catch (e) {
            // Ignore
        }
    }
    process.exit(0);
});
