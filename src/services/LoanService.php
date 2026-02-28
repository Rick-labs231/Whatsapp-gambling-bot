<?php

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../models/User.php';

/**
 * Loan Service
 * Borrow money against assets
 */
class LoanService
{
    private const INTEREST_RATE = 0.1; // 10%
    private const LOAN_DURATION_HOURS = 24;

    public static function viewLoans(string $whatsappId): string
    {
        $user = User::findByWhatsappId($whatsappId);

        if (!$user) {
            return "❌ You are not registered. Use .register";
        }

        $db = Database::connect();

        // Get user assets
        $stmt = $db->prepare("
            SELECT a.id, a.name, a.daily_income, ua.quantity
            FROM user_assets ua
            JOIN assets a ON ua.asset_id = a.id
            WHERE ua.user_id = ?
            ORDER BY a.daily_income DESC
        ");
        $stmt->execute([$user['id']]);
        $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($assets)) {
            return "❌ You have no assets to borrow against!\nUse .shop and .buy to get businesses first.";
        }

        $output = "╔════════════════════════════════╗\n";
        $output .= "        💳 LOAN OPTIONS\n";
        $output .= "╚════════════════════════════════╝\n\n";
        $output .= "You can borrow against your businesses:\n\n";

        $index = 1;
        foreach ($assets as $asset) {
            $maxBorrow = $asset['daily_income'] * 30; // Can borrow 30x daily income
            $output .= "{$index}. {$asset['name']}\n";
            $output .= "   └─ Daily Income: {$asset['daily_income']} coins\n";
            $output .= "   └─ Max Borrow: {$maxBorrow} coins\n\n";
            $index++;
        }

        $output .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $output .= "Usage: .loan <asset_name> <amount>\n";
        $output .= "Example: .loan \"Small Kiosk\" 5000\n\n";
        $output .= "⚠️ Interest: " . (self::INTEREST_RATE * 100) . "%\n";
        $output .= "⚠️ Due in: " . self::LOAN_DURATION_HOURS . " hours\n";
        $output .= "⚠️ If unpaid: You lose the asset!";

        return $output;
    }

    public static function takeLoan(string $whatsappId, string $assetName, int $amount): string
    {
        $user = User::findByWhatsappId($whatsappId);

        if (!$user) {
            return "❌ You are not registered. Use .register";
        }

        if ($user['banned']) {
            return "⛔ You are banned from using this command.";
        }

        if ($amount <= 0) {
            return "❌ Loan amount must be greater than 0.";
        }

        $db = Database::connect();

        // Find asset
        $stmt = $db->prepare("
            SELECT a.id, a.name, a.daily_income
            FROM user_assets ua
            JOIN assets a ON ua.asset_id = a.id
            WHERE ua.user_id = ? AND a.name = ?
        ");
        $stmt->execute([$user['id'], $assetName]);
        $asset = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$asset) {
            return "❌ You don't own this asset!";
        }

        $maxBorrow = $asset['daily_income'] * 30;
        if ($amount > $maxBorrow) {
            return "❌ Loan amount too high!\nMax for this asset: {$maxBorrow} coins";
        }

        // Check for existing active loan
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM loans WHERE user_id = ? AND asset_id = ? AND status = 'active'");
        $stmt->execute([$user['id'], $asset['id']]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
            return "❌ You already have an active loan on this asset!";
        }

        // Create loan
        $interest = $amount * self::INTEREST_RATE;
        $totalRepay = $amount + $interest;
        $dueAt = date('Y-m-d H:i:s', time() + (self::LOAN_DURATION_HOURS * 3600));

        $stmt = $db->prepare(
            "INSERT INTO loans (user_id, amount, interest_rate, asset_id, status, created_at, due_at) 
             VALUES (?, ?, ?, ?, 'active', datetime('now'), ?)"
        );
        $stmt->execute([$user['id'], $amount, self::INTEREST_RATE, $asset['id'], $dueAt]);

        User::addWallet($user['id'], $amount);
        $newBalance = User::findByWhatsappId($whatsappId)['wallet'];

        $output = "✅ LOAN APPROVED!\n\n";
        $output .= "📋 Asset: {$asset['name']}\n";
        $output .= "💵 Amount: {$amount} coins\n";
        $output .= "📊 Interest (10%): {$interest} coins\n";
        $output .= "💰 MUST REPAY: {$totalRepay} coins\n";
        $output .= "⏰ Due: {$dueAt}\n\n";
        $output .= "New Wallet: {$newBalance} coins\n\n";
        $output .= "Use .payloan to repay!";

        return $output;
    }

    public static function repayLoan(string $whatsappId, int $amount): string
    {
        $user = User::findByWhatsappId($whatsappId);

        if (!$user) {
            return "❌ You are not registered. Use .register";
        }

        $db = Database::connect();

        // Get active loans
        $stmt = $db->prepare("SELECT * FROM loans WHERE user_id = ? AND status = 'active' ORDER BY created_at ASC LIMIT 1");
        $stmt->execute([$user['id']]);
        $loan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$loan) {
            return "❌ You have no active loans!";
        }

        // Check if loan expired
        if (strtotime($loan['due_at']) < time()) {
            // Loan expired, asset is seized
            $stmt = $db->prepare("UPDATE loans SET status = 'expired' WHERE id = ?");
            $stmt->execute([$loan['id']]);

            $stmt = $db->prepare("DELETE FROM user_assets WHERE user_id = ? AND asset_id = ?");
            $stmt->execute([$user['id'], $loan['asset_id']]);

            $stmt = $db->prepare("SELECT name FROM assets WHERE id = ?");
            $stmt->execute([$loan['asset_id']]);
            $asset = $stmt->fetch(PDO::FETCH_ASSOC);

            return "❌ LOAN EXPIRED!\n\nYour {$asset['name']} has been seized!";
        }

        $totalOwed = $loan['amount'] + ($loan['amount'] * $loan['interest_rate']);

        if ($amount < $totalOwed) {
            return "❌ Insufficient repayment!\nYou owe: {$totalOwed} coins\nYou provided: {$amount} coins";
        }

        if ($user['wallet'] < $amount) {
            return "❌ You don't have enough coins!\nYou have: {$user['wallet']} coins";
        }

        // Repay
        User::removeWallet($user['id'], $amount);
        $stmt = $db->prepare("UPDATE loans SET status = 'repaid', repaid_at = datetime('now') WHERE id = ?");
        $stmt->execute([$loan['id']]);

        $newBalance = User::findByWhatsappId($whatsappId)['wallet'];

        return "✅ LOAN REPAID!\n\n" .
            "💵 Amount: {$amount} coins\n" .
            "New Balance: {$newBalance} coins\n" .
            "Your asset is safe!";
    }
}
