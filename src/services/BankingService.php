<?php

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../models/User.php';

/**
 * Banking Service
 * Allows users to deposit and withdraw money safely
 */
class BankingService
{
    public static function deposit(string $whatsappId, int $amount): string
    {
        $user = User::findByWhatsappId($whatsappId);

        if (!$user) {
            return "You are not registered. Use .register";
        }

        if ($amount <= 0) {
            return "Deposit amount must be greater than zero.";
        }

        if ($user['wallet'] < $amount) {
            return "❌ Insufficient wallet balance. You have {$user['wallet']} coins.";
        }

        // Transfer from wallet to bank
        if (User::transferToBank($user['id'], $amount)) {
            $newUser = User::findByWhatsappId($whatsappId);
            return "💼 BANK DEPOSIT\n\nDeposited: {$amount} coins\nWallet: {$newUser['wallet']} coins\nBank: {$newUser['bank']} coins";
        }

        return "Error processing deposit.";
    }

    public static function withdraw(string $whatsappId, int $amount): string
    {
        $user = User::findByWhatsappId($whatsappId);

        if (!$user) {
            return "You are not registered. Use .register";
        }

        if ($amount <= 0) {
            return "Withdraw amount must be greater than zero.";
        }

        if ($user['bank'] < $amount) {
            return "❌ Insufficient bank balance. You have {$user['bank']} coins.";
        }

        // Transfer from bank to wallet
        if (User::transferToWallet($user['id'], $amount)) {
            $newUser = User::findByWhatsappId($whatsappId);
            return "🏦 BANK WITHDRAWAL\n\nWithdrawn: {$amount} coins\nWallet: {$newUser['wallet']} coins\nBank: {$newUser['bank']} coins";
        }

        return "Error processing withdrawal.";
    }

    public static function getBalance(string $whatsappId): string
    {
        $user = User::findByWhatsappId($whatsappId);

        if (!$user) {
            return "You are not registered. Use .register";
        }

        $networth = User::getNetworth($user['id']);

        $output = "💰 ACCOUNT BALANCE\n\n";
        $output .= "Wallet: {$user['wallet']} coins\n";
        $output .= "Bank: {$user['bank']} coins (Safe from theft)\n";
        $output .= "Net Worth: {$networth} coins\n";

        return $output;
    }
}
