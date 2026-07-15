<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $cart = $user ? $user->getOrCreateCart() : $this->getSessionCart($request);

        $cart->load(['items.product.images', 'items.productVariant']);

        $items = $cart->items->map(function ($item) {
            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product->name,
                'product_slug' => $item->product->slug,
                'product_image' => $item->product->images->where('is_primary', true)->first()?->image_path
                    ?? $item->product->images->first()?->image_path,
                'variant' => $item->productVariant ? [
                    'id' => $item->productVariant->id,
                    'name' => $item->productVariant->name,
                ] : null,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'subtotal' => $item->subtotal,
                'stock_available' => $item->product->stock_quantity,
            ];
        });

        $summary = [
            'total_items' => $cart->items->sum('quantity'),
            'subtotal' => $cart->items->sum('subtotal'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'cart_id' => $cart->id,
                'items' => $items,
                'summary' => $summary,
            ],
        ]);
    }

    public function addItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'variant_id' => ['nullable', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $product = Product::active()->findOrFail($validated['product_id']);

        if (!$product->isInStock()) {
            return response()->json([
                'success' => false,
                'message' => 'Product is out of stock.',
            ], 422);
        }

        if ($validated['quantity'] > $product->stock_quantity) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient stock. Available: ' . $product->stock_quantity,
            ], 422);
        }

        $user = $request->user();
        $cart = $user ? $user->getOrCreateCart() : $this->getOrCreateSessionCart($request);

        $unitPrice = $product->current_price;

        // Add variant price adjustment if applicable
        if (!empty($validated['variant_id'])) {
            $variant = $product->variants()->find($validated['variant_id']);
            if ($variant) {
                $unitPrice += $variant->price_adjustment;
            }
        }

        $existingItem = $cart->items()
            ->where('product_id', $product->id)
            ->where('product_variant_id', $validated['variant_id'] ?? null)
            ->first();

        if ($existingItem) {
            $newQuantity = $existingItem->quantity + $validated['quantity'];
            if ($newQuantity > $product->stock_quantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient stock. Available: ' . $product->stock_quantity . ', In cart: ' . $existingItem->quantity,
                ], 422);
            }
            $existingItem->update([
                'quantity' => $newQuantity,
                'subtotal' => $newQuantity * $unitPrice,
            ]);
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'product_variant_id' => $validated['variant_id'] ?? null,
                'quantity' => $validated['quantity'],
                'unit_price' => $unitPrice,
                'subtotal' => $validated['quantity'] * $unitPrice,
            ]);
        }

        return $this->index($request);
    }

    public function updateItem(Request $request, int $itemId): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0'],
        ]);

        $cart = $request->user()?->getActiveCart() ?? $this->getSessionCart($request);
        if (!$cart) {
            return response()->json(['success' => false, 'message' => 'Cart not found.'], 404);
        }

        $item = $cart->items()->findOrFail($itemId);

        if ($validated['quantity'] === 0) {
            $item->delete();
        } else {
            $product = $item->product;
            if ($validated['quantity'] > $product->stock_quantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient stock. Available: ' . $product->stock_quantity,
                ], 422);
            }
            $item->update([
                'quantity' => $validated['quantity'],
                'subtotal' => $validated['quantity'] * $item->unit_price,
            ]);
        }

        return $this->index($request);
    }

    public function removeItem(Request $request, int $itemId): JsonResponse
    {
        $cart = $request->user()?->getActiveCart() ?? $this->getSessionCart($request);
        if ($cart) {
            $cart->items()->where('id', $itemId)->delete();
        }

        return $this->index($request);
    }

    public function clear(Request $request): JsonResponse
    {
        $cart = $request->user()?->getActiveCart() ?? $this->getSessionCart($request);
        if ($cart) {
            $cart->items()->delete();
        }

        return response()->json(['success' => true, 'message' => 'Cart cleared.']);
    }

    private function getSessionCart(Request $request): ?Cart
    {
        $sessionId = $request->session()->getId();
        return Cart::where('session_id', $sessionId)->first();
    }

    private function getOrCreateSessionCart(Request $request): Cart
    {
        $sessionId = $request->session()->getId();
        return Cart::firstOrCreate(['session_id' => $sessionId]);
    }
}
