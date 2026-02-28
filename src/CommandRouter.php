<?php

require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/services/GamblingService.php';
require_once __DIR__ . '/services/RouletteService.php';
require_once __DIR__ . '/services/SlotsService.php';
require_once __DIR__ . '/services/CoinFlipService.php';
require_once __DIR__ . '/services/BankingService.php';
require_once __DIR__ . '/services/ShopService.php';
require_once __DIR__ . '/services/AdminService.php';
require_once __DIR__ . '/services/RewardService.php';
require_once __DIR__ . '/services/RobberyService.php';
require_once __DIR__ . '/services/ProfileService.php';
require_once __DIR__ . '/services/BegService.php';
require_once __DIR__ . '/services/TransferService.php';
require_once __DIR__ . '/services/LoanService.php';

class CommandRouter
{
    private static ?array $repliedToUser = null;
    
    public static function handle(string $message, string $whatsappId, string $username, ?array $repliedTo = null): string
    {
        $parts = explode(' ', trim($message));
        $command = strtolower($parts[0]);
        
        // Check if user is banned first
        $user = User::findByWhatsappId($whatsappId);
        if ($user && $user['banned'] && $command !== '.menu') {
            return "⛔ You are banned from playing!";
        }
        
        // Store replied-to user info for admin commands
        self::$repliedToUser = $repliedTo;

        switch ($command) {
            // User Management
            case '.register':
                return self::register($whatsappId, $username);
            
            case '.profile':
                return ProfileService::getProfile($whatsappId);
            
            case '.balance':
            case '.bal':
                return BankingService::getBalance($whatsappId);

            case '.menu':
            case '.help':
                return self::getMenu();

            case '.leaderboard':
            case '.top':
                return self::leaderboard();

            // Games
            case '.roulette':
                $color = $parts[1] ?? '';
                $amount = intval($parts[2] ?? 0);
                if (!$color || !$amount) {
                    return "Usage: .roulette red|black|gold <amount>\nExample: .roulette black 200";
                }
                return RouletteService::play($whatsappId, $color, $amount);

            case '.slots':
                $amount = intval($parts[1] ?? 0);
                if (!$amount) {
                    return "Usage: .slots <amount>\nExample: .slots 100";
                }
                return SlotsService::play($whatsappId, $amount);

            case '.cf':
            case '.coinflip':
                $choice = $parts[1] ?? '';
                $amount = intval($parts[2] ?? 0);
                if (!$choice || !$amount) {
                    return "Usage: .cf (h|t) <amount>\nExample: .cf h 100\n(h = heads, t = tails)";
                }
                return CoinFlipService::play($whatsappId, $choice, $amount);

            // Dice feature disabled - pending full integration
            // case '.dice':
            //     Coming soon!

            case '.casino':
                $amount = intval($parts[1] ?? 0);
                if (!$amount) {
                    return "Usage: .casino <amount>\nExample: .casino 100";
                }
                return GamblingService::stake($whatsappId, $amount);

            // Banking
            case '.deposit':
            case '.dep':
                $amount = intval($parts[1] ?? 0);
                return BankingService::deposit($whatsappId, $amount);

            case '.withdraw':
            case '.with':
                $amount = intval($parts[1] ?? 0);
                return BankingService::withdraw($whatsappId, $amount);

            // Shop & Assets
            case '.shop':
                return ShopService::viewShop();

            case '.buy':
                $assetId = intval($parts[1] ?? 0);
                return ShopService::buyAsset($whatsappId, $assetId);

            case '.assets':
            case '.inv':
                return ShopService::viewAssets($whatsappId);

            case '.daily':
                return ShopService::claimDaily($whatsappId);

            case '.beg':
                return BegService::beg($whatsappId);

            case '.send':
                $target = $parts[1] ?? '';
                $amount = intval($parts[2] ?? 0);
                if (!$target || !$amount) {
                    return "Usage: .send <username> <amount>\nExample: .send john 1000";
                }
                return TransferService::send($whatsappId, $target, $amount);

            case '.loan':
                if (count($parts) < 2) {
                    return LoanService::viewLoans($whatsappId);
                }
                $assetName = $parts[1] ?? '';
                $amount = intval($parts[2] ?? 0);
                if (!$amount) {
                    return "Usage: .loan <asset_name> <amount>\nExample: .loan \"Small Kiosk\" 5000\n\nFirst use .loan to see options!";
                }
                return LoanService::takeLoan($whatsappId, $assetName, $amount);

            case '.payloan':
                $amount = intval($parts[1] ?? 0);
                if (!$amount) {
                    return "Usage: .payloan <amount>\nExample: .payloan 5500";
                }
                return LoanService::repayLoan($whatsappId, $amount);

            // Robbery
            case '.rob':
                $target = $parts[1] ?? '';
                if (!$target) {
                    return "Usage: .rob username";
                }
                return RobberyService::rob($whatsappId, $target);

            // Admin Commands (Owner only)
            case '.addbal':
                if (!AdminService::isAdmin($whatsappId)) {
                    return "❌ This command is only for admins!";
                }
                
                // Check if replying to a user
                if (self::$repliedToUser) {
                    $targetJid = self::$repliedToUser['jid'];
                    $targetUser = User::findByWhatsappId($targetJid);
                    $targetUsername = $targetUser['username'] ?? 'Unknown';
                } else {
                    $targetUsername = $parts[1] ?? '';
                }
                
                $amount = intval($parts[self::$repliedToUser ? 1 : 2] ?? 0);
                if (!$targetUsername || !$amount) {
                    return self::$repliedToUser 
                        ? "Usage: Reply to a user with: .addbal <amount>" 
                        : "Usage: .addbal <username> <amount>";
                }
                return AdminService::addBalance($whatsappId, $targetUsername, $amount);

            case '.ban':
                if (!AdminService::isAdmin($whatsappId)) {
                    return "❌ This command is only for admins!";
                }
                
                // Check if replying to a user
                if (self::$repliedToUser) {
                    $targetJid = self::$repliedToUser['jid'];
                    $targetUser = User::findByWhatsappId($targetJid);
                    $targetUsername = $targetUser['username'] ?? 'Unknown';
                } else {
                    $targetUsername = $parts[1] ?? '';
                }
                
                if (!$targetUsername) {
                    return self::$repliedToUser 
                        ? "Error: User not found" 
                        : "Usage: .ban <username>";
                }
                return AdminService::ban($targetUsername);

            case '.unban':
                if (!AdminService::isAdmin($whatsappId)) {
                    return "❌ This command is only for admins!";
                }
                
                // Check if replying to a user
                if (self::$repliedToUser) {
                    $targetJid = self::$repliedToUser['jid'];
                    $targetUser = User::findByWhatsappId($targetJid);
                    $targetUsername = $targetUser['username'] ?? 'Unknown';
                } else {
                    $targetUsername = $parts[1] ?? '';
                }
                
                if (!$targetUsername) {
                    return self::$repliedToUser 
                        ? "Error: User not found" 
                        : "Usage: .unban <username>";
                }
                return AdminService::unban($targetUsername);

            case '.seize':
                if (!AdminService::isAdmin($whatsappId)) {
                    return "❌ This command is only for admins!";
                }
                
                // Check if replying to a user
                if (self::$repliedToUser) {
                    $subcommand = strtolower($parts[1] ?? '');
                    $targetJid = self::$repliedToUser['jid'];
                    $targetUser = User::findByWhatsappId($targetJid);
                    $targetUsername = $targetUser['username'] ?? 'Unknown';
                } else {
                    $subcommand = strtolower($parts[1] ?? '');
                    $targetUsername = $parts[2] ?? '';
                }
                
                if (!$targetUsername) {
                    return self::$repliedToUser 
                        ? "Usage: Reply to a user with: .seize wallet|bank|assets" 
                        : "Usage: .seize wallet|bank|assets <username>";
                }
                
                switch ($subcommand) {
                    case 'wallet':
                        return AdminService::seizeWallet($targetUsername);
                    case 'bank':
                        return AdminService::seizeBank($targetUsername);
                    case 'assets':
                        return AdminService::seizeAssets($targetUsername);
                    default:
                        return self::$repliedToUser 
                            ? "Usage: Reply to a user with: .seize wallet|bank|assets" 
                            : "Usage: .seize wallet|bank|assets <username>";
                }

            case '.stats':
                if (!AdminService::isAdmin($whatsappId)) {
                    return "❌ This command is only for admins!";
                }
                return AdminService::getStats();

            case '.giveaway':
                if (!AdminService::isAdmin($whatsappId)) {
                    return "❌ This command is only for admins!";
                }
                return AdminService::giveaway();

            case '.whoami':
                $isAdminStr = AdminService::isAdmin($whatsappId) ? '✅ YES - You are an admin!' : '❌ NO - You are not an admin';
                return "👤 Your WhatsApp ID: {$whatsappId}\n\nAdmin Status: {$isAdminStr}";

            default:
                return "❓ Unknown command. Use .menu for available commands.";
        }
    }

