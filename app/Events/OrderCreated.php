<?php

declare(strict_types=1);

namespace App\Events;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class OrderCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly Order $order
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->order->user_id),
            new Channel('user-updates'), // Public fallback
            new Channel('orders'), // Public orders channel
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'OrderCreated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'order' => new OrderResource($this->order),
            'userId' => $this->order->user_id,
            'message' => 'Order created successfully!',
            'toast' => [
                'type' => 'success',
                'title' => '✅ Order Placed',
                'message' => "Your {$this->order->side->value} order for {$this->order->amount} {$this->getBaseSymbol()} at {$this->order->price} USDT has been placed",
                'duration' => 4000,
            ],
            'updates' => [
                'refresh_orders' => true,
                'refresh_balance' => true,
            ],
        ];
    }

    /**
     * Get the base symbol from trading pair.
     */
    private function getBaseSymbol(): string
    {
        return explode('/', $this->order->symbol)[0] ?? $this->order->symbol;
    }
}
