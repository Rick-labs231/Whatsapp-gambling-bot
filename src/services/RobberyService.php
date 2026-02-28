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

        if ($robber['wallet'] < 100) {
            return "❌ You need at least 100 coins to attempt a robbery.";
        }

        if ($robber['banned']) {
            return "⛔ You are banned from playing!";
        }

        // cooldown check
        $cooldownMsg = CooldownService::check(
            $robber['id'],
            'rob',
            600
        );

        if ($cooldownMsg) {
            return $cooldownMsg;
        }

        // 30% success rate
        $success = random_int(1, 100) <= 30;

        try {
            $db->beginTransaction();

            if ($success) {
                // Only rob from wallet, bank is safe!
                $maxSteal = (int) ($target['wallet'] * 0.5);
                $minSteal = max(1, (int) ($target['wallet'] * 0.2));
                
                if ($maxSteal <= 0) {
                    $message = "🚨 Robbery failed! Target has no coins in wallet.";
                } else {
                    $stolen = random_int($minSteal, max($minSteal, $maxSteal));

                    User::removeWallet($target['id'], $stolen);
                    User::addWallet($robber['id'], $stolen);

                    $newWallet = $robber['wallet'] + $stolen;
                    $message = "💰 ROBBERY SUCCESSFUL!\n\n";
                    $message .= "You stole {$stolen} coins from {$target['username']}!\n";
                    $message .= "Your new wallet: {$newWallet} coins";
                }
            } else {
                // Failed robbery - lose money as failed attempt cost
                $maxPen = (int) ($robber['wallet'] * 0.65);
                $minPen = max(1, (int) ($robber['wallet'] * 0.35));
                $penalty = random_int($minPen, max($minPen, $maxPen));

                User::removeWallet($robber['id'], $penalty);

                $newWallet = $robber['wallet'] - $penalty;
                $message = "🚨 ROBBERY FAILED!\n\n";
                $message .= "You lost {$penalty} coins in the attempt.\n";
                $message .= "Your new wallet: {$newWallet} coins";
            }

            CooldownService::set($robber['id'], 'rob');
            $db->commit();

            return $message;

        } catch (Exception $e) {
            $db->rollBack();
            return "❌ Robbery failed due to system error: " . $e->getMessage();
        }
    }
}
