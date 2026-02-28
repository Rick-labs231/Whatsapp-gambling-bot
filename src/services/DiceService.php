<?php

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../models/User.php';

/**
 * Dice Challenge Game
 * Two players roll dice, highest number wins all
 * Player has 30 seconds to accept or decline challenge
 */
class DiceService
{
    private const CHALLENGE_TIMEOUT = 30; // 30 seconds

    /**
     * Initiate a dice challenge to another player
     */
    public static function challenge(string $whatsappId, string $challengedJid, int $amount): string
    {
        $db = Database::connect();
        $challenger = User::findByWhatsappId($whatsappId);
        $challenged = User::findByWhatsappId($challengedJid);

        // Validation
        if (!$challenger) {
            return "❌ You are not registered. Use .register";
        }

        if (!$challenged) {
            return "❌ Player not found.";
        }

        if ($challenger['banned']) {
            return "⛔ You are banned from playing.";
        }

        if ($challenged['banned']) {
            return "❌ That player is banned.";
        }

        if ($amount <= 0) {
            return "❌ Bet amount must be greater than zero.";
        }

        if ($challenger['wallet'] < $amount) {
            return "❌ You don't have enough coins. You have {$challenger['wallet']} coins.";
        }

        if ($challenged['wallet'] < $amount) {
            return "❌ The challenged player doesn't have enough coins. They have {$challenged['wallet']} coins.";
        }

        // Create challenge record
        $stmt = $db->prepare("INSERT INTO dice_challenges (challenger_id, challenged_id, amount, status, created_at, expires_at) VALUES (?, ?, ?, 'pending', datetime('now'), datetime('now', '+30 seconds'))");
        $stmt->execute([$challenger['id'], $challenged['id'], $amount]);

        $challengerName = $challenger['username'] ?? explode('@', $whatsappId)[0];
        return "🎲 DICE CHALLENGE INITIATED! 🎲\n\nYou challenged this player to a dice duel!\n\nBet: {$amount} coins\n\n⏳ They have 30 seconds to accept or decline.\n\nWaiting for response...";
    }

    /**
     * Accept a pending challenge
     */
    public static function accept(string $whatsappId, string $challengerJid, int $challengeId): string
    {
        $db = Database::connect();
        $player = User::findByWhatsappId($whatsappId);
        $challenger = User::findByWhatsappId($challengerJid);

        if (!$player || !$challenger) {
            return "❌ Player not found.";
        }

        // Fetch challenge
        $stmt = $db->prepare("SELECT * FROM dice_challenges WHERE id = ? AND challenged_id = ? AND status = 'pending'");
        $stmt->execute([$challengeId, $player['id']]);
        $challenge = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$challenge) {
            return "❌ Challenge not found or already processed.";
        }

        // Check if challenge expired
        if (strtotime($challenge['expires_at']) < time()) {
            $stmt = $db->prepare("UPDATE dice_challenges SET status = 'expired' WHERE id = ?");
            $stmt->execute([$challengeId]);
            return "❌ Challenge has expired.";
        }

        // Check if players still have enough coins
        if ($challenger['wallet'] < $challenge['amount'] || $player['wallet'] < $challenge['amount']) {
            $stmt = $db->prepare("UPDATE dice_challenges SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$challengeId]);
            return "❌ One or both players don't have enough coins anymore.";
        }

        // Both players roll
        $challengerRoll = random_int(1, 6);
        $playerRoll = random_int(1, 6);

        // Deduct from both wallets
        User::removeWallet($challenger['id'], $challenge['amount']);
        User::removeWallet($player['id'], $challenge['amount']);

        $display = "🎲 \u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550 🎲\n";
        $display .= "      DICE CHALLENGE - BOTH ROLLING!\n";
        $display .= "🎲 \u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550 🎲\n\n";

        $challengerName = $challenger['username'] ?? 'Challenger';
        $playerName = $player['username'] ?? 'You';

        $display .= "🎯 {$challengerName} rolled: [{$challengerRoll}]\n";
        $display .= "🎯 {$playerName} rolled: [{$playerRoll}]\n\n";

        if ($challengerRoll > $playerRoll) {
            // Challenger wins
            $winAmount = $challenge['amount'] * 2;
            User::addWallet($challenger['id'], $winAmount);
            $display .= "🏆 {$challengerName} WINS! 🏆\n\n";
            $display .= "Winner gets: +{$winAmount} coins 💰\n";
            $status = 'won_challenger';
        } elseif ($playerRoll > $challengerRoll) {
            // Player (defender) wins
            $winAmount = $challenge['amount'] * 2;
            User::addWallet($player['id'], $winAmount);
            $display .= "🏆 {$playerName} WINS! 🏆\n\n";
            $display .= "Winner gets: +{$winAmount} coins 💰\n";
            $status = 'won_challenged';
        } else {
            // Draw
            $display .= "🤝 IT'S A DRAW! 🤝\n\n";
            $display .= "Both get their coins back!\n";
            User::addWallet($challenger['id'], $challenge['amount']);
            User::addWallet($player['id'], $challenge['amount']);
            $status = 'draw';
        }

        // Update challenge status
        $stmt = $db->prepare("UPDATE dice_challenges SET status = ?, challenger_roll = ?, challenged_roll = ? WHERE id = ?");
        $stmt->execute([$status, $challengerRoll, $playerRoll, $challengeId]);

        return $display;
    }

    /**
     * Decline a challenge
     */
    public static function decline(string $whatsappId, int $challengeId): string
    {
        $db = Database::connect();
        $player = User::findByWhatsappId($whatsappId);

        if (!$player) {
            return "❌ Player not found.";
        }

        // Fetch challenge
        $stmt = $db->prepare("SELECT * FROM dice_challenges WHERE id = ? AND challenged_id = ? AND status = 'pending'");
        $stmt->execute([$challengeId, $player['id']]);
        $challenge = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$challenge) {
            return "❌ Challenge not found or already processed.";
        }

        // Update status
        $stmt = $db->prepare("UPDATE dice_challenges SET status = 'declined' WHERE id = ?");
        $stmt->execute([$challengeId]);

        return "❌ You declined the dice challenge.";
    }
}
