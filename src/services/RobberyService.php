<?php

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/CooldownService.php';

class RobberyService
{
    public static function rob(string $robberWhatsapp, string $targetUsername): string
    {
        $db = Database::connect();

        $robber = User::findByWhatsappId($robberWhatsapp);
        if (!$robber) {
            return "You are not registered.";
        }

        $target = User::findByUsername($targetUsername);
        if (!$target) {
            return "Target not found.";
        }

        if ($robber['id'] === $target['id']) {
            return "You can’t rob yourself.";
        }

        if ($robber['balance'] < 200) {
            return "You need at least 200 coins to attempt a robbery.";
        }

        // cooldown check
        $cooldownMsg = CooldownService::check(
            $robber['id'],
            'rob',
            3600
        );

        if ($cooldownMsg) {
            return $cooldownMsg;
        }

        $success = random_int(1, 100) <= 30;

        try {
            $db->beginTransaction();

            if ($success) {
                $maxSteal = (int) ($target['balance'] * 0.5);
                $minSteal = max(1, (int) ($target['balance'] * 0.2));
                $stolen = random_int($minSteal, max($minSteal, $maxSteal));

                $db->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")
                   ->execute([$stolen, $target['id']]);

                $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")
                   ->execute([$stolen, $robber['id']]);

                $message = "Robbery successful! You stole {$stolen} coins from {$target['username']}.";
            } else {
                $penalty = 150;

                $db->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")
                   ->execute([$penalty, $robber['id']]);

                $message = "Robbery failed! You lost {$penalty} coins.";
            }

            CooldownService::set($robber['id'], 'rob');
            $db->commit();

            return $message;

        } catch (Exception $e) {
            $db->rollBack();
            return "Robbery failed due to system error.";
        }
    }
}
