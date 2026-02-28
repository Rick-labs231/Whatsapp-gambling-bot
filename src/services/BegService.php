<?php

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../models/User.php';

/**
 * Beg Service
 * Small random income with cooldown
 */
class BegService
{
    private const COOLDOWN_MINUTES = 5;
    private const MIN_AMOUNT = 50;
    private const MAX_AMOUNT = 500;

    public static function beg(string $whatsappId): string
    {
        $user = User::findByWhatsappId($whatsappId);

        if (!$user) {
            return "❌ You are not registered. Use .register";
        }

        if ($user['banned']) {
            return "⛔ You are banned from using this command.";
        }

        $db = Database::connect();

        // Check cooldown
        $stmt = $db->prepare("SELECT last_used FROM cooldowns WHERE user_id = ? AND command = 'beg'");
        $stmt->execute([$user['id']]);
        $cooldown = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cooldown) {
            $lastUsed = $cooldown['last_used'];
            $elapsed = time() - $lastUsed;
            $remaining = (self::COOLDOWN_MINUTES * 60) - $elapsed;

            if ($remaining > 0) {
                $mins = ceil($remaining / 60);
                return "⏳ You're begging too fast!\nTry again in {$mins} minute(s).";
            }
        }

        // Random amount
        $amount = random_int(self::MIN_AMOUNT, self::MAX_AMOUNT);

        // Add to wallet
        User::addWallet($user['id'], $amount);

        // Update cooldown
        if ($cooldown) {
            $stmt = $db->prepare("UPDATE cooldowns SET last_used = ? WHERE user_id = ? AND command = 'beg'");
        } else {
            $stmt = $db->prepare("INSERT INTO cooldowns (user_id, command, last_used) VALUES (?, 'beg', ?)");
        }
        $stmt->execute([$time = time(), $user['id']]);

        $begs = [
            "A kind stranger gave you {$amount} coins! 🤑",
            "You found {$amount} coins on the street! 💵",
            "Someone tipped you {$amount} coins! 💰",
            "You got lucky and earned {$amount} coins! 🍀",
            "A friend lent you {$amount} coins! 👫",
            "You hustled and made {$amount} coins! 💪",
        ];

        $response = $begs[array_rand($begs)];
        $newBalance = $user['wallet'] + $amount;

        return "🙏 Begging...\n\n{$response}\n\nNew Balance: {$newBalance} coins";
    }
}
