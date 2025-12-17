<?php

declare(strict_types=1);

use App\Events\OrderMatched;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->buyer = User::factory()->create(['balance' => '10000.00000000']);
    $this->seller = User::factory()->create(['balance' => '1000.00000000']);

    // Give seller some BTC to sell
    Asset::factory()
        ->forUser($this->seller)
        ->withSymbol('BTC')
        ->create(['amount' => '1.00000000', 'locked_amount' => '0.00000000']);
});

it('can filter orders by side', function () {
    // Create buy and sell orders
    $this->actingAs($this->buyer)
        ->postJson('/api/orders', [
            'symbol' => 'BTC/USDT',
            'side' => 'buy',
            'price' => '50000.00',
            'amount' => '0.1',
        ]);

    $this->actingAs($this->seller)
        ->postJson('/api/orders', [
            'symbol' => 'BTC/USDT',
            'side' => 'sell',
            'price' => '51000.00',
            'amount' => '0.1',
        ]);

    // Filter by buy side
    $response = $this->actingAs($this->buyer)
        ->getJson('/api/orders?side=buy');

    $response->assertSuccessful();
    $response->assertJsonPath('data.filters.side', 'buy');

    // All returned orders should be buy orders
    $orders = $response->json('data.orders');
    expect($orders)->not->toBeEmpty();
    foreach ($orders as $order) {
        expect($order['side'])->toBe('buy');
    }
});

it('can filter orders by status', function () {
    // Create an order
    $orderResponse = $this->actingAs($this->buyer)
        ->postJson('/api/orders', [
            'symbol' => 'BTC/USDT',
            'side' => 'buy',
            'price' => '50000.00',
            'amount' => '0.1',
        ]);

    $orderId = $orderResponse->json('data.id');

    // Cancel the order
    $this->actingAs($this->buyer)
        ->postJson("/api/orders/{$orderId}/cancel");

    // Filter by cancelled status
    $response = $this->actingAs($this->buyer)
        ->getJson('/api/orders?status=canceled');

    $response->assertSuccessful();
    expect($response->json('data.orders'))->not->toBeEmpty();
});

it('can filter orders by user', function () {
    // Create orders from different users
    $this->actingAs($this->buyer)
        ->postJson('/api/orders', [
            'symbol' => 'BTC/USDT',
            'side' => 'buy',
            'price' => '50000.00',
            'amount' => '0.1',
        ]);

    $this->actingAs($this->seller)
        ->postJson('/api/orders', [
            'symbol' => 'BTC/USDT',
            'side' => 'sell',
            'price' => '51000.00',
            'amount' => '0.1',
        ]);

    // Filter by buyer's orders
    $response = $this->actingAs($this->buyer)
        ->getJson('/api/orders?user_id='.$this->buyer->id);

    $response->assertSuccessful();

    // All returned orders should belong to the buyer
    $orders = $response->json('data.orders');
    expect($orders)->not->toBeEmpty();
    foreach ($orders as $order) {
        expect($order['user']['id'])->toBe($this->buyer->id);
    }
});

it('can preview order calculations', function () {
    $response = $this->actingAs($this->buyer)
        ->postJson('/api/orders/preview', [
            'symbol' => 'BTC/USDT',
            'side' => 'buy',
            'price' => '50000.00',
            'amount' => '0.1',
        ]);

    $response->assertSuccessful();

    $preview = $response->json('data');

    expect($preview)->toHaveKeys([
        'symbol', 'side', 'price', 'amount', 'volume', 'commission',
        'total_cost', 'you_will_receive', 'you_will_pay', 'can_afford',
    ]);

    // Volume should be price * amount
    expect($preview['volume'])->toBe('5000.00000000');

    // Commission should be 1.5% of volume
    expect($preview['commission'])->toBe('75.00000000');

    // Total cost should be volume + commission
    expect($preview['total_cost'])->toBe('5075.00000000');

    // Should be able to afford with 10k balance
    expect($preview['can_afford'])->toBeTrue();
});

it('shows cannot afford in preview when insufficient funds', function () {
    $response = $this->actingAs($this->buyer)
        ->postJson('/api/orders/preview', [
            'symbol' => 'BTC/USDT',
            'side' => 'buy',
            'price' => '200000.00', // Very expensive
            'amount' => '1.0',
        ]);

    $response->assertSuccessful();
    expect($response->json('data.can_afford'))->toBeFalse();
});

it('can get notification preferences', function () {
    $response = $this->actingAs($this->buyer)
        ->getJson('/api/notifications/preferences');

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => [
            'toast_enabled',
            'sound_enabled',
            'email_notifications',
            'push_notifications',
            'order_match_sound',
            'toast_duration',
            'toast_position',
        ],
    ]);
});

it('can update notification preferences', function () {
    $response = $this->actingAs($this->buyer)
        ->putJson('/api/notifications/preferences', [
            'toast_enabled' => false,
            'sound_enabled' => true,
            'toast_duration' => 3000,
            'toast_position' => 'bottom-right',
        ]);

    $response->assertSuccessful();
    $response->assertJsonPath('message', 'Notification preferences updated successfully');
});

it('can test notifications', function () {
    $response = $this->actingAs($this->buyer)
        ->postJson('/api/notifications/test', [
            'type' => 'success',
            'title' => 'Test Toast',
            'message' => 'This is a test notification',
        ]);

    $response->assertSuccessful();
    $response->assertJsonPath('data.toast.type', 'success');
    $response->assertJsonPath('data.toast.title', 'Test Toast');
});

it('order matched event includes enhanced toast data', function () {
    Event::fake();

    // Create matching orders
    $this->actingAs($this->buyer)
        ->postJson('/api/orders', [
            'symbol' => 'BTC/USDT',
            'side' => 'buy',
            'price' => '50000.00',
            'amount' => '0.1',
        ]);

    $this->actingAs($this->seller)
        ->postJson('/api/orders', [
            'symbol' => 'BTC/USDT',
            'side' => 'sell',
            'price' => '50000.00',
            'amount' => '0.1',
        ]);

    // Trigger matching
    $this->actingAs($this->buyer)
        ->postJson('/api/match-orders', ['symbol' => 'BTC/USDT']);

    // Verify OrderMatched event was fired with enhanced data
    Event::assertDispatched(OrderMatched::class, function ($event) {
        $data = $event->broadcastWith();

        // Check toast structure
        expect($data)->toHaveKey('toast');
        expect($data['toast'])->toHaveKeys([
            'type', 'title', 'message', 'duration', 'actions',
        ]);

        // Check updates structure
        expect($data)->toHaveKey('updates');
        expect($data['updates'])->toHaveKeys([
            'refresh_balance', 'refresh_assets', 'refresh_orders', 'refresh_trades',
        ]);

        // Check sound structure
        expect($data)->toHaveKey('sound');
        expect($data['sound'])->toHaveKeys(['enabled', 'type', 'file']);

        return true;
    });
});
