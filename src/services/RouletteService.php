<?php

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../models/User.php';

/**
 * Roulette Game
 * Player picks a color and bet amount
 * Red: 2x, Black: 2x, Gold: 100x
 */
class RouletteService
{
    public static function play(string $whatsappId, string $color, int $amount): string
    {
        $db = Database::connect();
        $user = User::findByWhatsappId($whatsappId);

        // Validation
        if (!$user) {
            return "❌ You are not registered. Use .register";
        }

        if ($user['banned']) {
            return "⛔ You are banned from playing.";
        }

        // Normalize color
        $color = strtolower(trim($color));
        if (!in_array($color, ['red', 'r', 'black', 'b', 'gold', 'g'])) {
            return "❌ Invalid color! Use: .roulette red 100, .roulette black 100, or .roulette gold 100";
        }

        if ($amount <= 0) {
            return "❌ Bet amount must be greater than zero.";
        }

        if ($user['wallet'] < $amount) {
            return "❌ Insufficient wallet balance. You have {$user['wallet']} coins.";
        }

        // Convert color shorthand
        $colorMap = ['red' => 'red', 'r' => 'red', 'black' => 'black', 'b' => 'black', 'gold' => 'gold', 'g' => 'gold'];
        $playerBet = $colorMap[$color];

        // Deduct bet
        User::removeWallet($user['id'], $amount);

        // Spin the wheel (40% red, 40% black, 20% gold)
        $spin = random_int(1, 100);
        
        $display = "\n🎡 ═══════════════════════════════ 🎡\n";
        $display .= "        ROULETTE WHEEL SPINNING...\n";
        $display .= "🎡 ═══════════════════════════════ 🎡\n\n";

        if ($spin <= 40) {
            $wheelResult = 'red';
            $wheelEmoji = '🔴';
        } elseif ($spin <= 80) {
            $wheelResult = 'black';
            $wheelEmoji = '⚫';
        } else {
            $wheelResult = 'gold';
            $wheelEmoji = '✨';
        }

        // Determine win or loss
        if ($playerBet === $wheelResult) {
            if ($wheelResult === 'gold') {
                $multiplier = 100;
                $winAmount = $amount * $multiplier;
                $display .= "🎰 {$wheelEmoji} THE BALL LANDED ON GOLD! 🎰\n\n";
                $display .= "✨✨✨ JACKPOT WINNER!!! ✨✨✨\n";
                $display .= "You betted on GOLD and WON {$multiplier}x!\n\n";
            } else {
                $multiplier = 2;
                $winAmount = $amount * $multiplier;
                $betColor = ucfirst($playerBet);
                $display .= "{$wheelEmoji} THE BALL LANDED ON {$betColor.strtoupper()}! {$wheelEmoji}\n\n";
                $display .= "🎉 WINNER! 🎉\n";
                $display .= "You betted on {$betColor} and WON {$multiplier}x!\n\n";
            }
            User::addWallet($user['id'], $winAmount);
        } else {
            $betColor = ucfirst($playerBet);
            $display .= "{$wheelEmoji} THE BALL LANDED ON " . strtoupper($wheelResult) . "! {$wheelEmoji}\n\n";
            $display .= "❌ YOU LOST!\n";
            $display .= "You betted on {$betColor} but lost -{$amount} coins.\n\n";
            $winAmount = 0;
        }

        $finalBalance = $user['wallet'] - $amount + $winAmount;
        $display .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $display .= "💰 New Wallet Balance: {$finalBalance} coins\n";
        $display .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━";

        return $display;
    }
}
