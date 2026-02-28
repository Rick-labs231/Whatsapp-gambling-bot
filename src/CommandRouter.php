<?php

require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/services/GamblingService.php';
require_once __DIR__ . '/services/RouletteService.php';
require_once __DIR__ . '/services/SlotsService.php';
require_once __DIR__ . '/services/CoinFlipService.php';
require_once __DIR__ . '/services/DiceService.php';
require_once __DIR__ . '/services/BankingService.php';
require_once __DIR__ . '/services/ShopService.php';
require_once __DIR__ . '/services/AdminService.php';
require_once __DIR__ . '/services/RewardService.php';
require_once __DIR__ . '/services/RobberyService.php';

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

            case '.dice':
                // Dice is reply-only command
                if (!self::$repliedToUser) {
                    return "❌ Dice challenges must be sent as a reply to a player!\nReply to a user with: .dice <amount>";
                }
                $amount = intval($parts[1] ?? 0);
                if (!$amount) {
                    return "Usage: Reply to a user with: .dice <amount>\nExample: .dice 200";
                }
                $challengedJid = self::$repliedToUser['jid'];
                return DiceService::challenge($whatsappId, $challengedJid, $amount);

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
        $menu = "📱 GAMBLE BOT MENU 📱\n\n";
        
        $menu .= "👤 ACCOUNT\n";
        $menu .= ".register - Create account\n";
        $menu .= ".balance - Check wallet/bank/net worth\n";
        $menu .= ".menu - Show this menu\n\n";

        $menu .= "🎮 GAMES (Use wallet coins)\n";
        $menu .= ".roulette red|black|gold <amount> - Pick a color & bet\n";
        $menu .= ".slots <amount> - Play slots machine\n";
        $menu .= ".cf h|t <amount> - Heads or Tails flip\n";
        $menu .= ".casino <amount> - Classic betting game\n";
        $menu .= ".dice <amount> (REPLY) - Challenge someone!\n\n";

        $menu .= "🏦 BANKING (Safe from robbery)\n";
        $menu .= ".deposit <amount> - Move coins to bank\n";
        $menu .= ".withdraw <amount> - Move coins from bank\n\n";

        $menu .= "🛍️ SHOP & ASSETS\n";
        $menu .= ".shop - View available assets\n";
        $menu .= ".buy <asset_id> - Buy income-generating asset\n";
        $menu .= ".assets - View your assets\n";
        $menu .= ".daily - Claim daily income from assets\n\n";

        $menu .= "🔫 ROBBERY\n";
        $menu .= ".rob <username> - Rob another player's wallet\n\n";

        $menu .= "📊 OTHER\n";
        $menu .= ".top - View leaderboard\n\n";

        $menu .= "🔐 ADMIN (Reply-based)\n";
        $menu .= "Reply with .ban - Ban a player\n";
        $menu .= "Reply with .unban - Unban a player\n";
        $menu .= "Reply with .addbal <amount> - Add balance\n";
        $menu .= "Reply with .seize wallet|bank|assets - Seize assets\n";
        $menu .= ".giveaway - Random 1000 to all players\n";

        return $menu;
    }
}
