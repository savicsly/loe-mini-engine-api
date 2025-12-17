<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Asset>
 */
final class AssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $symbols = ['BTC', 'ETH', 'USDT', 'USDC', 'BNB', 'SOL', 'ADA', 'DOT', 'LINK', 'UNI'];
        $amount = fake()->randomFloat(8, 0.1, 1000);
        $lockedAmount = fake()->randomFloat(8, 0, $amount * 0.1); // Locked amount is max 10% of total

        return [
            'user_id' => User::factory(),
            'symbol' => fake()->randomElement($symbols),
            'amount' => $amount,
            'locked_amount' => $lockedAmount,
        ];
    }

    /**
     * Create an asset for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Create an asset with a specific symbol.
     */
    public function withSymbol(string $symbol): static
    {
        return $this->state(fn (array $attributes) => [
            'symbol' => $symbol,
        ]);
    }
}
