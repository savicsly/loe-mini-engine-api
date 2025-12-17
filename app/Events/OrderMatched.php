<?php

declare(strict_types=1);

namespace App\Events;

use App\Http\Resources\TradeResource;
use App\Models\Trade;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class OrderMatched implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly Trade $trade
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->trade->buyOrder->user_id),
            new PrivateChannel('user.'.$this->trade->sellOrder->user_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'OrderMatched';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $buyOrder = $this->trade->buyOrder;
        $sellOrder = $this->trade->sellOrder;

        return [
            'trade' => new TradeResource($this->trade),
            'message' => 'Your order has been matched!',
            'toast' => [
                'type' => 'success',
                'title' => '🎉 Order Matched!',
                'message' => "Your {$buyOrder->side->value} order for {$this->trade->amount} {$this->getBaseSymbol()} has been executed at {$this->trade->price} USDT",
                'duration' => 5000, // 5 seconds
                'actions' => [
                    [
                        'text' => 'View Trade',
                        'action' => 'viewTrade',
                        'data' => ['tradeId' => $this->trade->id],
                    ],
                    [
                        'text' => 'View Portfolio',
                        'action' => 'viewPortfolio',
                        'data' => [],
                    ],
                ],
            ],
            'updates' => [
                'refresh_balance' => true,
                'refresh_assets' => true,
                'refresh_orders' => true,
                'refresh_trades' => true,
            ],
            'sound' => [
                'enabled' => true,
                'type' => 'success',
                'file' => 'order_matched.mp3',
            ],
        ];
    }

    /**
     * Get the base symbol from trading pair.
     */
    private function getBaseSymbol(): string
    {
        return explode('/', $this->trade->buyOrder->symbol)[0] ?? $this->trade->buyOrder->symbol;
    }
}
