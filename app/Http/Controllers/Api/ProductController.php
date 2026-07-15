<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'brand', 'series', 'images'])
            ->active()
            ->inStock();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Category filter
        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        // Brand filter
        if ($request->filled('brand')) {
            $query->whereHas('brand', function ($q) use ($request) {
                $q->where('slug', $request->brand);
            });
        }

        // Series filter
        if ($request->filled('series')) {
            $query->whereHas('series', function ($q) use ($request) {
                $q->where('slug', $request->series);
            });
        }

        // Condition filter
        if ($request->filled('condition')) {
            $query->where('condition', $request->condition);
        }

        // Product type filter
        if ($request->filled('product_type')) {
            $query->where('product_type', $request->product_type);
        }

        // Price range filter
        if ($request->filled('min_price')) {
            $query->where(function ($q) use ($request) {
                $q->where('sale_price', '>=', $request->min_price)
                  ->orWhere(function ($sq) use ($request) {
                      $sq->whereNull('sale_price')
                         ->where('regular_price', '>=', $request->min_price);
                  });
            });
        }
        if ($request->filled('max_price')) {
            $query->where(function ($q) use ($request) {
                $q->where('sale_price', '<=', $request->max_price)
                  ->orWhere(function ($sq) use ($request) {
                      $sq->whereNull('sale_price')
                         ->where('regular_price', '<=', $request->max_price);
                  });
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        $allowedSorts = ['name', 'regular_price', 'sale_price', 'created_at', 'view_count', 'sold_count'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDirection);
        }

        // Featured filter
        if ($request->boolean('featured')) {
            $query->featured();
        }

        // Limited edition filter
        if ($request->boolean('limited_edition')) {
            $query->where('is_limited_edition', true);
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        $products = $query->paginate($perPage);

        $products->getCollection()->transform(function ($product) {
            return $this->formatProduct($product);
        });

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $product = Product::with([
            'category',
            'brand',
            'series',
            'images',
            'variants' => function ($q) {
                $q->where('is_active', true);
            },
            'tags',
            'reviews' => function ($q) {
                $q->where('is_approved', true)->with('user:id,name,avatar');
            },
        ])->where('slug', $slug)->active()->firstOrFail();

        // Increment view count
        $product->increment('view_count');

        // Get related products
        $relatedProducts = Product::with(['images'])
            ->active()
            ->inStock()
            ->where('id', '!=', $product->id)
            ->where(function ($q) use ($product) {
                $q->where('category_id', $product->category_id)
                  ->orWhere('brand_id', $product->brand_id)
                  ->orWhere('series_id', $product->series_id);
            })
            ->limit(12)
            ->get()
            ->map(function ($p) {
                return $this->formatProduct($p);
            });

        return response()->json([
            'success' => true,
            'data' => [
                'product' => $this->formatProduct($product, true),
                'related_products' => $relatedProducts,
            ],
        ]);
    }

    public function featured(): JsonResponse
    {
        $products = Product::with(['images'])
            ->active()
            ->inStock()
            ->featured()
            ->latest()
            ->limit(20)
            ->get()
            ->map(function ($p) {
                return $this->formatProduct($p);
            });

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    public function flashSaleProducts(): JsonResponse
    {
        $flashSaleProducts = Product::with(['images'])
            ->active()
            ->whereHas('flashSales', function ($q) {
                $q->where('is_active', true)
                  ->where('start_time', '<=', now())
                  ->where('end_time', '>=', now());
            })
            ->get()
            ->map(function ($p) {
                $data = $this->formatProduct($p);
                $activeFlashSale = $p->flashSales()
                    ->where('is_active', true)
                    ->where('start_time', '<=', now())
                    ->where('end_time', '>=', now())
                    ->first();
                $data['flash_sale'] = $activeFlashSale ? [
                    'flash_price' => $activeFlashSale->pivot->flash_price,
                    'max_quantity' => $activeFlashSale->pivot->max_quantity,
                    'sold_quantity' => $activeFlashSale->pivot->sold_quantity,
                    'max_per_customer' => $activeFlashSale->pivot->max_per_customer,
                    'end_time' => $activeFlashSale->end_time,
                ] : null;
                return $data;
            });

        return response()->json([
            'success' => true,
            'data' => $flashSaleProducts,
        ]);
    }

    public function categories(): JsonResponse
    {
        $categories = Category::with('children')
            ->active()
            ->root()
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    public function brands(): JsonResponse
    {
        $brands = \App\Models\Brand::active()->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $brands,
        ]);
    }

    public function series(): JsonResponse
    {
        $series = \App\Models\Series::active()->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $series,
        ]);
    }

    private function formatProduct(Product $product, bool $detailed = false): array
    {
        $data = [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'slug' => $product->slug,
            'short_description' => $product->short_description,
            'regular_price' => $product->regular_price,
            'sale_price' => $product->sale_price,
            'current_price' => $product->current_price,
            'discount_percentage' => $product->discount_percentage,
            'stock_quantity' => $product->stock_quantity,
            'product_type' => $product->product_type,
            'condition' => $product->condition,
            'is_limited_edition' => $product->is_limited_edition,
            'is_featured' => $product->is_featured,
            'average_rating' => $product->average_rating,
            'reviews_count' => $product->reviews_count,
            'sold_count' => $product->sold_count,
            'primary_image' => $product->images->where('is_primary', true)->first()?->image_path
                ?? $product->images->first()?->image_path,
            'images' => $product->images->map(function ($img) {
                return [
                    'id' => $img->id,
                    'url' => $img->image_path,
                    'alt' => $img->alt_text,
                    'is_primary' => $img->is_primary,
                ];
            }),
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug,
            ] : null,
            'brand' => $product->brand ? [
                'id' => $product->brand->id,
                'name' => $product->brand->name,
                'slug' => $product->brand->slug,
            ] : null,
            'series' => $product->series ? [
                'id' => $product->series->id,
                'name' => $product->series->name,
                'slug' => $product->series->slug,
            ] : null,
        ];

        if ($detailed) {
            $data = array_merge($data, [
                'description' => $product->description,
                'scale' => $product->scale,
                'material' => $product->material,
                'year_released' => $product->year_released,
                'limited_quantity' => $product->limited_quantity,
                'weight' => $product->weight,
                'length' => $product->length,
                'width' => $product->width,
                'height' => $product->height,
                'meta_title' => $product->meta_title,
                'meta_description' => $product->meta_description,
                'variants' => $product->variants->map(function ($v) {
                    return [
                        'id' => $v->id,
                        'name' => $v->name,
                        'sku' => $v->sku,
                        'price_adjustment' => $v->price_adjustment,
                        'stock_quantity' => $v->stock_quantity,
                        'final_price' => $product->current_price + $v->price_adjustment,
                    ];
                }),
                'tags' => $product->tags->pluck('tag_name'),
                'reviews' => $product->reviews->map(function ($r) {
                    return [
                        'id' => $r->id,
                        'user_name' => $r->user->name,
                        'user_avatar' => $r->user->avatar,
                        'rating' => $r->rating,
                        'review' => $r->review,
                        'admin_reply' => $r->admin_reply,
                        'is_verified_purchase' => $r->is_verified_purchase,
                        'created_at' => $r->created_at,
                    ];
                }),
                'preorder' => $product->isPreorder() ? [
                    'start_date' => $product->preorder_start_date,
                    'end_date' => $product->preorder_end_date,
                    'estimated_ship' => $product->preorder_estimated_ship,
                ] : null,
            ]);
        }

        return $data;
    }
}
