# Trading Engine API Endpoints

This document describes the API endpoints implemented for the mini trading engine.

## Authentication

All endpoints except `/login` and `/register` require authentication using Laravel Sanctum tokens.

**Headers Required:**
```
Authorization: Bearer {your-token}
Content-Type: application/json
Accept: application/json
```

## Endpoints

### 1. User Profile
**GET** `/api/profile`

Returns authenticated user's USD balance and asset balances.

**Response:**
```json
{
  "user": {
    "id": "01JF...",
    "name": "Victor Bala",
    "email": "savicsly@gmail.com",
    "balance": "1000.00000000",
    "assets": [
      {
        "symbol": "BTC",
        "amount": "0.50000000",
        "locked_amount": "0.10000000",
        "available_amount": "0.40000000"
      }
    ],
    "created_at": "2025-12-16T10:00:00.000000Z",
    "updated_at": "2025-12-16T10:00:00.000000Z"
  }
}
```

### 2. Get Orderbook
**GET** `/api/orders?symbol=BTC/USDT`

Returns all open orders for orderbook (buy & sell orders).

**Parameters:**
- `symbol` (required): Trading pair symbol (e.g., BTC/USDT, ETH/USDT)

**Response:**
```json
{
  "data": {
    "symbol": "BTC/USDT",
    "buy_orders": [
      {
        "id": "01JF...",
        "symbol": "BTC/USDT",
        "side": "buy",
        "price": "45000.00000000",
        "amount": "0.10000000",
        "status": "OPEN",
        "created_at": "2025-12-16T10:00:00.000000Z"
      }
    ],
    "sell_orders": [
      {
        "id": "01JF...",
        "symbol": "BTC/USDT",
        "side": "sell",
        "price": "45100.00000000",
        "amount": "0.05000000",
        "status": "OPEN",
        "created_at": "2025-12-16T10:01:00.000000Z"
      }
    ]
  }
}
```

### 3. Create Limit Order
**POST** `/api/orders`

Creates a new limit order.

**Request Body:**
```json
{
  "symbol": "BTC/USDT",
  "side": "buy",
  "price": "45000.00",
  "amount": "0.1"
}
```

**Validation Rules:**
- `symbol`: Required, string, max 10 characters
- `side`: Required, must be "buy" or "sell"
- `price`: Required, numeric, minimum 0.00000001
- `amount`: Required, numeric, minimum 0.00000001

**Response (Success):**
```json
{
  "message": "Order created successfully",
  "data": {
    "id": "01JF...",
    "symbol": "BTC/USDT",
    "side": "buy",
    "price": "45000.00000000",
    "amount": "0.10000000",
    "status": "OPEN",
    "created_at": "2025-12-16T10:00:00.000000Z"
  }
}
```

**Response (Error):**
```json
{
  "error": "Insufficient USD balance"
}
```

### 4. Cancel Order
**POST** `/api/orders/{id}/cancel`

Cancels an open order and releases locked USD or assets.

**Parameters:**
- `id`: Order ID to cancel

**Response (Success):**
```json
{
  "message": "Order canceled successfully",
  "data": {
    "id": "01JF...",
    "symbol": "BTC/USDT",
    "side": "buy",
    "price": "45000.00000000",
    "amount": "0.10000000",
    "status": "CANCELED",
    "updated_at": "2025-12-16T10:05:00.000000Z"
  }
}
```

### 5. Match Orders (Internal)
**POST** `/api/match-orders`

Matches new orders with existing counter orders. This endpoint is typically used internally or by job processors.

**Request Body:**
```json
{
  "symbol": "BTC/USDT"
}
```

**Response:**
```json
{
  "message": "Matching completed",
  "data": {
    "symbol": "BTC/USDT",
    "matches_count": 2,
    "trades": [
      {
        "id": "01JF...",
        "price": "45000.00000000",
        "amount": "0.05000000",
        "commission": "2.25000000",
        "buy_order_id": "01JF...",
        "sell_order_id": "01JF...",
        "created_at": "2025-12-16T10:00:00.000000Z"
      }
    ]
  }
}
```

## Real-Time Broadcasting

### OrderMatched Event
When orders are successfully matched, an `OrderMatched` event is broadcast via Pusher to both users involved in the trade.

**Channel:** `user.{user_id}`
**Event:** `OrderMatched`

