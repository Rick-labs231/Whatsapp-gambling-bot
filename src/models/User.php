<?php

require_once __DIR__ . '/../Database.php';

class User
{
    public static function findByWhatsappId(string $whatsappId): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM users WHERE whatsapp_id = ?");
        $stmt->execute([$whatsappId]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    public static function create(string $whatsappId, string $username): array
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            INSERT INTO users (whatsapp_id, username, balance, created_at)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $whatsappId,
            $username,
            1000, // starting coins
            date('Y-m-d H:i:s')
        ]);

        return self::findByWhatsappId($whatsappId);
    }

    public static function top(int $limit = 10): array
    {
        $db = Database::connect();
        $stmt = $db->prepare("
            SELECT username, balance
            FROM users
            ORDER BY balance DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function findByUsername(string $username): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }


}
