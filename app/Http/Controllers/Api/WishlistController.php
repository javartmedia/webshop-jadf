<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $wishlists = $request->user()
            ->wishlists()
            ->with(['product.images', 'product.category'])
            ->latest()
            ->paginate(20);

        $items = $wishlists->getCollection()->map(function ($wishlist) {
            $product = $wishlist->product;
            return [
                'id' => $wishlist->id,
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'sku' => $product->sku,
                    'current_price' => $product->current_price,
                    'regular_price' => $product->regular_price,
                    'sale_price' => $product->sale_price,
                    'discount_percentage' => $product->discount_percentage,
                    'stock_quantity' => $product->stock_quantity,
                    'condition' => $product->condition,
                    'primary_image' => $product->images->where('is_primary', true)->first()?->image_path
                        ?? $product->images->first()?->image_path,
                    'category' => $product->category ? $product->category->name : null,
                ],
                'added_at' => $wishlist->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'total' => $wishlists->total(),
                'current_page' => $wishlists->currentPage(),
                'last_page' => $wishlists->lastPage(),
            ],
        ]);
    }

    public function toggle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
        ]);

        $user = $request->user();
        $existing = $user->wishlists()->where('product_id', $validated['product_id'])->first();

        if ($existing) {
            $existing->delete();
            return response()->json([
                'success' => true,
                'message' => 'Product removed from wishlist',
                'data' => ['is_wishlisted' => false],
            ]);
        }

        $user->wishlists()->create(['product_id' => $validated['product_id']]);

        return response()->json([
            'success' => true,
            'message' => 'Product added to wishlist',
            'data' => ['is_wishlisted' => true],
        ]);
    }

    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_ids' => ['required', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ]);

        $user = $request->user();
        $wishlistedIds = $user->wishlists()
            ->whereIn('product_id', $validated['product_ids'])
            ->pluck('product_id')
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => ['wishlisted_product_ids' => $wishlistedIds],
        ]);
    }

    public function remove(Request $request, int $productId): JsonResponse
    {
        $request->user()->wishlists()->where('product_id', $productId)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product removed from wishlist',
        ]);
    }
}
