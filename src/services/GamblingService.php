<?php

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../models/User.php';

/**
 * Classic Gambling Service
 * 50/50 chance betting game
 */
class GamblingService
{
    public static function stake(string $whatsappId, int $amount): string
    {
        if ($amount <= 0) {
            return "Stake amount must be greater than zero.";
        }

        $db = Database::connect();
        $user = User::findByWhatsappId($whatsappId);

        if (!$user) {
            return "You are not registered. Use .register";
        }

        if ($user['banned']) {
            return "⛔ You are banned from playing!";
        }

        if ($user['wallet'] < $amount) {
            return "Insufficient wallet balance. You have {$user['wallet']} coins.";
        }

        // 50/50 chance with different outcomes
        $win = random_int(0, 7);

        User::removeWallet($user['id'], $amount);

        if ($win == 0) {
            $bonus = $amount;
            User::addWallet($user['id'], $bonus);
            $message = "🎭 CASINO - Even Money\n\nYou got even odds! +{$bonus} coins back.";
        } elseif ($win == 3) {
            $winAmount = $amount * 2;
            User::addWallet($user['id'], $winAmount);
            $message = "🎭 CASINO - 2x Win!\n\nDouble! Congrats! +{$winAmount} coins.";
        } elseif ($win == 7) {
            $winAmount = $amount * 15;
            User::addWallet($user['id'], $winAmount);
            $message = "🎭 CASINO - JACKPOT!!\n\n15x Multiplier! +{$winAmount} coins!!! 🎉";
        } else {
            $message = "🎭 CASINO - Lost\n\nYou lost! -{$amount} coins.";
        }

        $newUser = User::findByWhatsappId($whatsappId);
        return $message . "\n\nNew wallet balance: {$newUser['wallet']} coins";
    }
}

