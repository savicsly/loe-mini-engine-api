<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\OrderSide;
use App\Enum\OrderStatus;
use App\Events\BalanceUpdated;
use App\Events\OrderCancelled;
use App\Events\OrderCreated;
use App\Events\OrderMatched;
use App\Models\Asset;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class OrderService
{
    public function getOrderbook(string $symbol): array
    {
        $buyOrders = Order::where('symbol', $symbol)
            ->where('side', OrderSide::BUY)
            ->where('status', OrderStatus::OPEN)
            ->orderBy('price', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();

        $sellOrders = Order::where('symbol', $symbol)
            ->where('side', OrderSide::SELL)
            ->where('status', OrderStatus::OPEN)
            ->orderBy('price', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        return [
            'symbol' => $symbol,
            'buy_orders' => $buyOrders,
            'sell_orders' => $sellOrders,
        ];
    }

    public function createOrder(User $user, array $orderData): Order
    {
        return DB::transaction(function () use ($user, $orderData) {
            $symbol = $orderData['symbol'];
            $side = OrderSide::from($orderData['side']);
            $price = (string) $orderData['price'];
            $amount = (string) $orderData['amount'];

            $this->validateAndLockFunds($user, $symbol, $side, $price, $amount);

            $order = Order::create([
                'user_id' => $user->id,
                'symbol' => $symbol,
                'side' => $side,
                'price' => $price,
                'amount' => $amount,
                'status' => OrderStatus::OPEN,
            ]);

            OrderCreated::dispatch($order);
            $this->tryMatchOrder($order);

            return $order;
        });
    }

    public function cancelOrder(User $user, string $orderId): Order
    {
        return DB::transaction(function () use ($user, $orderId) {
            $order = Order::where('id', $orderId)
                ->where('user_id', $user->id)
                ->where('status', OrderStatus::OPEN)
                ->firstOrFail();

            $this->releaseLockedFunds($order);

            $order->update(['status' => OrderStatus::CANCELED]);
            OrderCancelled::dispatch($order);

            return $order;
        });
    }

    public function getFilteredOrders(array $filters): Collection
    {
        $query = Order::query()->with('user');

        if (! empty($filters['symbol'])) {
            $query->where('symbol', $filters['symbol']);
        }

        if (! empty($filters['side'])) {
            $query->where('side', OrderSide::from($filters['side']));
        }

        if (! empty($filters['status'])) {
            $statusMapping = [
                'open' => OrderStatus::OPEN,
                'filled' => OrderStatus::FILLED,
                'canceled' => OrderStatus::CANCELED,
            ];

            if (isset($statusMapping[$filters['status']])) {
                $query->where('status', $statusMapping[$filters['status']]);
            }
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function canAffordOrder(User $user, string $side, string $price, string $amount, string $symbol): bool
    {
        $price = (string) $price;
        $amount = (string) $amount;

        if ($side === 'buy') {
            $totalCost = bcmul($price, $amount, 8);
            $commission = bcmul($totalCost, '0.015', 8);
            $totalRequired = bcadd($totalCost, $commission, 8);

            return bccomp($user->balance, $totalRequired, 8) >= 0;
        }

        $baseAsset = explode('/', $symbol, 2)[0] ?? $symbol;
        $asset = Asset::where('user_id', $user->id)
            ->where('symbol', $baseAsset)
            ->first();

        if (! $asset) {
            return false;
        }

        return bccomp($asset->available_amount, $amount, 8) >= 0;
    }

    private function validateAndLockFunds(User $user, string $symbol, OrderSide $side, string $price, string $amount): void
    {
        if ($side === OrderSide::BUY) {
            $totalUsd = bcmul($price, $amount, 8);

            if (bccomp($user->balance, $totalUsd, 8) < 0) {
                if (bccomp($user->balance, $totalUsd, 8) < 0) {
                    throw new Exception('Insufficient USD balance');
                }

                $previousBalance = $user->balance;

                $user->decrement('balance', $totalUsd);

                $user->refresh();
                BalanceUpdated::dispatch($user, $previousBalance, 'Funds locked for order');
            } else {
                $baseAsset = explode('/', $symbol, 2)[0] ?? $symbol;

                $asset = Asset::where('user_id', $user->id)
                    ->where('symbol', $baseAsset)
                    ->first();

                if (! $asset || bccomp($asset->available_amount, $amount, 8) < 0) {
                    throw new Exception("Insufficient {$baseAsset} balance");
                }
                $asset->increment('locked_amount', $amount);
            }
        }
    }

    private function releaseLockedFunds(Order $order): void
    {
        if ($order->side === OrderSide::BUY) {
            $totalUsd = bcmul($order->price, $order->amount, 8);

            $previousBalance = $order->user->balance;
            $previousBalance = $order->user->balance;

            $order->user->increment('balance', $totalUsd);

            $order->user->refresh();
            BalanceUpdated::dispatch($order->user, $previousBalance, 'Funds released from cancelled order');
        } else {
            $baseAsset = explode('/', $order->symbol, 2)[0] ?? $order->symbol;

            $asset = Asset::where('user_id', $order->user_id)
                ->where('symbol', $baseAsset)
                ->first();

            if ($asset) {
                $asset->decrement('locked_amount', $order->amount);
            }
        }
    }

    private function tryMatchOrder(Order $newOrder): void
    {
        $counterSide = $newOrder->side === OrderSide::BUY ? OrderSide::SELL : OrderSide::BUY;

        $matchingOrders = Order::where('symbol', $newOrder->symbol)
            ->where('side', $counterSide)
            ->where('status', OrderStatus::OPEN)
            ->when($newOrder->side === OrderSide::BUY, function ($query) use ($newOrder) {
                return $query->where('price', '<=', $newOrder->price)->orderBy('price', 'asc');
            })
            ->when($newOrder->side === OrderSide::SELL, function ($query) use ($newOrder) {
                return $query->where('price', '>=', $newOrder->price)->orderBy('price', 'desc');
            })
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($matchingOrders as $matchingOrder) {
            if ($newOrder->fresh()->status !== OrderStatus::OPEN) {
                break;
            }
            $this->executeMatch($newOrder, $matchingOrder);
        }
    }

    private function executeMatch(Order $order1, Order $order2): void
    {
        DB::transaction(function () use ($order1, $order2) {
            $buyOrder = $order1->side === OrderSide::BUY ? $order1 : $order2;
            $sellOrder = $order1->side === OrderSide::SELL ? $order1 : $order2;

            $executionPrice = (float) $order2->price;
            $executionAmount = min((float) $buyOrder->amount, (float) $sellOrder->amount);
            $totalValue = $executionPrice * $executionAmount;

            $commission = $totalValue * 0.015;
            $buyer = $buyOrder->user;
            $lockedAmount = (float) $buyOrder->price * (float) $buyOrder->amount;
            $actualCost = $totalValue + $commission;
            $refund = $lockedAmount - $actualCost;

            if ($refund > 0) {
                $buyer->increment('balance', $refund);
            }
            $seller = $sellOrder->user;
            $baseSymbol = explode('/', $sellOrder->symbol)[0] ?? $sellOrder->symbol;

            $asset = Asset::firstOrCreate(
                ['user_id' => $seller->id, 'symbol' => $baseSymbol],
                ['amount' => 0, 'locked_amount' => 0]
            );

            $asset->decrement('locked_amount', $executionAmount);
            $seller->increment('balance', $totalValue - $commission);
            $buyerAsset = Asset::firstOrCreate(
                ['user_id' => $buyer->id, 'symbol' => $baseSymbol],
                ['amount' => 0, 'locked_amount' => 0]
            );
            $buyerAsset->increment('amount', $executionAmount);

            $buyOrder->update(['status' => OrderStatus::FILLED]);
            $sellOrder->update(['status' => OrderStatus::FILLED]);
            $trade = Trade::create([
                'buy_order_id' => $buyOrder->id,
                'sell_order_id' => $sellOrder->id,
                'price' => $executionPrice,
                'amount' => $executionAmount,
                'commission' => $commission,
            ]);

            OrderMatched::dispatch($trade);
            BalanceUpdated::dispatch($buyer->fresh(), $buyer->balance, 'Order matched');
            BalanceUpdated::dispatch($seller->fresh(), $seller->balance, 'Order matched');
        });
    }
}
