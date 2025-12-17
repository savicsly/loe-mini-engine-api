<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enum\OrderSide;
use App\Enum\OrderStatus;
use App\Events\BalanceUpdated;
use App\Events\OrderCreated;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class TestController extends Controller
{
    public function testBroadcast(): JsonResponse
    {
        // Create a test order event
        $testUser = User::first();

        if (!$testUser) {
            return response()->json(['error' => 'No users found'], 404);
        }

        // Create a test order (don't save to database)
        $testOrder = new Order([
            'user_id' => $testUser->id,
            'symbol' => 'BTCUSD',
            'side' => OrderSide::BUY,
            'price' => 50000.0,
            'amount' => 0.001,
            'status' => OrderStatus::OPEN,
        ]);

        // Set an ID for the test order
        $testOrder->id = 'test-order-12345';
        $testOrder->created_at = now();
        $testOrder->updated_at = now();

        // Test direct broadcasting using Reverb
        try {
            $broadcaster = \Illuminate\Support\Facades\Broadcast::connection('reverb');

            // Send directly to user-updates channel
            $broadcaster->broadcast(['user-updates'], 'test.message', [
                'message' => 'Direct broadcast test from API',
                'timestamp' => now()->toISOString(),
                'order_id' => $testOrder->id,
                'user_id' => $testUser->id,
            ]);

            \Log::info('Direct broadcast sent successfully');
        } catch (\Exception $e) {
            \Log::error('Broadcast failed: ' . $e->getMessage());
        }

        // Also try the event-based approach
        broadcast(new OrderCreated($testOrder))->toOthers();

        return response()->json([
            'message' => 'Test events broadcast successfully',
            'order_id' => $testOrder->id,
            'user_id' => $testUser->id
        ]);
    }
}
