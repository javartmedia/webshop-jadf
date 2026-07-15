<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['images', 'category', 'brand'])
            ->active()
            ->inStock();

        // Search
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('sku', 'like', '%' . $request->search . '%');
            });
        }

        // Filters
        if ($request->filled('category')) {
            $query->whereHas('category', fn($q) => $q->where('slug', $request->category));
        }
        if ($request->filled('brand')) {
            $query->whereHas('brand', fn($q) => $q->where('slug', $request->brand));
        }
        if ($request->filled('condition')) {
            $query->where('condition', $request->condition);
        }
        if ($request->filled('product_type')) {
            $query->where('product_type', $request->product_type);
        }
        if ($request->filled('min_price')) {
            $query->where(function ($q) use ($request) {
                $q->where('sale_price', '>=', $request->min_price)
                  ->orWhere(fn($sq) => $sq->whereNull('sale_price')->where('regular_price', '>=', $request->min_price));
            });
        }
        if ($request->filled('max_price')) {
            $query->where(function ($q) use ($request) {
                $q->where('sale_price', '<=', $request->max_price)
                  ->orWhere(fn($sq) => $sq->whereNull('sale_price')->where('regular_price', '<=', $request->max_price));
            });
        }

        // Sorting
        $sortOptions = [
            'newest' => ['created_at', 'desc'],
            'oldest' => ['created_at', 'asc'],
            'price_asc' => ['regular_price', 'asc'],
            'price_desc' => ['regular_price', 'desc'],
            'name_asc' => ['name', 'asc'],
            'name_desc' => ['name', 'desc'],
            'bestseller' => ['sold_count', 'desc'],
        ];
        $sort = $request->get('sort', 'newest');
        if (isset($sortOptions[$sort])) {
            $query->orderBy($sortOptions[$sort][0], $sortOptions[$sort][1]);
        }

        $products = $query->paginate(24)->appends($request->query());

        $conditions = ['mint', 'near_mint', 'excellent', 'good', 'fair', 'poor', 'carded'];
        $productTypes = ['standard', 'collector_edition', 'preorder'];

        return view('frontend.products.index', compact('products', 'conditions', 'productTypes'));
    }

    public function show(string $slug)
    {
        $product = Product::with([
            'images',
            'category',
            'brand',
            'series',
            'variants' => fn($q) => $q->where('is_active', true),
            'tags',
            'reviews' => fn($q) => $q->where('is_approved', true)->with('user:id,name,avatar')->latest(),
        ])
        ->where('slug', $slug)
        ->active()
        ->firstOrFail();

        $product->increment('view_count');

        $relatedProducts = Product::with('images')
            ->active()
            ->inStock()
            ->where('id', '!=', $product->id)
            ->where(function ($q) use ($product) {
                $q->where('category_id', $product->category_id)
                  ->orWhere('brand_id', $product->brand_id)
                  ->orWhere('series_id', $product->series_id);
            })
            ->limit(12)
            ->get();

        return view('frontend.products.show', compact('product', 'relatedProducts'));
    }

    public function byCategory(string $slug)
    {
        $category = \App\Models\Category::where('slug', $slug)->active()->firstOrFail();
        $categoryIds = $category->getAllChildrenIds();

        $products = Product::with(['images', 'brand'])
            ->active()
            ->inStock()
            ->whereIn('category_id', $categoryIds)
            ->latest()
            ->paginate(24);

        return view('frontend.products.by-category', compact('products', 'category'));
    }

    public function byBrand(string $slug)
    {
        $brand = \App\Models\Brand::where('slug', $slug)->active()->firstOrFail();

        $products = Product::with(['images', 'category'])
            ->active()
            ->inStock()
            ->where('brand_id', $brand->id)
            ->latest()
            ->paginate(24);

        return view('frontend.products.by-brand', compact('products', 'brand'));
    }

    public function flashSale()
    {
        $activeFlashSale = \App\Models\FlashSale::with(['products' => function ($q) {
            $q->active()->with('images');
        }])
        ->where('is_active', true)
        ->where('start_time', '<=', now())
        ->where('end_time', '>=', now())
        ->first();

        $products = collect();
        if ($activeFlashSale) {
            $products = $activeFlashSale->products->map(function ($product) use ($activeFlashSale) {
                $product->flash_price = $activeFlashSale->pivot->flash_price ?? $product->current_price;
                $product->flash_max_qty = $activeFlashSale->pivot->max_quantity ?? 0;
                $product->flash_sold_qty = $activeFlashSale->pivot->sold_quantity ?? 0;
                return $product;
            });
        }

        return view('frontend.products.flash-sale', compact('activeFlashSale', 'products'));
    }

    public function search(Request $request)
    {
        $query = Product::with(['images', 'category'])
            ->active()
            ->inStock();

        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('tags', fn($tq) => $tq->where('tag_name', 'like', "%{$search}%"));
            });
        }

        $products = $query->latest()->paginate(24)->appends($request->query());

        return view('frontend.products.search', compact('products'));
    }
}
