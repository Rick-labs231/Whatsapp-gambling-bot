<?php

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../models/User.php';

/**
 * Coin Flip Game
 * 50/50 chance - pick heads or tails, double your money if correct
 */
class CoinFlipService
{
    public static function play(string $whatsappId, string $choice, int $amount): string
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

        if ($amount <= 0) {
            return "❌ Bet amount must be greater than zero.";
        }

        if ($user['wallet'] < $amount) {
            return "❌ Insufficient wallet balance. You have {$user['wallet']} coins.";
        }

        // Normalize choice
        $choice = strtolower(trim($choice));
        if (!in_array($choice, ['heads', 'h', 'tails', 't'])) {
            return "❌ Invalid choice! Use: .cf h 100 or .cf t 100\n(h = heads, t = tails)";
        }

        // Convert to boolean
        $playerChoice = in_array($choice, ['heads', 'h']);
        $playerChoiceText = $playerChoice ? 'Heads' : 'Tails';

        // Deduct bet
        User::removeWallet($user['id'], $amount);

        // Flip coin (50/50)
        $result = (random_int(0, 1) === 1);
        $resultText = $result ? 'Heads' : 'Tails';

        $display = "\n🪙 ═══════════════════════════════ 🪙\n";
        $display .= "         FLIPPING THE COIN...\n";
        $display .= "🪙 ═══════════════════════════════ 🪙\n\n";

        if ($playerChoice === $result) {
            // Won!
            $winAmount = $amount * 2;
            User::addWallet($user['id'], $winAmount);
            $display .= "🎪 The coin landed on: {$resultText} 🎪\n\n";
            $display .= "✅✅✅ YOU WIN! ✅✅✅\n";
            $display .= "You chose {$playerChoiceText} - CORRECT!\n";
            $display .= "Doubled your money! +{$winAmount} coins 🎉\n";
        } else {
            // Lost
            $display .= "🎪 The coin landed on: {$resultText} 🎪\n\n";
            $display .= "❌❌❌ YOU LOST! ❌❌❌\n";
            $display .= "You chose {$playerChoiceText} - WRONG!\n";
            $display .= "Better luck next time! -{$amount} coins\n";
            $winAmount = 0;
        }

        $finalBalance = $user['wallet'] - $amount + $winAmount;
        $display .= "━━━━━━━━━━━━━━━━━━━━━◆━━━━━━━━━━━━━━━━━━━━━\n";
        $display .= "💰 New Wallet Balance: {$finalBalance} coins\n";
        $display .= "━━━━━━━━━━━━━━━━━━━━━◆━━━━━━━━━━━━━━━━━━━━━";

        return $display;
    }
}
