<?php

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../models/User.php';

/**
 * Profile Service
 * Display user profile with all account info
 */
class ProfileService
{
    public static function getProfile(string $whatsappId): string
    {
        $user = User::findByWhatsappId($whatsappId);

        if (!$user) {
            return "❌ You are not registered. Use .register";
        }

        $db = Database::connect();

        // Get user assets
        $stmt = $db->prepare("
            SELECT a.name, ua.quantity, a.daily_income
            FROM user_assets ua
            JOIN assets a ON ua.asset_id = a.id
            WHERE ua.user_id = ?
        ");
        $stmt->execute([$user['id']]);
        $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $assetCount = count($assets);
        $totalIncome = 0;
        foreach ($assets as $asset) {
            $totalIncome += ($asset['daily_income'] * $asset['quantity']);
        }

        // Get active loans
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM loans WHERE user_id = ? AND status = 'active'");
        $stmt->execute([$user['id']]);
        $loanCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        $profile = "╔════════════════════════════════╗\n";
        $profile .= "         👤 " . strtoupper($user['username']) . "\n";
        $profile .= "╚════════════════════════════════╝\n\n";

        $profile .= "💰 FINANCIALS\n";
        $profile .= "├─ Wallet: 💵 {$user['wallet']} coins\n";
        $profile .= "├─ Bank: 🏦 {$user['bank']} coins\n";
        $profile .= "├─ Total Net Worth: 💎 {$user['networth']} coins\n\n";

        $profile .= "🏢 BUSINESS EMPIRE\n";
        $profile .= "├─ Businesses Owned: {$assetCount}\n";
        $profile .= "├─ Daily Passive Income: 📈 {$totalIncome} coins/day\n";
        $profile .= "├─ Active Loans: 📋 {$loanCount}\n\n";

        $profile .= "📊 ACCOUNT INFO\n";
        $profile .= "├─ Member Since: " . date('M d, Y', strtotime($user['created_at'])) . "\n";
        $profile .= "├─ Status: " . ($user['banned'] ? "⛔ BANNED" : "✅ ACTIVE") . "\n";

        $profile .= "\n💡 Tip: Use .daily to claim your rewards!";

        return $profile;
    }
}
