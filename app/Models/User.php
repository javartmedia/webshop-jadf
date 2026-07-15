<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'role_id',
        'name',
        'email',
        'password',
        'phone',
        'address',
        'city',
        'province',
        'postal_code',
        'avatar',
        'loyalty_points',
        'wallet_balance',
        'status',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
        'loyalty_points' => 'integer',
        'wallet_balance' => 'decimal:2',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function cart(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function loyaltyPointTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyPointTransaction::class);
    }

    public function voucherUsages(): HasMany
    {
        return $this->hasMany(VoucherUsage::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->slug === 'super-admin';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role?->slug, ['super-admin', 'admin']);
    }

    public function isStaff(): bool
    {
        return in_array($this->role?->slug, ['super-admin', 'admin', 'staff']);
    }

    public function isCustomer(): bool
    {
        return $this->role?->slug === 'customer';
    }

    public function hasRole(string $roleSlug): bool
    {
        return $this->role?->slug === $roleSlug;
    }

    public function hasAnyRole(array $roleSlugs): bool
    {
        return in_array($this->role?->slug, $roleSlugs);
    }

    public function getActiveCart(): ?Cart
    {
        return $this->cart()->first();
    }

    public function getOrCreateCart(): Cart
    {
        return $this->cart()->firstOrCreate(['user_id' => $this->id]);
    }

    public function addLoyaltyPoints(int $points, ?Order $order = null, string $description = 'Points earned'): void
    {
        $balanceBefore = $this->loyalty_points;
        $this->loyalty_points += $points;
        $this->save();

        LoyaltyPointTransaction::create([
            'user_id' => $this->id,
            'order_id' => $order?->id,
            'type' => 'earn',
            'points' => $points,
            'balance_before' => $balanceBefore,
            'balance_after' => $this->loyalty_points,
            'description' => $description,
        ]);
    }

    public function redeemLoyaltyPoints(int $points, Order $order): void
    {
        $balanceBefore = $this->loyalty_points;
        $this->loyalty_points -= $points;
        $this->save();

        LoyaltyPointTransaction::create([
            'user_id' => $this->id,
            'order_id' => $order->id,
            'type' => 'redeem',
            'points' => $points,
            'balance_before' => $balanceBefore,
            'balance_after' => $this->loyalty_points,
            'description' => 'Points redeemed for order #' . $order->order_number,
        ]);
    }
}
