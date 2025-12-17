<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TradeResource;
use App\Services\MatchingService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MatchingController extends Controller
{
    public function __construct(
        private readonly MatchingService $matchingService
    ) {}

    /**
     * Trigger order matching for a specific symbol.
     * This would typically be called by a job or internal system.
     */
    public function matchOrders(Request $request): JsonResponse
    {
        $symbol = $request->input('symbol');

        if (! $symbol) {
            return response()->json([
                'error' => 'Symbol parameter is required',
            ], 400);
        }

        try {
            $matches = $this->matchingService->matchOrders($symbol);

            return response()->json([
                'message' => 'Matching completed',
                'data' => [
                    'symbol' => $symbol,
                    'matches_count' => count($matches),
                    'trades' => TradeResource::collection(collect($matches)),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Matching failed: '.$e->getMessage(),
            ], 500);
        }
    }
}
