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
            INSERT INTO users (whatsapp_id, username, wallet, bank, banned, created_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $whatsappId,
            $username,
            1000, // starting wallet coins
            0,    // no starting bank
            0,    // not banned
            date('Y-m-d H:i:s')
        ]);

        return self::findByWhatsappId($whatsappId);
    }

    public static function findByUsername(string $username): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    public static function top(int $limit = 10): array
    {
        $db = Database::connect();
        $stmt = $db->prepare("
            SELECT username, wallet, bank, 
            (wallet + bank + COALESCE((SELECT SUM(a.cost * ua.quantity) FROM user_assets ua JOIN assets a ON ua.asset_id = a.id WHERE ua.user_id = users.id), 0)) as networth
            FROM users 
            WHERE banned = 0
            ORDER BY networth DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get user's networth (wallet + bank + assets value)
     */
    public static function getNetworth(int $userId): int
    {
        $db = Database::connect();
        $stmt = $db->prepare("
            SELECT 
                u.wallet + u.bank + COALESCE((SELECT SUM(a.cost * ua.quantity) FROM user_assets ua JOIN assets a ON ua.asset_id = a.id WHERE ua.user_id = u.id), 0) as networth
            FROM users u
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['networth'] ?? 0);
    }

    /**
     * Add money to wallet
     */
    public static function addWallet(int $userId, int $amount): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare("UPDATE users SET wallet = wallet + ? WHERE id = ?");
        return $stmt->execute([$amount, $userId]);
    }

    /**
     * Remove money from wallet
     */
    public static function removeWallet(int $userId, int $amount): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare("UPDATE users SET wallet = wallet - ? WHERE id = ? AND wallet >= ?");
        return $stmt->execute([$amount, $userId, $amount]);
    }

    /**
     * Add money to bank
     */
    public static function addBank(int $userId, int $amount): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare("UPDATE users SET bank = bank + ? WHERE id = ?");
        return $stmt->execute([$amount, $userId]);
    }

    /**
     * Remove money from bank
     */
    public static function removeBank(int $userId, int $amount): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare("UPDATE users SET bank = bank - ? WHERE id = ? AND bank >= ?");
        return $stmt->execute([$amount, $userId, $amount]);
    }

    /**
     * Transfer between wallet and bank
     */
    public static function transferToBank(int $userId, int $amount): bool
    {
        if (!self::removeWallet($userId, $amount)) {
            return false;
        }
        return self::addBank($userId, $amount);
    }

    public static function transferToWallet(int $userId, int $amount): bool
    {
        if (!self::removeBank($userId, $amount)) {
            return false;
        }
        return self::addWallet($userId, $amount);
    }

    /**
     * Ban/Unban user
     */
    public static function ban(int $userId): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare("UPDATE users SET banned = 1 WHERE id = ?");
        return $stmt->execute([$userId]);
    }

    public static function unban(int $userId): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare("UPDATE users SET banned = 0 WHERE id = ?");
        return $stmt->execute([$userId]);
    }

    /**
     * Get all assets
     */
    public static function getAllAssets(): array
    {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM assets ORDER BY cost ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buy asset
     */
    public static function buyAsset(int $userId, int $assetId): bool
    {
        $db = Database::connect();
        
        // Get asset details
        $stmt = $db->prepare("SELECT * FROM assets WHERE id = ?");
        $stmt->execute([$assetId]);
        $asset = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$asset) {
            return false;
        }

        $user = $db->prepare("SELECT * FROM users WHERE id = ?")->fetchObject();
        
        // Check if user has money
        $stmt = $db->prepare("SELECT wallet FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userWallet = $stmt->fetch(PDO::FETCH_ASSOC)['wallet'];
        
        if ($userWallet < $asset['cost']) {
            return false;
        }

        // Deduct from wallet
        self::removeWallet($userId, $asset['cost']);

        // Add/update user asset
        $stmt = $db->prepare("SELECT id FROM user_assets WHERE user_id = ? AND asset_id = ?");
        $stmt->execute([$userId, $assetId]);
        $userAsset = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($userAsset) {
            $stmt = $db->prepare("UPDATE user_assets SET quantity = quantity + 1 WHERE user_id = ? AND asset_id = ?");
        } else {
            $stmt = $db->prepare("INSERT INTO user_assets (user_id, asset_id, quantity) VALUES (?, ?, 1)");
        }

        return $stmt->execute([$userId, $assetId]);
    }

    /**
     * Get user assets
     */
    public static function getUserAssets(int $userId): array
    {
        $db = Database::connect();
        $stmt = $db->prepare("
            SELECT a.id, a.name, a.daily_income, a.cost, ua.quantity
            FROM user_assets ua
            JOIN assets a ON ua.asset_id = a.id
            WHERE ua.user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calculate daily income from assets
     */
    public static function calculateDailyIncome(int $userId): int
    {
        $assets = self::getUserAssets($userId);
        $total = 0;
        foreach ($assets as $asset) {
            $total += $asset['daily_income'] * $asset['quantity'];
        }
        return $total;
    }

    /**
     * Claim daily income
     */
    public static function claimDailyIncome(int $userId): int
    {
        $db = Database::connect();

        // Check if already claimed today
        $stmt = $db->prepare("
            SELECT amount FROM daily_income 
            WHERE user_id = ? AND DATE(claimed_at) = DATE('now')
        ");
        $stmt->execute([$userId]);
        if ($stmt->fetch()) {
            return 0; // Already claimed
        }

        // Calculate daily income
        $income = self::calculateDailyIncome($userId);

        if ($income > 0) {
            // Add to bank
            self::addBank($userId, $income);

            // Record claim
            $stmt = $db->prepare("INSERT INTO daily_income (user_id, amount, claimed_at) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $income, date('Y-m-d H:i:s')]);
        }

        return $income;
    }
}