**Event Data:**
```json
{
  "trade": {
    "id": "01JF...",
    "price": "45000.00000000",
    "amount": "0.05000000",
    "commission": "67.50000000",
    "buy_order_id": "01JF...",
    "sell_order_id": "01JF...",
    "created_at": "2025-12-16T10:00:00.000000Z"
  },
  "message": "Your order has been matched!"
}
```

**Frontend Integration:**
```javascript
// Subscribe to private user channel using Laravel Echo with Reverb
const channel = window.Echo.private('user.' + userId);

// Listen for order matched events
channel.listen('OrderMatched', (data) => {
    console.log('Order matched:', data.trade);
    // Update UI with new trade information
    // Refresh user balance and asset amounts
    // Update order list
});
```

## Business Logic

### Order Creation
- **Buy Orders**: Locks USD balance (price × amount)
- **Sell Orders**: Locks asset amount from user's portfolio
- Orders are created with `OPEN` status

### Order Cancellation
- Releases locked funds back to user
- Updates order status to `CANCELED`
- Only the order owner can cancel their orders

### Order Matching
- Matches buy orders (highest price first) with sell orders (lowest price first)
- Uses sell order price as execution price
- Creates trades with 1.5% commission
- Updates user balances and assets automatically
- Marks orders as `FILLED` when completely executed

### Asset Management
- Each user has a USD balance and multiple asset balances
- Assets track `amount` (total) and `locked_amount` (in orders)
- `available_amount` = `amount` - `locked_amount`

## Bonus Features

### 6. Order Filtering
The orderbook endpoint supports filtering orders by multiple criteria:

**GET** `/api/orders?side=buy&status=open&user_id=123`

**Parameters:**
- `side`: Filter by order side (`buy` or `sell`)
- `status`: Filter by order status (`open`, `filled`, `canceled`)
- `user_id`: Filter by user ID
- `symbol`: Filter by trading symbol (optional when using other filters)

**Response:**
```json
{
  "data": {
    "orders": [...],
    "filters": {
      "side": "buy",
      "status": "open",
      "user_id": "123",
      "symbol": "BTC/USDT"
    }
  }
}
```

### 7. Order Preview/Volume Calculator
**POST** `/api/orders/preview`

Preview order details and calculations before placing the order.

**Request Body:**
```json
{
  "symbol": "BTC/USDT",
  "side": "buy",
  "price": "50000.00",
  "amount": "0.1"
}
```

**Response:**
```json
{
  "data": {
    "symbol": "BTC/USDT",
    "side": "buy",
    "price": "50000.00000000",
    "amount": "0.10000000",
    "volume": "5000.00000000",
    "commission": "75.00000000",
    "total_cost": "5075.00000000",
    "you_will_receive": "0.1 BTC",
    "you_will_pay": "5075.00000000 USDT",
    "can_afford": true
  }
}
```

### 8. Enhanced Toast/Alert Notifications

The OrderMatched event now includes comprehensive notification data:

```json
{
  "trade": {...},
  "message": "Your order has been matched!",
  "toast": {
    "type": "success",
    "title": "🎉 Order Matched!",
    "message": "Your buy order for 0.1 BTC has been executed at 50000 USDT",
    "duration": 5000,
    "actions": [
      {
        "text": "View Trade",
        "action": "viewTrade", 
        "data": {"tradeId": "01JF..."}
      },
      {
        "text": "View Portfolio",
        "action": "viewPortfolio",
        "data": {}
      }
    ]
  },
  "updates": {
    "refresh_balance": true,
    "refresh_assets": true,
    "refresh_orders": true,
    "refresh_trades": true
  },
  "sound": {
    "enabled": true,
    "type": "success",
    "file": "order_matched.mp3"
  }
}
```

### 9. Notification Preferences
**GET** `/api/notifications/preferences`

Get user notification preferences.

**PUT** `/api/notifications/preferences`

Update notification preferences:
```json
{
  "toast_enabled": true,
  "sound_enabled": true,
  "email_notifications": true,
  "push_notifications": true,
  "order_match_sound": "order_matched.mp3",
  "toast_duration": 5000,
  "toast_position": "top-right"
}
```

**POST** `/api/notifications/test`

Test the notification system:
```json
{
  "type": "success",
  "title": "Test Notification",
  "message": "This is a test message"
}
```

## Error Handling

The API returns appropriate HTTP status codes:
- `200`: Success
- `201`: Created (new order)
- `400`: Bad Request (validation errors, insufficient balance)
- `401`: Unauthorized
- `404`: Not Found
- `500`: Internal Server Error

All error responses follow this format:
```json
{
  "error": "Error message description"
}
```
