<?php

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/CooldownService.php';

class RewardService
{
    public static function daily(string $whatsappId): string
    {
        return self::claim(
            $whatsappId,
            'daily',
            86400,   // 24 hours
            300
        );
    }

    public static function weekly(string $whatsappId): string
    {
        return self::claim(
            $whatsappId,
            'weekly',
            604800, // 7 days
            1200
        );
    }

    public static function monthly(string $whatsappId): string
    {
        return self::claim(
            $whatsappId,
            'monthly',
            2592000, // ~30 days
            5000
        );
    }

    private static function claim(
        string $whatsappId,
        string $command,
        int $cooldownSeconds,
        int $reward
    ): string {
        $db = Database::connect();
        $user = User::findByWhatsappId($whatsappId);

        if (!$user) {
            return "You are not registered. Use .register";
        }

        // cooldown check
        $cooldownMsg = CooldownService::check(
            $user['id'],
            $command,
            $cooldownSeconds
        );

        if ($cooldownMsg) {
            return ucfirst($command) . " already claimed. " . $cooldownMsg;
        }

        try {
            $db->beginTransaction();

            $newBalance = $user['balance'] + $reward;

            $db->prepare(
                "UPDATE users SET balance = ? WHERE id = ?"
            )->execute([$newBalance, $user['id']]);

            CooldownService::set($user['id'], $command);

            $db->commit();

            return ucfirst($command) .
                   " reward claimed! +{$reward} coins. New balance: {$newBalance}";

        } catch (Exception $e) {
            $db->rollBack();
            return "Failed to claim {$command} reward.";
        }
    }
}
