<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Voucher;
use App\Services\MidtransService;
use App\Services\RajaOngkirService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    protected MidtransService $midtransService;
    protected RajaOngkirService $rajaOngkirService;

    public function __construct(MidtransService $midtransService, RajaOngkirService $rajaOngkirService)
    {
        $this->midtransService = $midtransService;
        $this->rajaOngkirService = $rajaOngkirService;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $orders = $user->orders()
            ->with(['items.product.images', 'statusHistories'])
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    public function show(Request $request, string $orderNumber): JsonResponse
    {
        $user = $request->user();
        $order = $user->orders()
            ->with(['items.product.images', 'items.productVariant', 'statusHistories.user', 'voucher'])
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => ['order' => $order],
        ]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $user = $request->user();
        $cart = $user->getActiveCart();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Cart is empty.',
            ], 422);
        }

        $validated = $request->validate([
            'shipping_address' => ['required', 'string'],
            'shipping_city' => ['required', 'string'],
            'shipping_province' => ['required', 'string'],
            'shipping_postal_code' => ['required', 'string'],
            'shipping_phone' => ['required', 'string'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'shipping_courier' => ['required', 'string'],
            'shipping_service' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
            'voucher_code' => ['nullable', 'string', 'exists:vouchers,code'],
            'use_loyalty_points' => ['nullable', 'integer', 'min:0'],
            'payment_method' => ['nullable', 'string'],
        ]);

        // Validate stock for all cart items
        foreach ($cart->items as $item) {
            $product = $item->product;
            if (!$product->isInStock() || $item->quantity > $product->stock_quantity) {
                return response()->json([
                    'success' => false,
                    'message' => "Insufficient stock for {$product->name}. Available: {$product->stock_quantity}",
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            $subtotal = $cart->items->sum('subtotal');
            $shippingCost = $this->calculateShipping(
                $validated['shipping_city'],
                $validated['shipping_courier'],
                $validated['shipping_service'],
                $cart
            );

            $voucherDiscount = 0;
            $voucherId = null;

            // Validate and apply voucher
            if (!empty($validated['voucher_code'])) {
                $voucher = Voucher::where('code', $validated['voucher_code'])
                    ->where('is_active', true)
                    ->where('start_date', '<=', now())
                    ->where('end_date', '>=', now())
                    ->first();

                if ($voucher) {
                    if ($voucher->min_purchase > $subtotal) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Minimum purchase for this voucher is Rp ' . number_format($voucher->min_purchase),
                        ], 422);
                    }
                    if ($voucher->usage_limit > 0 && $voucher->usage_count >= $voucher->usage_limit) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Voucher usage limit reached.',
                        ], 422);
                    }
                    $userUsageCount = $voucher->usages()->where('user_id', $user->id)->count();
                    if ($userUsageCount >= $voucher->max_per_user) {
                        return response()->json([
                            'success' => false,
                            'message' => 'You have reached the maximum usage for this voucher.',
                        ], 422);
                    }

                    $voucherDiscount = $voucher->discount_type === 'percentage'
                        ? ($subtotal * $voucher->discount_value / 100)
                        : $voucher->discount_value;

                    if ($voucher->max_discount && $voucherDiscount > $voucher->max_discount) {
                        $voucherDiscount = $voucher->max_discount;
                    }
                    $voucherId = $voucher->id;
                }
            }

            // Loyalty points
            $loyaltyDiscount = 0;
            $loyaltyPointsUsed = 0;
            $redemptionRate = (int) \App\Models\LoyaltyPointConfig::getValue('redemption_rate', 100);
            $minRedemption = (int) \App\Models\LoyaltyPointConfig::getValue('minimum_redemption_points', 1000);

            if (!empty($validated['use_loyalty_points']) && $validated['use_loyalty_points'] > 0) {
                $pointsToUse = (int) $validated['use_loyalty_points'];
                if ($pointsToUse > $user->loyalty_points) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient loyalty points. Available: ' . $user->loyalty_points,
                    ], 422);
                }
                if ($pointsToUse < $minRedemption) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Minimum redemption is ' . $minRedemption . ' points.',
                    ], 422);
                }
                $loyaltyPointsUsed = $pointsToUse;
                $loyaltyDiscount = $pointsToUse * $redemptionRate;
                // Cap loyalty discount to not exceed total
                $maxDiscount = $subtotal - $voucherDiscount;
                if ($loyaltyDiscount > $maxDiscount) {
                    $loyaltyDiscount = $maxDiscount;
                    $loyaltyPointsUsed = (int) floor($maxDiscount / $redemptionRate);
                }
            }

            $grandTotal = $subtotal + $shippingCost - $voucherDiscount - $loyaltyDiscount;
            if ($grandTotal < 0) {
                $grandTotal = 0;
            }

            // Create order
            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'payment_method' => $validated['payment_method'] ?? null,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'discount_amount' => 0,
                'voucher_discount' => $voucherDiscount,
                'loyalty_discount' => $loyaltyDiscount,
                'tax_amount' => 0,
                'grand_total' => $grandTotal,
                'shipping_courier' => $validated['shipping_courier'],
                'shipping_service' => $validated['shipping_service'],
                'shipping_address' => $validated['shipping_address'],
                'shipping_city' => $validated['shipping_city'],
                'shipping_province' => $validated['shipping_province'],
                'shipping_postal_code' => $validated['shipping_postal_code'],
                'shipping_phone' => $validated['shipping_phone'],
                'recipient_name' => $validated['recipient_name'],
                'notes' => $validated['notes'] ?? null,
                'voucher_id' => $voucherId,
                'loyalty_points_used' => $loyaltyPointsUsed,
            ]);

            // Create order items and reduce stock
            foreach ($cart->items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_name' => $item->product->name,
                    'product_sku' => $item->product->sku,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'subtotal' => $item->subtotal,
                ]);

                // Reduce stock
                $item->product->decrement('stock_quantity', $item->quantity);
                $item->product->increment('sold_count', $item->quantity);
            }

            // Redeem loyalty points if used
            if ($loyaltyPointsUsed > 0) {
                $user->redeemLoyaltyPoints($loyaltyPointsUsed, $order);
            }

            // Record voucher usage
            if ($voucherId) {
                $voucher->increment('usage_count');
                $voucher->usages()->create([
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'discount_amount' => $voucherDiscount,
                ]);
            }

            // Save address to user profile if not set
            if (empty($user->address)) {
                $user->update([
                    'address' => $validated['shipping_address'],
                    'city' => $validated['shipping_city'],
                    'province' => $validated['shipping_province'],
                    'postal_code' => $validated['shipping_postal_code'],
                    'phone' => $user->phone ?? $validated['shipping_phone'],
                ]);
            }

            // Add initial status history
            $order->addStatusHistory('pending', 'Order created');

            // Clear cart
            $cart->items()->delete();

            // Generate Midtrans Snap Token
            $snapToken = null;
            if ($grandTotal > 0) {
                try {
                    $snapToken = $this->midtransService->createTransaction($order);
                    $order->update([
                        'payment_snap_token' => $snapToken,
                        'payment_gateway' => 'midtrans',
                    ]);
                } catch (\Exception $e) {
                    // Log but don't fail - allow manual payment
                    \Log::error('Midtrans token generation failed: ' . $e->getMessage());
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => [
                    'order' => $order->load(['items.product.images']),
                    'snap_token' => $snapToken,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Checkout error: ' . $e->getMessage(), $e->getTrace());
            return response()->json([
                'success' => false,
                'message' => 'Checkout failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function calculateShippingCost(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'destination_city_id' => ['required', 'string'],
            'courier' => ['required', 'string'],
            'weight' => ['nullable', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $cart = $user->getActiveCart();

        $totalWeight = $validated['weight'] ?? ($cart ? $cart->items->sum(function ($item) {
            return $item->product->weight * $item->quantity;
        }) : 1000);

        if ($totalWeight < 1) {
            $totalWeight = 1000; // minimum 1kg
        }

        try {
            $costs = $this->rajaOngkirService->getShippingCost(
                $validated['destination_city_id'],
                $totalWeight,
                $validated['courier']
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'weight' => $totalWeight,
                    'courier' => $validated['courier'],
                    'services' => $costs,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate shipping: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getProvinces(): JsonResponse
    {
        try {
            $provinces = $this->rajaOngkirService->getProvinces();
            return response()->json(['success' => true, 'data' => $provinces]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getCities(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'province_id' => ['required', 'string'],
        ]);

        try {
            $cities = $this->rajaOngkirService->getCities($validated['province_id']);
            return response()->json(['success' => true, 'data' => $cities]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function cancel(Request $request, string $orderNumber): JsonResponse
    {
        $user = $request->user();
        $order = $user->orders()->where('order_number', $orderNumber)->firstOrFail();

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if (!$order->canBeCancelled()) {
            return response()->json([
                'success' => false,
                'message' => 'Order cannot be cancelled in current status.',
            ], 422);
        }

        $order->cancel($validated['reason'], $user->id);

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled successfully',
            'data' => ['order' => $order->fresh()->load('items.product')],
        ]);
    }

    public function confirmDelivery(Request $request, string $orderNumber): JsonResponse
    {
        $user = $request->user();
        $order = $user->orders()
            ->where('order_number', $orderNumber)
            ->where('status', 'shipped')
            ->firstOrFail();

        $order->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
        $order->addStatusHistory('delivered', 'Customer confirmed delivery', $user->id);

        return response()->json([
            'success' => true,
            'message' => 'Delivery confirmed',
            'data' => ['order' => $order->fresh()],
        ]);
    }

    public function midtransCallback(Request $request): JsonResponse
    {
        \Log::info('Midtrans Callback:', $request->all());

        try {
            $notification = $this->midtransService->handleCallback($request->all());

            if (!$notification) {
                return response()->json(['success' => false, 'message' => 'Invalid notification'], 400);
            }

            $orderId = $notification['order_id'];
            $order = Order::where('order_number', $orderId)->first();

            if (!$order) {
                return response()->json(['success' => false, 'message' => 'Order not found'], 404);
            }

            $transactionStatus = $notification['transaction_status'];
            $fraudStatus = $notification['fraud_status'] ?? 'accept';

            $order->update(['payment_response' => $notification]);

            if ($transactionStatus == 'capture') {
                if ($fraudStatus == 'accept') {
                    $order->update([
                        'payment_status' => 'paid',
                        'paid_at' => now(),
                        'payment_transaction_id' => $notification['transaction_id'],
                        'status' => 'confirmed',
                    ]);
                    $order->addStatusHistory('confirmed', 'Payment verified via Midtrans');

                    // Earn loyalty points
                    $earnRate = (float) \App\Models\LoyaltyPointConfig::getValue('earn_rate_percentage', 1);
                    $pointsEarned = (int) floor(($order->grand_total * $earnRate) / 100);
                    if ($pointsEarned > 0) {
                        $order->user->addLoyaltyPoints($pointsEarned, $order, 'Points earned from order #' . $order->order_number);
                        $order->update(['loyalty_points_earned' => $pointsEarned]);
                    }
                }
            } elseif ($transactionStatus == 'settlement') {
                $order->update([
                    'payment_status' => 'paid',
                    'paid_at' => now(),
                    'payment_transaction_id' => $notification['transaction_id'],
                    'status' => 'confirmed',
                ]);
                $order->addStatusHistory('confirmed', 'Payment settled via Midtrans');
            } elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire'])) {
                $order->update(['payment_status' => 'failed']);
                $order->addStatusHistory('pending', 'Payment ' . $transactionStatus);
            } elseif ($transactionStatus == 'pending') {
                $order->update(['payment_status' => 'pending']);
            } elseif ($transactionStatus == 'refund' || $transactionStatus == 'partial_refund') {
                $refundStatus = $transactionStatus === 'partial_refund' ? 'partially_refunded' : 'refunded';
                $order->update([
                    'payment_status' => 'refunded',
                    'status' => $refundStatus,
                ]);
                $order->addStatusHistory($refundStatus, 'Refund processed via Midtrans');
            }

            return response()->json(['success' => true, 'message' => 'Callback processed']);
        } catch (\Exception $e) {
            \Log::error('Midtrans callback error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function calculateShipping(string $destinationCity, string $courier, string $service, $cart): float
    {
        $totalWeight = $cart->items->sum(function ($item) {
            return $item->product->weight * $item->quantity;
        });

        if ($totalWeight < 1) {
            $totalWeight = 1000;
        }

        try {
            $costs = $this->rajaOngkirService->getShippingCost($destinationCity, $totalWeight, $courier);
            foreach ($costs as $cost) {
                if ($cost['service'] === $service) {
                    return (float) $cost['cost'];
                }
            }
        } catch (\Exception $e) {
            \Log::error('Shipping calculation error: ' . $e->getMessage());
        }

        return 0; // Default fallback
    }
}
