<?php

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../models/User.php';

/**
 * Admin Service
 * Only accessible by the admin (owner)
 */
class AdminService
{
    // Set this to your WhatsApp ID
    private static $ADMIN_ID = '120363423877801019@g.us'; // Admin WhatsApp group ID

    public static function isAdmin(string $whatsappId): bool
    {
        // Normalize the WhatsApp ID - remove domain parts (@s.whatsapp.net or @g.us)
        $normalizedId = preg_replace('/@.*/', '', $whatsappId);
        $normalizedAdminId = preg_replace('/@.*/', '', self::$ADMIN_ID);
        
        // Also trim + prefix if present
        $normalizedId = trim($normalizedId, '+');
        $normalizedAdminId = trim($normalizedAdminId, '+');
        
        $isMatch = $normalizedId === $normalizedAdminId;
        
        // Debug logging
        error_log("DEBUG: Admin check - Input: '{$whatsappId}', Normalized: '{$normalizedId}', Admin: '{$normalizedAdminId}', Match: " . ($isMatch ? 'YES' : 'NO'));
        
        return $isMatch;
    }

    public static function setAdmin(string $whatsappId): void
    {
        self::$ADMIN_ID = $whatsappId;
    }

    public static function addBalance(string $whatsappId, string $username, int $amount): string
    {
        $user = User::findByUsername($username);

        if (!$user) {
            return "❌ User '{$username}' not found.";
        }

        if ($amount < 0) {
            $user['wallet'] -= abs($amount);
            User::removeWallet($user['id'], abs($amount));
            $action = "removed";
        } else {
            User::addWallet($user['id'], $amount);
            $action = "added";
        }

        $newUser = User::findByWhatsappId($user['whatsapp_id']);
        return "✅ Admin Command Executed\n\n" .
               "User: {$username}\n" .
               "Amount {$action}: {$amount} coins\n" .
               "New Wallet Balance: {$newUser['wallet']} coins";
    }

    public static function ban(string $username): string
    {
        $user = User::findByUsername($username);

        if (!$user) {
            return "❌ User '{$username}' not found.";
        }

        if ($user['banned']) {
            return "ℹ️ User '{$username}' is already banned.";
        }

        User::ban($user['id']);
        return "✅ User '{$username}' has been banned.";
    }

    public static function unban(string $username): string
    {
        $user = User::findByUsername($username);

        if (!$user) {
            return "❌ User '{$username}' not found.";
        }

        if (!$user['banned']) {
            return "ℹ️ User '{$username}' is not banned.";
        }

        User::unban($user['id']);
        return "✅ User '{$username}' has been unbanned.";
    }

    public static function seizeWallet(string $username): string
    {
        $user = User::findByUsername($username);

        if (!$user) {
            return "❌ User '{$username}' not found.";
        }

        $amount = $user['wallet'];
        User::removeWallet($user['id'], $amount);

        return "✅ Admin Command Executed\n\n" .
               "User: {$username}\n" .
               "Seized from wallet: {$amount} coins\n" .
               "New Wallet Balance: 0 coins";
    }

    public static function seizeBank(string $username): string
    {
        $user = User::findByUsername($username);

        if (!$user) {
            return "❌ User '{$username}' not found.";
        }

        $amount = $user['bank'];
        User::removeBank($user['id'], $amount);

        return "✅ Admin Command Executed\n\n" .
               "User: {$username}\n" .
               "Seized from bank: {$amount} coins\n" .
               "New Bank Balance: 0 coins";
    }

    public static function seizeAssets(string $username): string
    {
        $db = Database::connect();
        $user = User::findByUsername($username);

        if (!$user) {
            return "❌ User '{$username}' not found.";
        }

        $assets = User::getUserAssets($user['id']);

        if (empty($assets)) {
            return "ℹ️ User '{$username}' has no assets to seize.";
        }

        // Delete all user assets
        $stmt = $db->prepare("DELETE FROM user_assets WHERE user_id = ?");
        $stmt->execute([$user['id']]);

        $assetCount = count($assets);
        return "✅ Admin Command Executed\n\n" .
               "User: {$username}\n" .
               "Seized {$assetCount} asset(s).";
    }

    public static function getStats(): string
    {
        $db = Database::connect();

        // Get total users
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users");
        $stmt->execute();
        $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Get total balance
        $stmt = $db->prepare("SELECT SUM(wallet + bank) as total FROM users");
        $stmt->execute();
        $totalBalance = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

        // Get most active players
        $topPlayers = User::top(5);

        $output = "📊 ADMIN STATISTICS\n\n";
        $output .= "Total Players: {$totalUsers}\n";
        $output .= "Total Balance: {$totalBalance} coins\n\n";

        $output .= "Top 5 Players:\n";
        foreach ($topPlayers as $index => $player) {
            $rank = $index + 1;
            $output .= "{$rank}. {$player['username']} - {$player['networth']} coins\n";
        }

        return $output;
    }

    public static function giveaway(): string
    {
        $db = Database::connect();
        
        // Get all non-banned users
        $stmt = $db->prepare("SELECT id, username FROM users WHERE banned = 0");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($users)) {
            return "❌ No eligible users for giveaway.";
        }

        $giveawayAmount = 1000;
        $userCount = count($users);
        $totalAmount = $giveawayAmount * $userCount;
        $output = "🎁🎁🎁 RANDOM GIVEAWAY! 🎁🎁🎁\n\n";
        $output .= "Give: {$giveawayAmount} coins to each player!\n\n";

        // Give to all players
        foreach ($users as $user) {
            User::addWallet($user['id'], $giveawayAmount);
        }

        $output .= "✅ Distributed {$totalAmount} coins to {$userCount} players!\n\n";
        $output .= "Everyone received: +{$giveawayAmount} coins 💰";

        return $output;
    }
}
