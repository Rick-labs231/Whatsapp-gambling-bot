<?php

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../models/User.php';

/**
 * Shop Service
 * Handle buying assets that generate daily income
 */
class ShopService
{
    public static function viewShop(): string
    {
        $assets = User::getAllAssets();

        if (empty($assets)) {
            return "Shop is empty.";
        }

        $output = "🏪 ASSET SHOP 🏪\n\n";
        $output .= "Assets generate income daily! Buy them with wallet coins.\n\n";

        foreach ($assets as $asset) {
            $output .= "├─ {$asset['id']}. {$asset['name']}\n";
            $output .= "│  Daily Income: {$asset['daily_income']} coins\n";
            $output .= "│  Cost: {$asset['cost']} coins\n";
            $output .= "│  Description: {$asset['description']}\n\n";
        }

        $output .= "To buy an asset, use: .buy <asset_id>";

        return $output;
    }

    public static function buyAsset(string $whatsappId, int $assetId): string
    {
        $user = User::findByWhatsappId($whatsappId);

        if (!$user) {
            return "You are not registered. Use .register";
        }

        // Get asset
        $assets = User::getAllAssets();
        $asset = null;
        foreach ($assets as $a) {
            if ($a['id'] == $assetId) {
                $asset = $a;
                break;
            }
        }

        if (!$asset) {
            return "❌ Asset not found!";
        }

        if ($user['wallet'] < $asset['cost']) {
            return "❌ You don't have enough coins! Need {$asset['cost']}, you have {$user['wallet']}";
        }

        // Try to buy
        if (User::buyAsset($user['id'], $assetId)) {
            $newUser = User::findByWhatsappId($whatsappId);
            $dailyIncome = User::calculateDailyIncome($user['id']);

            return "✅ PURCHASE SUCCESSFUL!\n\n" .
                   "Asset: {$asset['name']}\n" .
                   "Cost: {$asset['cost']} coins\n" .
                   "Daily Income: +{$asset['daily_income']} coins/day\n\n" .
                   "Your new wallet: {$newUser['wallet']} coins\n" .
                   "Total daily income: {$dailyIncome} coins";
        }

        return "Error processing purchase.";
    }

    public static function viewAssets(string $whatsappId): string
    {
        $user = User::findByWhatsappId($whatsappId);

        if (!$user) {
            return "You are not registered. Use .register";
        }

        $userAssets = User::getUserAssets($user['id']);

        if (empty($userAssets)) {
            $dailyIncome = 0;
            $output = "🎁 YOUR ASSETS\n\n";
            $output .= "You don't own any assets yet.\n";
            $output .= "Use .shop to view available assets.";
        } else {
            $output = "🎁 YOUR ASSETS\n\n";

            foreach ($userAssets as $asset) {
                $totalIncome = $asset['daily_income'] * $asset['quantity'];
                $output .= "├─ {$asset['name']}\n";
                $output .= "│  Quantity: {$asset['quantity']}\n";
                $output .= "│  Daily Income: +{$totalIncome} coins/day\n\n";
            }

            $dailyIncome = User::calculateDailyIncome($user['id']);
            $output .= "💰 Total Daily Income: {$dailyIncome} coins";
        }

        return $output;
    }

    public static function claimDaily(string $whatsappId): string
    {
        $user = User::findByWhatsappId($whatsappId);

        if (!$user) {
            return "You are not registered. Use .register";
        }

        $income = User::claimDailyIncome($user['id']);

        if ($income === 0) {
            return "❌ You've already claimed your daily income!\n";
        }

        if ($income > 0) {
            return "✅ DAILY INCOME CLAIMED!\n\n" .
                   "Amount: +{$income} coins\n" .
                   "Added to your bank (safe from theft)\n" .
                   "Come back tomorrow for more!";
        }

        return "ℹ️ You don't own any assets yet. Purchase some in the shop!";
    }
}
