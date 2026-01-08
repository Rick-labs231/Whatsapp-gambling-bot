<?php

require_once __DIR__ . '/../Database.php';

class CooldownService
{
    public static function check(int $userId, string $command, int $seconds): ?string
    {
        $db = Database::connect();
        $now = time();

        $stmt = $db->prepare("
            SELECT last_used FROM cooldowns
            WHERE user_id = ? AND command = ?
        ");
        $stmt->execute([$userId, $command]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && ($now - $row['last_used']) < $seconds) {
            $remaining = $seconds - ($now - $row['last_used']);
            $minutes = ceil($remaining / 60);
            return "Cooldown active. Try again in {$minutes} minutes.";
        }

        return null;
    }

    public static function set(int $userId, string $command): void
    {
        $db = Database::connect();
        $now = time();

        $db->prepare("
            INSERT INTO cooldowns (user_id, command, last_used)
            VALUES (?, ?, ?)
            ON CONFLICT(user_id, command)
            DO UPDATE SET last_used = excluded.last_used
        ")->execute([$userId, $command, $now]);
    }
}
