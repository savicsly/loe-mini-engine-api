# LOE Mini Trading Engine API

A comprehensive Laravel-based mini trading engine that implements core cryptocurrency trading functionality with real-time order matching, asset management, and live event broadcasting.

## 🚀 Features

-   **User Management**: Registration, authentication with email verification
-   **Asset Portfolio**: Multi-asset support with precise decimal handling
-   **Order Management**: Create, cancel, and track buy/sell limit orders
-   **Real-time Matching**: Full order matching with commission calculation
-   **Live Broadcasting**: Real-time event notifications via Laravel Reverb
-   **Comprehensive API**: RESTful endpoints for all trading operations

## 🏗️ Core Business Logic

### Order Processing

-   **Buy Orders**: Validates and locks USD balance (amount × price)
-   **Sell Orders**: Validates and locks asset quantities
-   **Order States**: OPEN → FILLED/CANCELED with proper fund management

### Matching Engine

-   **Full Match Only**: Orders must have identical amounts (no partial fills)
-   **Price Priority**: Buy orders (highest first), Sell orders (lowest first)
-   **Time Priority**: First-in-first-out within same price levels
-   **Commission**: 1.5% of trade value deducted from buyer

### Real-time Integration

-   **OrderMatched Event**: Broadcasts to both parties via private channels
-   **Instant Updates**: Frontend receives balance/asset changes immediately
-   **No Refresh Required**: UI updates automatically on successful matches

## 🛠️ Installation

```bash
# Clone the repository
git clone <repository-url>
cd loe-mini-engine-api

# Install dependencies
composer install
npm install

# Environment setup
cp .env.example .env
php artisan key:generate

# Database setup
php artisan migrate:fresh --seed

# Start the development server
php artisan serve
php artisan queue:work
php artisan reverb:start --port=8080
```

## 📊 Database Structure

-   **Users**: Balance (USDT) + authentication
-   **Assets**: User portfolios (BTC, ETH, etc.) with locked amounts
-   **Orders**: Buy/sell orders with status tracking
-   **Trades**: Executed matches with commission records

## 🧪 Testing

The system includes comprehensive tests covering all trading scenarios:

```bash
# Run all tests
php artisan test

# Run specific trading tests
php artisan test tests/Feature/TradingEngineTest.php

# Test with coverage
php artisan test --coverage
```

## 📡 API Endpoints

See `API_DOCUMENTATION.md` for detailed endpoint documentation including:

-   Authentication endpoints (`/login`, `/register`)
-   Profile management (`/profile`)
-   Order operations (`/orders`, `/orders/{id}/cancel`)
-   Orderbook retrieval (`/orders?symbol=BTC/USDT`)
-   Order matching (`/match-orders`)

## 🔗 Real-time Integration

Configure Laravel Reverb for real-time broadcasting:

```env
BROADCAST_DRIVER=reverb
REVERB_APP_ID=your_app_id
REVERB_APP_KEY=your_key
REVERB_APP_SECRET=your_secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

Frontend integration example:

```javascript
// Laravel Echo is pre-configured for Reverb
const channel = window.Echo.private("user." + userId);

channel.listen("OrderMatched", (data) => {
    console.log("Trade executed:", data.trade);
    // Update UI automatically
});
```

## 📝 Seeded Data

The seeders create:

-   10 users (including Victor Bala with `savicsly@gmail.com`)
-   1-2 random assets per user
-   Sample orders across different trading pairs
-   Historical trade data for testing

## 🔐 Security

-   Laravel Sanctum for API authentication
-   Input validation on all endpoints
-   Transaction-safe order processing
-   Precise decimal arithmetic for financial calculations

## 💡 Architecture Highlights

-   **Service Layer**: `OrderService` and `MatchingService` handle core logic
-   **Event System**: `OrderMatched` event for real-time notifications
-   **Resource Classes**: Consistent API response formatting
-   **Factory Pattern**: Comprehensive test data generation
-   **Validation**: Form requests for robust input handling

## 🚦 System Requirements

-   PHP 8.5+
-   Laravel 12
-   MySQL/PostgreSQL
-   bcmath extension (for precise decimal calculations)
-   Laravel Reverb (for real-time features)

---