    private static function register(string $whatsappId, string $username): string
    {
        $user = User::findByWhatsappId($whatsappId);

        if ($user) {
            return "✅ You are already registered.\n\nUsername: {$user['username']}\nUse .menu for commands.";
        }

        User::create($whatsappId, $username);
        return "✅ REGISTRATION COMPLETE!\n\nWelcome, {$username}!\n\n💰 You received 1000 coins to start.\n\nUse .menu to see all commands!";
    }

    private static function leaderboard(): string
    {
        $users = User::top(10);

        if (empty($users)) {
            return "No players yet.";
        }

        $output = "🏆 LEADERBOARD 🏆\n\n";

        foreach ($users as $index => $user) {
            $rank = $index + 1;
            $medal = '';
            if ($rank === 1) $medal = '🥇';
            elseif ($rank === 2) $medal = '🥈';
            elseif ($rank === 3) $medal = '🥉';
            
            $output .= "{$medal} {$rank}. {$user['username']}\n";
            $output .= "   Net Worth: {$user['networth']} coins\n\n";
        }

        return trim($output);
    }

    private static function getMenu(): string
    {
        $menu = "╔══════════════════════╗\n";
        $menu .= "     💎 RICK'S HUB 💎\n";
        $menu .= "╚══════════════════════╝\n\n";
        
        $menu .= "👤 ACCOUNT & PROFILE\n";
        $menu .= ".profile     → Show your full profile\n";
        $menu .= ".bal         → Wallet & Bank balance\n";
        $menu .= ".assets      → Your businesses\n";
        $menu .= ".lb          → Leaderboard\n";
        $menu .= ".menu        → Show this menu\n\n";

        $menu .= "💰 INCOME & FINANCE\n";
        $menu .= ".daily       → Claim daily reward\n";
        $menu .= ".beg         → Beg for random money\n";
        $menu .= ".send u x    → Send money to user\n";
        $menu .= ".dep x       → Deposit to bank\n";
        $menu .= ".wd x        → Withdraw from bank\n";
        $menu .= ".loan        → Borrow against asset\n";
        $menu .= ".payloan x   → Repay loan\n\n";

        $menu .= "🎰 GAMBLING & CHANCE\n";
        $menu .= ".casino x       → Play casino\n";
        $menu .= ".slots x        → Spin slots\n";
        $menu .= ".cf h/t x       → Coin flip\n";
        $menu .= ".roulette c x   → Roulette\n\n";

        $menu .= "🏢 BUSINESS & SHOP\n";
        $menu .= ".shop     → View businesses\n";
        $menu .= ".buy id   → Buy a business\n";
        $menu .= ".sell idx → Sell a business\n\n";

        $menu .= "💣 ROBBERY\n";
        $menu .= ".rob @user → Rob someone\n\n";

        $menu .= "━━━━━━━━━━━━━━━━━━\n";
        $menu .= "💼 Build • Gamble • Dominate\n";
        $menu .= "━━━━━━━━━━━━━━━━━━";

        return $menu;
    }
}
