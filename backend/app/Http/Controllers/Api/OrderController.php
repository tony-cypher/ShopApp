<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::with('items')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('placed_at')
            ->get();

        return response()->json(['data' => $orders]);
    }

    public function show(Request $request, string $reference): JsonResponse
    {
        $order = Order::with('items')
            ->where('reference', $reference)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json(['data' => $order]);
    }
}
