<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'brand_id',
        'series_id',
        'sku',
        'name',
        'slug',
        'description',
        'short_description',
        'regular_price',
        'sale_price',
        'cost_price',
        'stock_quantity',
        'low_stock_threshold',
        'product_type',
        'condition',
        'scale',
        'material',
        'year_released',
        'is_limited_edition',
        'limited_quantity',
        'is_featured',
        'is_active',
        'preorder_start_date',
        'preorder_end_date',
        'preorder_estimated_ship',
        'weight',
        'length',
        'width',
        'height',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'view_count',
        'sold_count',
    ];

    protected $casts = [
        'regular_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'is_limited_edition' => 'boolean',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'preorder_start_date' => 'datetime',
        'preorder_end_date' => 'datetime',
        'preorder_estimated_ship' => 'datetime',
        'weight' => 'decimal:2',
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (empty($product->sku)) {
                $product->sku = 'HW-' . strtoupper(Str::random(8));
            }
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(ProductTag::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)->where('is_approved', true);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function flashSales(): BelongsToMany
    {
        return $this->belongsToMany(FlashSale::class, 'flash_sale_products')
            ->withPivot(['flash_price', 'max_quantity', 'sold_quantity', 'max_per_customer'])
            ->withTimestamps();
    }

    public function getCurrentPriceAttribute(): float
    {
        // Check active flash sale first
        $activeFlashSale = $this->flashSales()
            ->wherePivot('sold_quantity', '<', \DB::raw('flash_sale_products.max_quantity'))
            ->where('is_active', true)
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->first();

        if ($activeFlashSale) {
            return (float) $activeFlashSale->pivot->flash_price;
        }

        return $this->sale_price && $this->sale_price < $this->regular_price
            ? (float) $this->sale_price
            : (float) $this->regular_price;
    }

    public function getDiscountPercentageAttribute(): ?int
    {
        $currentPrice = $this->current_price;
        if ($currentPrice < $this->regular_price) {
            return (int) round((($this->regular_price - $currentPrice) / $this->regular_price) * 100);
        }
        return null;
    }

    public function getAverageRatingAttribute(): float
    {
        return (float) $this->reviews()->avg('rating') ?? 0.0;
    }

    public function getReviewsCountAttribute(): int
    {
        return $this->reviews()->count();
    }

    public function isInStock(): bool
    {
        return $this->stock_quantity > 0;
    }

    public function isLowStock(): bool
    {
        return $this->stock_quantity > 0 && $this->stock_quantity <= $this->low_stock_threshold;
    }

    public function isOutOfStock(): bool
    {
        return $this->stock_quantity <= 0;
    }

    public function isPreorder(): bool
    {
        return $this->product_type === 'preorder';
    }

    public function isCollectorEdition(): bool
    {
        return $this->product_type === 'collector_edition';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true)->where('is_active', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock_quantity', '>', 0);
    }

    public function scopeByCategory($query, $categorySlug)
    {
        return $query->whereHas('category', function ($q) use ($categorySlug) {
            $q->where('slug', $categorySlug);
        });
    }

    public function scopeByBrand($query, $brandSlug)
    {
        return $query->whereHas('brand', function ($q) use ($brandSlug) {
            $q->where('slug', $brandSlug);
        });
    }

    public function scopePriceRange($query, $min, $max)
    {
        return $query->where(function ($q) use ($min, $max) {
            $q->where('sale_price', '>=', $min)
              ->where('sale_price', '<=', $max)
              ->orWhere(function ($sq) use ($min, $max) {
                  $sq->whereNull('sale_price')
                     ->where('regular_price', '>=', $min)
                     ->where('regular_price', '<=', $max);
              });
        });
    }
}
