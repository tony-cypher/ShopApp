<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Most-recently favourited first.
        $ids = Favorite::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->pluck('product_id');

        $products = Product::with(['category:id,name,slug', 'brand:id,name,slug,initials,color'])
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn ($product) => $ids->search($product->id))
            ->values();

        return response()->json(['data' => $products]);
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        Favorite::firstOrCreate([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
        ]);

        return response()->json([
            'data' => $this->favoriteIds($request),
            'message' => 'Added to favourites.',
        ], 201);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        Favorite::where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->delete();

        return response()->json([
            'data' => $this->favoriteIds($request),
            'message' => 'Removed from favourites.',
        ]);
    }

    private function favoriteIds(Request $request): array
    {
        return Favorite::where('user_id', $request->user()->id)->pluck('product_id')->values()->all();
    }
}
