<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enum\OrderSide;
use App\Enum\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class OrderSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $users = User::all();
        $symbols = ['BTC/USDT', 'ETH/USDT', 'BNB/USDT', 'SOL/USDT', 'ADA/USDT', 'DOT/USDT'];

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please run UserSeeder first.');

            return;
        }

        // Create a mix of orders for each user
        $users->each(function (User $user) use ($symbols) {
            // Create 3-8 orders per user
            $orderCount = fake()->numberBetween(3, 8);

            for ($i = 0; $i < $orderCount; $i++) {
                $symbol = fake()->randomElement($symbols);
                $side = fake()->randomElement(OrderSide::cases());
                $status = fake()->randomElement(OrderStatus::cases());

                // Adjust price based on symbol for more realistic data
                $basePrice = match ($symbol) {
                    'BTC/USDT' => fake()->randomFloat(2, 40000, 100000),
                    'ETH/USDT' => fake()->randomFloat(2, 2000, 5000),
                    'BNB/USDT' => fake()->randomFloat(2, 200, 800),
                    'SOL/USDT' => fake()->randomFloat(2, 50, 300),
                    'ADA/USDT' => fake()->randomFloat(4, 0.3, 2.0),
                    'DOT/USDT' => fake()->randomFloat(2, 5, 50),
                    default => fake()->randomFloat(2, 1, 100),
                };

                Order::factory()
                    ->forUser($user)
                    ->withSymbol($symbol)
                    ->withSide($side)
                    ->withStatus($status)
                    ->create([
                        'price' => $basePrice,
                        'amount' => fake()->randomFloat(6, 0.001, 10),
                    ]);
            }
        });

        $this->command->info('Orders seeded successfully!');
    }
}
