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

it('can create a buy order with sufficient balance', function () {
    $response = $this->actingAs($this->buyer)
        ->postJson('/api/orders', [
            'symbol' => 'BTC/USDT',
            'side' => 'buy',
            'price' => '50000.00',
            'amount' => '0.1',
        ]);

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'message',
        'data' => [
            'id', 'symbol', 'side', 'price', 'amount', 'status',
        ],
    ]);

    // Check that USD balance was deducted
    $this->buyer->refresh();
    expect((string) $this->buyer->balance)->toBe('5000.00000000'); // 10000 - (50000 * 0.1)
});

it('rejects buy order with insufficient balance', function () {
    $response = $this->actingAs($this->buyer)
        ->postJson('/api/orders', [
            'symbol' => 'BTC/USDT',
            'side' => 'buy',
            'price' => '200000.00', // Too expensive
            'amount' => '0.1',
        ]);

    $response->assertStatus(400);
    $response->assertJson(['error' => 'Insufficient USD balance']);
});

it('can create a sell order with sufficient assets', function () {
    $response = $this->actingAs($this->seller)
        ->postJson('/api/orders', [
            'symbol' => 'BTC/USDT',
            'side' => 'sell',
            'price' => '50000.00',
            'amount' => '0.5',
        ]);

    $response->assertSuccessful();

    // Check that BTC was locked
    $btcAsset = $this->seller->assets()->where('symbol', 'BTC')->first();
    expect($btcAsset->locked_amount)->toBe('0.50000000');
});

it('rejects sell order with insufficient assets', function () {
    $response = $this->actingAs($this->seller)
        ->postJson('/api/orders', [
            'symbol' => 'BTC/USDT',
            'side' => 'sell',
            'price' => '50000.00',
            'amount' => '2.0', // More than available
        ]);

    $response->assertStatus(400);
    $response->assertJson(['error' => 'Insufficient BTC balance']);
});

it('can match orders and create trades', function () {
    Event::fake();

    // Create buy order
    $buyResponse = $this->actingAs($this->buyer)
        ->postJson('/api/orders', [
            'symbol' => 'BTC/USDT',
            'side' => 'buy',
            'price' => '50000.00',
            'amount' => '0.1',
        ]);

    // Create matching sell order
    $sellResponse = $this->actingAs($this->seller)
        ->postJson('/api/orders', [
            'symbol' => 'BTC/USDT',
            'side' => 'sell',
            'price' => '50000.00',
            'amount' => '0.1',
        ]);

    // Trigger matching
    $matchResponse = $this->actingAs($this->buyer)
        ->postJson('/api/match-orders', [
            'symbol' => 'BTC/USDT',
        ]);

    $matchResponse->assertSuccessful();
    $matchResponse->assertJsonStructure([
        'message',
        'data' => [
            'symbol',
            'matches_count',
            'trades',
        ],
    ]);

    // Verify OrderMatched event was fired
    Event::assertDispatched(OrderMatched::class);

    // Verify buyer received BTC
    $buyerBtcAsset = $this->buyer->assets()->where('symbol', 'BTC')->first();
    expect($buyerBtcAsset)->not->toBeNull();
    expect($buyerBtcAsset->amount)->toBe('0.10000000');

    // Verify seller received USD (minus commission)
    $this->seller->refresh();
    $expectedAmount = bcadd('1000.00000000', bcsub('5000.00000000', '75.00000000', 8), 8); // 1000 + (5000 - 75 commission)
    expect((string) $this->seller->balance)->toBe($expectedAmount);
});

it('can get orderbook for a symbol', function () {
    // Create some orders
    $this->actingAs($this->buyer)
        ->postJson('/api/orders', [
            'symbol' => 'BTC/USDT',
            'side' => 'buy',
            'price' => '49000.00',
            'amount' => '0.1',
        ]);

    $this->actingAs($this->seller)
        ->postJson('/api/orders', [
            'symbol' => 'BTC/USDT',
            'side' => 'sell',
            'price' => '51000.00',
            'amount' => '0.1',
        ]);

    $response = $this->actingAs($this->buyer)
        ->getJson('/api/orders?symbol=BTC/USDT');

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => [
            'symbol',
            'buy_orders',
            'sell_orders',
        ],
    ]);
});

it('can cancel an order and release funds', function () {
    // Create buy order
    $response = $this->actingAs($this->buyer)
        ->postJson('/api/orders', [
            'symbol' => 'BTC/USDT',
            'side' => 'buy',
            'price' => '50000.00',
            'amount' => '0.1',
        ]);

    $orderId = $response->json('data.id');

    // Cancel the order
    $cancelResponse = $this->actingAs($this->buyer)
        ->postJson("/api/orders/{$orderId}/cancel");

    $cancelResponse->assertSuccessful();

    // Verify balance was restored
    $this->buyer->refresh();
    expect((string) $this->buyer->balance)->toBe('10000.00000000');
});
