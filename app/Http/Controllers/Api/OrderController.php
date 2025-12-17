<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService
    ) {}

    /**
     * Get orderbook for a specific symbol or filtered orders.
     */
    public function index(Request $request): JsonResponse
    {
        $symbol = $request->query('symbol');
        $side = $request->query('side');
        $status = $request->query('status');
        $userId = $request->query('user_id');

        // If filtering parameters are provided, return filtered orders
        if ($side || $status || $userId) {
            $orders = $this->orderService->getFilteredOrders([
                'symbol' => $symbol,
                'side' => $side,
                'status' => $status,
                'user_id' => $userId,
            ]);

            return response()->json([
                'data' => [
                    'orders' => OrderResource::collection($orders),
                    'filters' => [
                        'symbol' => $symbol,
                        'side' => $side,
                        'status' => $status,
                        'user_id' => $userId,
                    ],
                ],
            ]);
        }

        // Default behavior: return orderbook
        if (! $symbol) {
            return response()->json([
                'error' => 'Symbol parameter is required for orderbook',
            ], 400);
        }

        $orderbook = $this->orderService->getOrderbook($symbol);

        return response()->json([
            'data' => [
                'symbol' => $orderbook['symbol'],
                'buy_orders' => OrderResource::collection($orderbook['buy_orders']),
                'sell_orders' => OrderResource::collection($orderbook['sell_orders']),
            ],
        ]);
    }

    /**
     * Create a new limit order.
     */
    public function store(CreateOrderRequest $request): JsonResponse
    {
        try {
            $order = $this->orderService->createOrder(
                $request->user(),
                $request->validated()
            );

            return response()->json([
                'message' => 'Order created successfully',
                'data' => new OrderResource($order),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Cancel an order.
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        try {
            $order = $this->orderService->cancelOrder($request->user(), $id);

            return response()->json([
                'message' => 'Order canceled successfully',
                'data' => new OrderResource($order),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Calculate volume and preview trade details before placing order.
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'symbol' => 'required|string',
            'side' => 'required|in:buy,sell',
            'price' => 'required|numeric|min:0.00000001',
            'amount' => 'required|numeric|min:0.00000001',
        ]);

        $symbol = $request->input('symbol');
        $side = $request->input('side');
        $price = (string) $request->input('price');
        $amount = (string) $request->input('amount');

        // Calculate trade volume
        $volume = bcmul($price, $amount, 8);
        $commission = bcmul($volume, '0.015', 8); // 1.5% commission

        $preview = [
            'symbol' => $symbol,
            'side' => $side,
            'price' => $price,
            'amount' => $amount,
            'volume' => $volume,
            'commission' => $commission,
        ];

        if ($side === 'buy') {
            $preview['total_cost'] = bcadd($volume, $commission, 8);
            $preview['you_will_receive'] = $amount.' '.explode('/', $symbol)[0];
            $preview['you_will_pay'] = $preview['total_cost'].' USDT';
        } else {
            $preview['you_will_receive'] = bcsub($volume, $commission, 8).' USDT';
            $preview['you_will_pay'] = $amount.' '.explode('/', $symbol)[0];
        }

        // Check if user has sufficient funds
        $user = $request->user();
        $preview['can_afford'] = $this->orderService->canAffordOrder($user, $side, $price, $amount, $symbol);

        return response()->json([
            'data' => $preview,
        ]);
    }
}
