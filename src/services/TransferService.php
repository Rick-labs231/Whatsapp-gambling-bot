<?php

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../models/User.php';

/**
 * Transfer Service
 * Send money to other players
 */
class TransferService
{
    public static function send(string $fromJid, string $toUsername, int $amount): string
    {
        $sender = User::findByWhatsappId($fromJid);
        $receiver = User::findByUsername($toUsername);

        // Validation
        if (!$sender) {
            return "❌ You are not registered. Use .register";
        }

        if (!$receiver) {
            return "❌ User '{$toUsername}' not found.";
        }

        if ($sender['id'] === $receiver['id']) {
            return "❌ You can't send money to yourself!";
        }

        if ($sender['banned']) {
            return "⛔ You are banned from using this command.";
        }

        if ($amount <= 0) {
            return "❌ Amount must be greater than 0.";
        }

        if ($sender['wallet'] < $amount) {
            return "❌ You don't have enough coins!\nYou have: {$sender['wallet']} coins";
        }

        // Transfer
        User::removeWallet($sender['id'], $amount);
        User::addWallet($receiver['id'], $amount);

        $senderNewBalance = User::findByWhatsappId($fromJid)['wallet'];
        $receiverNewBalance = $receiver['wallet'] + $amount;

        $message = "✅ TRANSFER COMPLETE!\n\n";
        $message .= "📤 You sent: {$amount} coins\n";
        $message .= "👤 To: {$receiver['username']}\n";
        $message .= "💰 Your Balance: {$senderNewBalance} coins\n";

        return $message;
    }
}
