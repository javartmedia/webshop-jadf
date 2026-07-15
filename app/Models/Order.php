<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'order_number',
        'status',
        'payment_status',
        'payment_method',
        'payment_gateway',
        'payment_transaction_id',
        'payment_snap_token',
        'payment_response',
        'subtotal',
        'shipping_cost',
        'discount_amount',
        'voucher_discount',
        'loyalty_discount',
        'tax_amount',
        'grand_total',
        'shipping_courier',
        'shipping_service',
        'shipping_awb',
        'shipping_address',
        'shipping_city',
        'shipping_province',
        'shipping_postal_code',
        'shipping_phone',
        'recipient_name',
        'notes',
        'internal_notes',
        'paid_at',
        'shipped_at',
        'delivered_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'voucher_id',
        'loyalty_points_used',
        'loyalty_points_earned',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'voucher_discount' => 'decimal:2',
        'loyalty_discount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'payment_response' => 'json',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->order_number)) {
                $order->order_number = 'WH-' . date('Ymd') . '-' . strtoupper(Str::random(6));
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function voucherUsage(): HasMany
    {
        return $this->hasMany(VoucherUsage::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    public function addStatusHistory(string $status, ?string $notes = null, ?int $userId = null): void
    {
        $this->statusHistories()->create([
            'status' => $status,
            'notes' => $notes,
            'user_id' => $userId ?? auth()->id(),
        ]);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed', 'processing']);
    }

    public function cancel(string $reason, ?int $userId = null): void
    {
        if (!$this->canBeCancelled()) {
            throw new \Exception('Order cannot be cancelled in current status: ' . $this->status);
        }

        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        $this->addStatusHistory('cancelled', $reason, $userId);

        // Restore stock
        foreach ($this->items as $item) {
            $product = Product::find($item->product_id);
            if ($product) {
                $product->increment('stock_quantity', $item->quantity);
            }
        }
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            'pending' => 'bg-yellow-100 text-yellow-800',
            'confirmed' => 'bg-blue-100 text-blue-800',
            'processing' => 'bg-indigo-100 text-indigo-800',
            'shipped' => 'bg-purple-100 text-purple-800',
            'delivered' => 'bg-green-100 text-green-800',
            'completed' => 'bg-green-200 text-green-900',
            'cancelled' => 'bg-red-100 text-red-800',
            'refunded', 'partially_refunded' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public function getPaymentStatusBadgeClassAttribute(): string
    {
        return match($this->payment_status) {
            'paid' => 'bg-green-100 text-green-800',
            'pending' => 'bg-yellow-100 text-yellow-800',
            'unpaid' => 'bg-gray-100 text-gray-800',
            'failed', 'expired' => 'bg-red-100 text-red-800',
            'refunded' => 'bg-blue-100 text-blue-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
}
