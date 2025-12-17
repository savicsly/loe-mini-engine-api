<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enum\OrderSide;
use App\Enum\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
final class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $symbols = ['BTC/USDT', 'ETH/USDT', 'BNB/USDT', 'SOL/USDT', 'ADA/USDT', 'DOT/USDT'];

        return [
            'user_id' => User::factory(),
            'symbol' => fake()->randomElement($symbols),
            'side' => fake()->randomElement(OrderSide::cases()),
            'price' => fake()->randomFloat(8, 0.001, 100000),
            'amount' => fake()->randomFloat(8, 0.001, 1000),
            'status' => fake()->randomElement(OrderStatus::cases()),
        ];
    }

    /**
     * Create an order for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Create an order with a specific symbol.
     */
    public function withSymbol(string $symbol): static
    {
        return $this->state(fn (array $attributes) => [
            'symbol' => $symbol,
        ]);
    }

    /**
     * Create an order with a specific side.
     */
    public function withSide(OrderSide $side): static
    {
        return $this->state(fn (array $attributes) => [
            'side' => $side,
        ]);
    }

    /**
     * Create an order with a specific status.
     */
    public function withStatus(OrderStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }

    /**
     * Create an open order.
     */
    public function open(): static
    {
        return $this->withStatus(OrderStatus::OPEN);
    }

    /**
     * Create a filled order.
     */
    public function filled(): static
    {
        return $this->withStatus(OrderStatus::FILLED);
    }

    /**
     * Create a canceled order.
     */
    public function canceled(): static
    {
        return $this->withStatus(OrderStatus::CANCELED);
    }

    /**
     * Create a buy order.
     */
    public function buy(): static
    {
        return $this->withSide(OrderSide::BUY);
    }

    /**
     * Create a sell order.
     */
    public function sell(): static
    {
        return $this->withSide(OrderSide::SELL);
    }
}
