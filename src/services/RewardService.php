<?php

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../models/User.php';

class RewardService
{
    public static function daily(string $whatsappId): string
    {
        $db = Database::connect();
        $user = User::findByWhatsappId($whatsappId);

        if (!$user) {
            return "You are not registered. Use .register";
        }

        $now = time();
        $cooldown = 86400; // 24 hours

        $stmt = $db->prepare("
            SELECT last_used FROM cooldowns
            WHERE user_id = ? AND command = 'daily'
        ");
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && ($now - $row['last_used']) < $cooldown) {
            $remaining = $cooldown - ($now - $row['last_used']);
            $hours = ceil($remaining / 3600);
            return "Daily already claimed. Try again in {$hours} hours.";
        }

        // reward
        $reward = 300;
        $newBalance = $user['balance'] + $reward;

        $db->prepare("UPDATE users SET balance = ? WHERE id = ?")
           ->execute([$newBalance, $user['id']]);

        // update cooldown
        $db->prepare("
            INSERT INTO cooldowns (user_id, command, last_used)
            VALUES (?, 'daily', ?)
            ON CONFLICT(user_id, command)
            DO UPDATE SET last_used = excluded.last_used
        ")->execute([$user['id'], $now]);

        return "Daily reward claimed! +{$reward} coins. New balance: {$newBalance}";
    }
}
