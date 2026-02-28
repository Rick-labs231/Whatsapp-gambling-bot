# 🤖 WhatsApp Gamble Bot

A fully-featured WhatsApp gamble bot built with Node.js (Baileys) and PHP. Features multiple games, banking system, asset shop, and admin controls.

## 🎮 Features

### Games
- **Roulette** - Spin the wheel for 2x or 10x multipliers
  - Red/Black: 40% chance each, 2x multiplier
  - Gold: 20% chance, 10x multiplier (RARE!)
- **Slots** - 3-reel slot machine with various multipliers
  - Double Match: 3x multiplier
  - Triple Match: 10x multiplier
  - Triple Diamond: 50x multiplier (JACKPOT!)
- **Coin Flip** - 50/50 chance to double your bet
- **Casino** - Classic betting with variable odds

### Banking System
- **Wallet** - For gambling and robberies (vulnerable to theft)
- **Bank** - Safe storage (cannot be stolen or used in games)
- **Net Worth** - Total value (wallet + bank + assets)

### Shop & Assets
Buy income-generating assets:
- Small Kiosk - 1,000 coins/day (costs 2,000)
- Mini Mart - 5,000 coins/day (costs 10,000)
- Supermarket - 25,000 coins/day (costs 50,000)
- Restaurant - 100,000 coins/day (costs 200,000)
- Shopping Mall - 500,000 coins/day (costs 1,000,000)
- Tech Company - 5,000,000 coins/day (costs 10,000,000)
- Black Hat Company - 100,000,000 coins/day (costs 200,000,000)

**Daily income** is sent directly to your bank (safe!)

### Robbery
- Risk your wallet coins to rob others
- 30% success rate
- Steal 20-50% of target's wallet
- Lose 35-65% of your wallet on failure
- 10-minute cooldown

### Leaderboard
Compete with other players based on net worth (wallet + bank + assets)

---

## 📱 Commands

### Account Management
```
.register - Create account
.balance  - Check wallet/bank/net worth
.menu     - Show all commands
.top      - View leaderboard
```

### Games (Uses wallet coins)
```
.roulette <amount>              - Spin roulette (2x or 10x)
.slots <amount>                 - Play slots machine
.coinflip <amount> heads/tails  - 50/50 double or nothing
.casino <amount>                - Classic betting game
```

### Banking (Safe storage)
```
.deposit <amount>  - Move coins from wallet to bank
.withdraw <amount> - Move coins from bank to wallet
```

### Shop & Assets
```
.shop           - View available assets to buy
.buy <asset_id> - Buy an income-generating asset
.assets         - View your owned assets
.daily          - Claim daily income from assets
```

### Robbery
```
.rob <username> - Rob another player's wallet (30% success)
```

---

## 🔐 Admin Commands (Owner Only)

**Your Admin ID:** `+23470793905353`

```
.addbal <username> <amount>     - Add/remove coins to wallet
.ban <username>                 - Ban a player
.unban <username>               - Unban a player
.seize wallet <username>        - Seize all wallet coins
.seize bank <username>          - Seize all bank coins
.seize assets <username>        - Remove all assets
.stats                          - View bot statistics
```

---

## 💰 Game Mechanics

### Money Flow
1. **Wallet** (Liquid) → Used for gambling, robbery target, bot operations
2. **Bank** (Safe) → Stored coins, cannot be stolen or used in games
3. **Assets** (Income) → Generate daily income deposited to bank

### Asset Economics
- Cost = 2 × Daily Income
- Example: Asset that gives 50,000/day costs 100,000 to buy
- Income claimed once per day
- Assets add to your net worth → improves leaderboard position

### Risk Management
- Move coins to bank to protect from robbery
- Only wallet coins are at risk
- Bank is completely safe
- Assets cannot be stolen, but can be seized by admins

---

## 🚀 Starting the Bot

### Node.js Server (WhatsApp Connection)
```bash
cd bot-server
npm install  # First time only
npm start    # Start server
```

The server runs on `http://localhost:3000` and maintains your WhatsApp connection.

### Database
SQLite database at `/storage/gamble.sqlite` (auto-created)

### Testing
Visit: `http://localhost/gamble%20bot/public/test.php`

Features:
- Check connection status
- Display QR code for re-authentication
- Test sending messages
- View logs

---

## 📊 Technical Stack

- **WhatsApp Connection**: Node.js + Baileys
- **PHP Backend**: Message processing and game logic
- **Database**: SQLite
- **API**: REST endpoints for server-PHP communication

---

## 🎯 Getting Started

1. **Register**: `.register` (get 1,000 starting coins)
2. **Check Balance**: `.balance`
3. **Try a Game**: `.roulette 100`
4. **Buy Assets**: `.shop` then `.buy 1`
5. **Claim Daily**: `.daily`
6. **Check Rank**: `.top`

---

## 🐛 Troubleshooting

### Connection Issues
- Check if Node.js server is running: `http://localhost:3000/status`
- Verify WhatsApp is still authenticated
- Re-scan QR code if needed

### Database Issues
- Delete `/bot-server/sessions/creds.json` to reset authentication
- Database auto-migrates on startup

### Game Errors
- Ensure you have registered: `.register`
- Check wallet balance: `.balance`
- Verify bet amount is positive

---

## 📈 Future Features
- Lottery system
- Betting pools/multiplayer games
- Item trading
- Achievements/badges
- Advanced statistics
- Daily quests

---

## ⚖️ Rules
- No self-robbery
- Cannot gamble with bank coins
- Cannot rob bank coins
- Daily income claimed once per day
- Minimum bet requirements for robbery (100 coins)

Enjoy the bot! 🎉
