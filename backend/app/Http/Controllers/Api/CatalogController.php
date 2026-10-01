<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function categories(): JsonResponse
    {
        return response()->json([
            'data' => Category::orderBy('position')->orderBy('name')->get(['id', 'name', 'slug', 'emoji']),
        ]);
    }

    public function brands(): JsonResponse
    {
        return response()->json([
            'data' => Brand::orderBy('name')->get(['id', 'name', 'slug', 'initials', 'color']),
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'brands' => ['nullable', 'array'],
            'brands.*' => ['string', 'max:100'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'min_rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'delivery' => ['nullable', 'in:standard,pickup'],
            'deals' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:featured,newest,price_asc,price_desc,rating'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);

        $query = Product::query()
            ->with(['category:id,name,slug,emoji', 'brand:id,name,slug,initials,color']);

        if (! empty($validated['search'])) {
            $term = '%'.strtolower(trim($validated['search'])).'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('lower(name) like ?', [$term])
                    ->orWhereRaw('lower(coalesce(description, \'\')) like ?', [$term]);
            });
        }

        if (! empty($validated['category'])) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $validated['category']));
        }

        if (! empty($validated['brands'])) {
            $query->whereHas('brand', fn ($q) => $q->whereIn('slug', $validated['brands']));
        }

        if (isset($validated['min_price'])) {
            $query->where('price', '>=', $validated['min_price']);
        }

        if (isset($validated['max_price'])) {
            $query->where('price', '<=', $validated['max_price']);
        }

        if (isset($validated['min_rating'])) {
            $query->where('rating', '>=', $validated['min_rating']);
        }

        if (($validated['delivery'] ?? null) === 'standard') {
            $query->where('delivery_standard', true);
        } elseif (($validated['delivery'] ?? null) === 'pickup') {
            $query->where('delivery_pickup', true);
        }

        if (! empty($validated['deals'])) {
            $query->where('is_deal', true);
        }

        match ($validated['sort'] ?? null) {
            'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'rating' => $query->orderByDesc('rating')->orderByDesc('reviews_count'),
            'featured' => $query->orderByDesc('featured')->orderByDesc('rating'),
            default => $query->orderBy('id'),
        };

        $products = $query->paginate((int) ($validated['per_page'] ?? 12));

        // Price-range slider data, computed from the whole catalogue so the
        // slider bounds and histogram stay stable while filters are applied.
        // One query keeps remote (Supabase) round trips down.
        $catalogue = Product::query()->get(['price', 'is_deal']);
        $prices = $catalogue->map(fn ($product) => (float) $product->price);
        $min = $prices->min() ?? 0;
        $max = $prices->max() ?? 0;
        $avg = $prices->avg() ?? 0;
        $dealsCount = $catalogue->where('is_deal', true)->count();

        $bins = 24;
        $histogram = array_fill(0, $bins, 0);
        $span = $max - $min;

        foreach ($prices as $price) {
            $index = $span > 0
                ? min($bins - 1, (int) floor((($price - $min) / $span) * $bins))
                : 0;
            $histogram[$index]++;
        }

        return response()->json([
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
                'price' => [
                    'min' => floor($min),
                    'max' => ceil($max),
                    'avg' => round($avg),
                ],
                'price_histogram' => $histogram,
                'deals_count' => $dealsCount,
            ],
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $product = Product::with(['category:id,name,slug', 'brand:id,name,slug,initials,color'])
            ->where('slug', $slug)
            ->firstOrFail();

        $reviews = $product->reviews()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'author', 'avatar', 'rating', 'comment', 'created_at']);

        $related = Product::with(['category:id,name,slug', 'brand:id,name,slug,initials,color'])
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->inRandomOrder()
            ->limit(4)
            ->get();

        return response()->json([
            'data' => $product,
            'reviews' => $reviews,
            'related' => $related,
        ]);
    }
}
