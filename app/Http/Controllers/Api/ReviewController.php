<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function productReviews(string $productSlug): JsonResponse
    {
        $product = \App\Models\Product::where('slug', $productSlug)->firstOrFail();

        $reviews = $product->reviews()
            ->with(['user:id,name,avatar', 'images'])
            ->where('is_approved', true)
            ->latest()
            ->paginate(10);

        $stats = [
            'average_rating' => $product->average_rating,
            'total_reviews' => $product->reviews_count,
            'rating_distribution' => [
                5 => $product->reviews()->where('is_approved', true)->where('rating', 5)->count(),
                4 => $product->reviews()->where('is_approved', true)->where('rating', 4)->count(),
                3 => $product->reviews()->where('is_approved', true)->where('rating', 3)->count(),
                2 => $product->reviews()->where('is_approved', true)->where('rating', 2)->count(),
                1 => $product->reviews()->where('is_approved', true)->where('rating', 1)->count(),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'reviews' => $reviews,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'order_id' => ['nullable', 'exists:orders,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review' => ['nullable', 'string', 'max:2000'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:5120'],
        ]);

        $user = $request->user();

        // Check if already reviewed this product for this order
        $existingReview = Review::where('user_id', $user->id)
            ->where('product_id', $validated['product_id'])
            ->where('order_id', $validated['order_id'] ?? null)
            ->first();

        if ($existingReview) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this product.',
            ], 422);
        }

        // Check if order is delivered/completed (verified purchase)
        $isVerifiedPurchase = false;
        if (!empty($validated['order_id'])) {
            $order = $user->orders()->find($validated['order_id']);
            if ($order && in_array($order->status, ['delivered', 'completed'])) {
                $isVerifiedPurchase = true;
            }
        }

        $review = Review::create([
            'user_id' => $user->id,
            'product_id' => $validated['product_id'],
            'order_id' => $validated['order_id'] ?? null,
            'rating' => $validated['rating'],
            'review' => $validated['review'] ?? null,
            'is_verified_purchase' => $isVerifiedPurchase,
            'is_approved' => true, // Auto approve for now
        ]);

        // Handle review images
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('review-images', 'public');
                $review->images()->create(['image_path' => $path]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Review submitted successfully',
            'data' => [
                'review' => $review->load(['user:id,name,avatar', 'images']),
            ],
        ], 201);
    }

    public function myReviews(Request $request): JsonResponse
    {
        $reviews = $request->user()
            ->reviews()
            ->with(['product.images', 'images'])
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $reviews,
        ]);
    }
}
