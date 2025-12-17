<?php

namespace App\Console\Commands;

use App\Events\OrderMatched;
use App\Models\User;
use App\Models\Order;
use App\Models\Trade;
use Illuminate\Console\Command;

class TestBroadcast extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-broadcast {user_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test broadcasting by sending a test OrderMatched event';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('user_id');

        if (!$userId) {
            $user = User::first();
            if (!$user) {
                $this->error('No users found. Please create a user first.');
                return 1;
            }
            $userId = $user->id;
        } else {
            $user = User::find($userId);
            if (!$user) {
                $this->error("User with ID {$userId} not found.");
                return 1;
            }
        }

        $this->info("Testing broadcast to user {$user->email} (ID: {$userId})");

        // Create a mock trade for testing
        try {
            // Create dummy orders (not saved to database)
            $buyOrder = new Order([
                'user_id' => $userId,
                'symbol' => 'BTC/USDT',
                'side' => 'buy',
                'amount' => '0.001',
                'price' => '50000',
                'status' => 2 // FILLED
            ]);
            $buyOrder->id = 1;

            $sellOrder = new Order([
                'user_id' => $userId,
                'symbol' => 'BTC/USDT',
                'side' => 'sell',
                'amount' => '0.001',
                'price' => '50000',
                'status' => 2 // FILLED
            ]);
            $sellOrder->id = 2;

            $trade = new Trade([
                'buy_order_id' => 1,
                'sell_order_id' => 2,
                'amount' => '0.001',
                'price' => '50000'
            ]);
            $trade->id = 1;
            $trade->setRelation('buyOrder', $buyOrder);
            $trade->setRelation('sellOrder', $sellOrder);

            // Broadcast the event
            broadcast(new OrderMatched($trade));

            $this->info('OrderMatched event broadcasted successfully!');
            $this->info('Check the frontend console for the real-time update.');
        } catch (\Exception $e) {
            $this->error('Error broadcasting event: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
