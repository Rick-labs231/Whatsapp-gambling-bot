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

        // Users table with wallet, bank, and banned status
        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                whatsapp_id TEXT UNIQUE,
                username TEXT,
                wallet INTEGER DEFAULT 1000,
                bank INTEGER DEFAULT 0,
                banned INTEGER DEFAULT 0,
                created_at TEXT
            );
        ");

        // Assets table (shop items)
        $db->exec("
            CREATE TABLE IF NOT EXISTS assets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT UNIQUE,
                daily_income INTEGER,
                cost INTEGER,
                description TEXT
            );
        ");

        // User assets (what they own)
        $db->exec("
            CREATE TABLE IF NOT EXISTS user_assets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                asset_id INTEGER,
                quantity INTEGER DEFAULT 1,
                UNIQUE(user_id, asset_id),
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (asset_id) REFERENCES assets(id)
            );
        ");

        // Daily income tracking
        $db->exec("
            CREATE TABLE IF NOT EXISTS daily_income (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                amount INTEGER,
                claimed_at TEXT,
                FOREIGN KEY (user_id) REFERENCES users(id)
            );
        ");

        // Cooldowns
        $db->exec("
            CREATE TABLE IF NOT EXISTS cooldowns (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                command TEXT,
                last_used INTEGER,
                UNIQUE(user_id, command)
            );
        ");

        // Dice challenges
        $db->exec("
            CREATE TABLE IF NOT EXISTS dice_challenges (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                challenger_id INTEGER,
                challenged_id INTEGER,
                amount INTEGER,
                status TEXT DEFAULT 'pending',
                challenger_roll INTEGER,
                challenged_roll INTEGER,
                created_at TEXT,
                expires_at TEXT,
                FOREIGN KEY (challenger_id) REFERENCES users(id),
                FOREIGN KEY (challenged_id) REFERENCES users(id)
            );
        ");

        // Loans
        $db->exec("
            CREATE TABLE IF NOT EXISTS loans (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                amount INTEGER,
                interest_rate REAL DEFAULT 0.1,
                asset_id INTEGER,
                status TEXT DEFAULT 'active',
                created_at TEXT,
                due_at TEXT,
                repaid_at TEXT,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (asset_id) REFERENCES assets(id)
            );
        ");

        // Insert default assets (shop items)
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM assets");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['count'] == 0) {
                $assets = [
                    ['Small Kiosk', 1000, 2000, 'A small shop that generates income'],
                    ['Mini Mart', 5000, 10000, 'A convenience store'],
                    ['Supermarket', 25000, 50000, 'A large supermarket'],
                    ['Restaurant', 100000, 200000, 'A popular restaurant'],
                    ['Shopping Mall', 500000, 1000000, 'A commercial shopping complex'],
                    ['Tech Company', 5000000, 10000000, 'A software company'],
                    ['Black Hat Company', 100000000, 200000000, 'Underground tech enterprise'],
                ];

                $stmt = $db->prepare("INSERT INTO assets (name, daily_income, cost, description) VALUES (?, ?, ?, ?)");
                foreach ($assets as $asset) {
                    $stmt->execute($asset);
                }
            }
        } catch (PDOException $e) {
            // Table might already be populated
        }
    }
}
