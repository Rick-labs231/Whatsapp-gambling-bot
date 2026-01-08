<?php

class Database
{
    private static ?PDO $pdo = null;

    public static function connect(): PDO
    {
        if (self::$pdo === null) {
            $dbPath = __DIR__ . '/../storage/gamble.sqlite';
            self::$pdo = new PDO('sqlite:' . $dbPath);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }

        return self::$pdo;
    }

    public static function migrate(): void
    {
        $db = self::connect();

        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                whatsapp_id TEXT UNIQUE,
                username TEXT,
                balance INTEGER DEFAULT 0,
                created_at TEXT
            );

        ");

        $db->exec("
        CREATE TABLE IF NOT EXISTS cooldowns (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            command TEXT,
            last_used INTEGER,
            UNIQUE(user_id, command)
        )
      ");

    
    }
}
