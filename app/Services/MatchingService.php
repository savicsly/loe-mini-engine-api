<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\OrderSide;
use App\Enum\OrderStatus;
use App\Models\Asset;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class MatchingService
{
    /**
     * Match orders for a specific symbol.
     * This is a simple matching algorithm that matches the first valid counter order.
     */
    public function matchOrders(string $symbol): array
    {
        $matches = [];

        return DB::transaction(function () use ($symbol, &$matches) {
            // Get all open buy orders ordered by price (highest first) and time (oldest first)
            $buyOrders = Order::where('symbol', $symbol)
                ->where('side', OrderSide::BUY)
                ->where('status', OrderStatus::OPEN)
                ->orderBy('price', 'desc')
                ->orderBy('created_at', 'asc')
                ->lockForUpdate()
                ->get();

            // Get all open sell orders ordered by price (lowest first) and time (oldest first)
            $sellOrders = Order::where('symbol', $symbol)
                ->where('side', OrderSide::SELL)
                ->where('status', OrderStatus::OPEN)
                ->orderBy('price', 'asc')
                ->orderBy('created_at', 'asc')
                ->lockForUpdate()
                ->get();

            foreach ($buyOrders as $buyOrder) {
                foreach ($sellOrders as $sellOrder) {
                    // Skip if same user
                    if ($buyOrder->user_id === $sellOrder->user_id) {
                        continue;
                    }

                    // Check if prices can match (buy price >= sell price) AND amounts are equal (full match only)
                    if (bccomp($buyOrder->price, $sellOrder->price, 8) >= 0 &&
                        bccomp($buyOrder->amount, $sellOrder->amount, 8) === 0) {

                        $match = $this->executeMatch($buyOrder, $sellOrder);
                        if ($match) {
                            $matches[] = $match;

                            // Both orders are completely filled (full match)
                            $sellOrders = $sellOrders->reject(fn ($order) => $order->id === $sellOrder->id);
                            break; // Move to next buy order
                        }
                    }
                }
            }

            return $matches;
        });
    }

    /**
     * Execute a full match between two orders (amounts are equal).
     */
    private function executeMatch(Order $buyOrder, Order $sellOrder): ?Trade
    {
        // Calculate trade details (full match, so amounts are equal)
        $tradePrice = $sellOrder->price; // Use sell order price (market price)
        $tradeAmount = $buyOrder->amount; // Both amounts are equal
        $tradeValue = bcmul($tradePrice, $tradeAmount, 8);
        $commission = bcmul($tradeValue, '0.015', 8); // 1.5% commission

        // Create the trade
        $trade = Trade::create([
            'buy_order_id' => $buyOrder->id,
            'sell_order_id' => $sellOrder->id,
            'price' => $tradePrice,
            'amount' => $tradeAmount,
            'commission' => $commission,
        ]);

        // Mark both orders as filled (full match)
        $buyOrder->update([
            'amount' => '0',
            'status' => OrderStatus::FILLED,
        ]);

        $sellOrder->update([
            'amount' => '0',
            'status' => OrderStatus::FILLED,
        ]);

        // Update user balances and assets
        $this->updateUserBalances($buyOrder, $sellOrder, $tradePrice, $tradeAmount, $commission);

        // Broadcast the OrderMatched event to both users
        \App\Events\OrderMatched::dispatch($trade);

        return $trade;
    }

    /**
     * Update user balances and assets after a trade.
     */
    private function updateUserBalances(Order $buyOrder, Order $sellOrder, string $price, string $amount, string $commission): void
    {
        $tradeValue = bcmul($price, $amount, 8);
        $baseAsset = explode('/', $buyOrder->symbol, 2)[0];

        // Update buyer (gets asset, pays USD + commission)
        $buyer = $buyOrder->user;
        $buyerAsset = Asset::firstOrCreate(
            ['user_id' => $buyer->id, 'symbol' => $baseAsset],
            ['amount' => '0', 'locked_amount' => '0']
        );
        $buyerAsset->increment('amount', $amount);

        // Update seller (gets USD - commission, loses asset)
        $seller = $sellOrder->user;
        $receivedUsd = bcsub($tradeValue, $commission, 8);
        $seller->increment('balance', $receivedUsd);

        // Decrease locked amounts
        $sellerAsset = Asset::where('user_id', $seller->id)
            ->where('symbol', $baseAsset)
            ->first();
        if ($sellerAsset) {
            $sellerAsset->decrement('locked_amount', $amount);
        }
    }
}
