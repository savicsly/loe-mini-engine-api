<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enum\OrderSide;
use App\Enum\OrderStatus;
use App\Models\Order;
use App\Models\Trade;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class TradeSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Get filled orders that can be used for trades
        $filledOrders = Order::where('status', OrderStatus::FILLED)->get();

        if ($filledOrders->count() < 2) {
            $this->command->warn('Not enough filled orders found. Creating some trades with existing orders anyway.');

            // Create some trades with random orders if no filled orders exist
            $allOrders = Order::all();
            if ($allOrders->count() < 2) {
                $this->command->warn('Not enough orders found. Please run OrderSeeder first.');

                return;
            }

            // Create 10-20 trades with random orders
            $tradeCount = fake()->numberBetween(10, 20);
            for ($i = 0; $i < $tradeCount; $i++) {
                $this->createRandomTrade($allOrders);
            }
        } else {
            // Create trades from filled orders
            $symbols = $filledOrders->groupBy('symbol');

            foreach ($symbols as $symbol => $orders) {
                $buyOrders = $orders->where('side', OrderSide::BUY);
                $sellOrders = $orders->where('side', OrderSide::SELL);

                // Create trades by pairing buy and sell orders
                $pairCount = min($buyOrders->count(), $sellOrders->count());

                for ($i = 0; $i < $pairCount; $i++) {
                    $buyOrder = $buyOrders->skip($i)->first();
                    $sellOrder = $sellOrders->skip($i)->first();

                    if ($buyOrder && $sellOrder) {
                        $this->createTradeFromOrders($buyOrder, $sellOrder);
                    }
                }
            }

            // Create additional random trades
            $additionalTrades = fake()->numberBetween(5, 15);
            for ($i = 0; $i < $additionalTrades; $i++) {
                $this->createRandomTrade($filledOrders);
            }
        }

        $this->command->info('Trades seeded successfully!');
    }

    /**
     * Create a trade from two specific orders.
     */
    private function createTradeFromOrders(Order $buyOrder, Order $sellOrder): void
    {
        // Use the lower price and smaller amount for the trade
        $tradePrice = min($buyOrder->price, $sellOrder->price);
        $tradeAmount = min($buyOrder->amount, $sellOrder->amount);

        Trade::factory()
            ->withOrders($buyOrder, $sellOrder)
            ->withPriceAndAmount((float) $tradePrice, (float) $tradeAmount)
            ->create();
    }

    /**
     * Create a random trade from available orders.
     */
    private function createRandomTrade($orders): void
    {
        if ($orders->count() < 2) {
            return;
        }

        $buyOrder = $orders->random();
        $sellOrder = $orders->where('id', '!=', $buyOrder->id)->random();

        // Create a realistic trade price between the two order prices
        $minPrice = min($buyOrder->price, $sellOrder->price);
        $maxPrice = max($buyOrder->price, $sellOrder->price);
        $tradePrice = fake()->randomFloat(8, (float) $minPrice * 0.99, (float) $maxPrice * 1.01);

        // Create a realistic trade amount (usually smaller than order amounts)
        $maxAmount = min($buyOrder->amount, $sellOrder->amount);
        $tradeAmount = fake()->randomFloat(8, (float) $maxAmount * 0.1, (float) $maxAmount);

        Trade::factory()
            ->withOrders($buyOrder, $sellOrder)
            ->withPriceAndAmount($tradePrice, $tradeAmount)
            ->create();
    }
}
