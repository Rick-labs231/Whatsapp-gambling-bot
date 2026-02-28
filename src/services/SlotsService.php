<?php

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../models/User.php';

/**
 * Slots Game
 * 3 reels, various multipliers for matching symbols
 */
class SlotsService
{
    private static $symbols = ['🍎', '🍊', '🍋', '🍓', '💎'];

    public static function play(string $whatsappId, int $amount): string
    {
        $db = Database::connect();
        $user = User::findByWhatsappId($whatsappId);

        // Validation
        if (!$user) {
            return "You are not registered. Use .register";
        }

        if ($user['banned']) {
            return "You are banned from playing.";
        }

        if ($amount <= 0) {
            return "Bet amount must be greater than zero.";
        }

        if ($user['wallet'] < $amount) {
            return "❌ Insufficient wallet balance. You have {$user['wallet']} coins.";
        }

        // Deduct bet from wallet
        User::removeWallet($user['id'], $amount);

        // Spin 3 reels
        $reel1 = self::$symbols[random_int(0, count(self::$symbols) - 1)];
        $reel2 = self::$symbols[random_int(0, count(self::$symbols) - 1)];
        $reel3 = self::$symbols[random_int(0, count(self::$symbols) - 1)];

        // Display reels
        $display = "🎰 SLOTS 🎰\n\n";
        $display .= "┌─────────────┐\n";
        $display .= "│ {$reel1}  {$reel2}  {$reel3} │\n";
        $display .= "└─────────────┘\n\n";

        // Calculate winnings
        $multiplier = 0;

        if ($reel1 === $reel2 && $reel2 === $reel3) {
            // All three match - JACKPOT!
            $multiplier = 50; // Triple match = 50x jackpot
            $display .= "🎊 " . str_repeat($reel1, 3) . " TRIPLE MATCH! JACKPOT! " . str_repeat($reel1, 3) . " 🎊\n\n";
            $display .= "🤑 YOU WON 50x JACKPOT!\n";
        } elseif ($reel1 === $reel2 || $reel2 === $reel3 || $reel1 === $reel3) {
            // Two match = 2x win
            $multiplier = 2;
            $display .= "✨ TWO MATCHES! ✨\n\n";
            $display .= "🎉 YOU WON 2x!\n";
        } else {
            // No match - lose
            $display .= "\n❌ NO MATCHES!\n\n";
            $display .= "Better luck next time!\n";
        }

        if ($multiplier > 0) {
            $winAmount = $amount * $multiplier;
            User::addWallet($user['id'], $winAmount);
            $display .= "You won {$winAmount} coins!\n";
        } else {
            $winAmount = 0;
            $display .= "You lost -{$amount} coins!\n";
        }

        $newWallet = $user['wallet'] - $amount + $winAmount;
        $display .= "\n━━━━━━━━━━━━━━━━━━━━━\n";
        $display .= "💰 New Balance: {$newWallet} coins\n";
        $display .= "━━━━━━━━━━━━━━━━━━━━━";

        return $display;
    }
}
