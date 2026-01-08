<?php

require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/services/GamblingService.php';
require_once __DIR__ . '/services/RewardService.php';
require_once __DIR__ . '/services/RobberyService.php';




class CommandRouter
{
    public static function handle(string $message, string $whatsappId, string $username): string
    {
        $parts = explode(' ', trim($message));
        $command = strtolower($parts[0]);

        switch ($command) {
            case '.register':
                return self::register($whatsappId, $username);

            case '.balance':
                return self::balance($whatsappId);

            case '.stake':
                $amount = intval($parts[1] ?? 0);
                return GamblingService::stake($whatsappId, $amount);

            case '.leaderboard':
                return self::leaderboard();

            case '.daily':
                return RewardService::daily($whatsappId);

            case '.rob':
                $target = $parts[1] ?? '';
                if (!$target) {
                    return "Usage: .rob username";
                }
                return RobberyService::rob($whatsappId, $target);                

            default:
                return "Unknown command.";
        }
    }

    private static function register(string $whatsappId, string $username): string
    {
        $user = User::findByWhatsappId($whatsappId);

        if ($user) {
            return "You are already registered.";
        }

        User::create($whatsappId, $username);
        return "Registration complete. You received 1000 coins.";
    }

    private static function balance(string $whatsappId): string
    {
        $user = User::findByWhatsappId($whatsappId);

        if (!$user) {
            return "You are not registered. Use .register";
        }

        return "Your balance: {$user['balance']} coins.";
    }

    private static function leaderboard(): string
    {
        $users = User::top(10);

        if (empty($users)) {
            return "No players yet.";
        }

        $output = "🏆 Leaderboard 🏆\n";

        foreach ($users as $index => $user) {
            $rank = $index + 1;
            $output .= "{$rank}. {$user['username']} — {$user['balance']} coins\n";
        }

        return trim($output);
    }
}
