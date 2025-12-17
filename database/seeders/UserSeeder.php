<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $victorBala = User::factory()->create([
            'name' => 'Victor Bala',
            'email' => 'savicsly@gmail.com',
            'balance' => '50000.00000000',
        ]);

        // Create 1-2 assets for Victor Bala
        $this->createAssetsForUser($victorBala);

        // Create 9 more users with their assets
        User::factory(9)->create()->each(function (User $user) {
            $this->createAssetsForUser($user);
        });
    }

    /**
     * Create 1-2 random assets for a user.
     */
    private function createAssetsForUser(User $user): void
    {
        $assetCount = fake()->numberBetween(1, 2);
        $availableSymbols = ['BTC', 'ETH', 'USDT'];

        foreach (fake()->randomElements($availableSymbols, $assetCount) as $symbol) {
            Asset::factory()->forUser($user)->withSymbol($symbol)->create();
        }
    }
}
