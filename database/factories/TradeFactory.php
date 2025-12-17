<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\Trade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Trade>
 */
final class TradeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = fake()->randomFloat(8, 0.001, 100000);
        $amount = fake()->randomFloat(8, 0.001, 100);
        $commission = $amount * $price * 0.015; // 1.5% commission

        return [
            'buy_order_id' => Order::factory(),
            'sell_order_id' => Order::factory(),
            'price' => $price,
            'amount' => $amount,
            'commission' => $commission,
        ];
    }

    /**
     * Create a trade with specific orders.
     */
    public function withOrders(Order $buyOrder, Order $sellOrder): static
    {
        return $this->state(fn (array $attributes) => [
            'buy_order_id' => $buyOrder->id,
            'sell_order_id' => $sellOrder->id,
        ]);
    }

    /**
     * Create a trade with a specific price and amount.
     */
    public function withPriceAndAmount(float $price, float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => $price,
            'amount' => $amount,
            'commission' => $amount * $price * 0.015, // 1.5% commission
        ]);
    }
}
