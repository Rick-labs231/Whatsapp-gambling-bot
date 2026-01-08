<?php

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../models/User.php';

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

        if ($user['balance'] < $amount) {
            return "Insufficient balance.";
        }

        // 50/50 chance
        $win = random_int(0, 1) === 1;

        if ($win) {
            $newBalance = $user['balance'] + $amount;
            $message = "You won! +{$amount} coins.";
        } else {
            $newBalance = $user['balance'] - $amount;
            $message = "You lost! -{$amount} coins.";
        }

        $stmt = $db->prepare("UPDATE users SET balance = ? WHERE id = ?");
        $stmt->execute([$newBalance, $user['id']]);

        return $message . " New balance: {$newBalance}";
    }
}
