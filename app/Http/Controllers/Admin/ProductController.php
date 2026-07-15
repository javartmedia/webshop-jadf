<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Series;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'brand', 'images']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('sku', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            } elseif ($request->status === 'low_stock') {
                $query->where('stock_quantity', '>', 0)
                    ->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
            } elseif ($request->status === 'out_of_stock') {
                $query->where('stock_quantity', 0);
            }
        }

        $products = $query->latest()->paginate(25);
        $categories = Category::active()->get();

        return view('admin.products.index', compact('products', 'categories'));
    }

    public function create()
    {
        $categories = Category::active()->get();
        $brands = Brand::active()->get();
        $series = Series::active()->get();

        return view('admin.products.create', compact('categories', 'brands', 'series'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'series_id' => ['nullable', 'exists:series,id'],
            'description' => ['required', 'string'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'regular_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'product_type' => ['required', 'in:standard,collector_edition,preorder'],
            'condition' => ['required', 'in:mint,near_mint,excellent,good,fair,poor,carded'],
            'scale' => ['nullable', 'string', 'max:20'],
            'material' => ['nullable', 'string', 'max:100'],
            'year_released' => ['nullable', 'integer', 'min:1968'],
            'is_limited_edition' => ['boolean'],
            'limited_quantity' => ['nullable', 'integer', 'min:0'],
            'is_featured' => ['boolean'],
            'is_active' => ['boolean'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'length' => ['nullable', 'numeric', 'min:0'],
            'width' => ['nullable', 'numeric', 'min:0'],
            'height' => ['nullable', 'numeric', 'min:0'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
            'images.*' => ['nullable', 'image', 'max:5120'],
            'tags' => ['nullable', 'string'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $validated['sku'] = 'HW-' . strtoupper(Str::random(8));

        // Ensure unique slug
        $slugCount = Product::where('slug', $validated['slug'])->count();
        if ($slugCount > 0) {
            $validated['slug'] .= '-' . ($slugCount + 1);
        }

        $product = Product::create($validated);

        // Handle images
        if ($request->hasFile('images')) {
            $isPrimary = true;
            foreach ($request->file('images') as $image) {
                $path = $image->store('products', 'public');
                $product->images()->create([
                    'image_path' => $path,
                    'is_primary' => $isPrimary,
                    'alt_text' => $product->name,
                    'sort_order' => 0,
                ]);
                $isPrimary = false;
            }
        }

        // Handle tags
        if (!empty($validated['tags'])) {
            $tags = explode(',', $validated['tags']);
            foreach ($tags as $tag) {
                $tag = trim($tag);
                if (!empty($tag)) {
                    $product->tags()->create(['tag_name' => $tag]);
                }
            }
        }

        return redirect()->route('admin.products.index')
            ->with('success', 'Product created successfully.');
    }

    public function edit(Product $product)
    {
        $categories = Category::active()->get();
        $brands = Brand::active()->get();
        $series = Series::active()->get();

        return view('admin.products.edit', compact('product', 'categories', 'brands', 'series'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'series_id' => ['nullable', 'exists:series,id'],
            'description' => ['required', 'string'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'regular_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'product_type' => ['required', 'in:standard,collector_edition,preorder'],
            'condition' => ['required', 'in:mint,near_mint,excellent,good,fair,poor,carded'],
            'scale' => ['nullable', 'string', 'max:20'],
            'material' => ['nullable', 'string', 'max:100'],
            'year_released' => ['nullable', 'integer', 'min:1968'],
            'is_limited_edition' => ['boolean'],
            'limited_quantity' => ['nullable', 'integer', 'min:0'],
            'is_featured' => ['boolean'],
            'is_active' => ['boolean'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'length' => ['nullable', 'numeric', 'min:0'],
            'width' => ['nullable', 'numeric', 'min:0'],
            'height' => ['nullable', 'numeric', 'min:0'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
            'images.*' => ['nullable', 'image', 'max:5120'],
            'tags' => ['nullable', 'string'],
            'delete_image_ids' => ['nullable', 'string'],
        ]);

        $product->update($validated);

        // Handle image deletions
        if (!empty($validated['delete_image_ids'])) {
            $deleteIds = explode(',', $validated['delete_image_ids']);
            $product->images()->whereIn('id', $deleteIds)->delete();
        }

        // Handle new images
        if ($request->hasFile('images')) {
            // Set primary if no primary exists
            $hasPrimary = $product->images()->where('is_primary', true)->exists();
            foreach ($request->file('images') as $image) {
                $path = $image->store('products', 'public');
                $product->images()->create([
                    'image_path' => $path,
                    'is_primary' => !$hasPrimary,
                    'alt_text' => $product->name,
                    'sort_order' => 0,
                ]);
                $hasPrimary = true;
            }
        }

        // Update tags
        if (isset($validated['tags'])) {
            $product->tags()->delete();
            $tags = explode(',', $validated['tags']);
            foreach ($tags as $tag) {
                $tag = trim($tag);
                if (!empty($tag)) {
                    $product->tags()->create(['tag_name' => $tag]);
                }
            }
        }

        return redirect()->route('admin.products.index')
            ->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->route('admin.products.index')
            ->with('success', 'Product deleted successfully.');
    }

    public function bulkAction(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:products,id'],
            'action' => ['required', 'in:activate,deactivate,feature,unfeature,delete'],
        ]);

        $products = Product::whereIn('id', $validated['ids']);

        switch ($validated['action']) {
            case 'activate':
                $products->update(['is_active' => true]);
                break;
            case 'deactivate':
                $products->update(['is_active' => false]);
                break;
            case 'feature':
                $products->update(['is_featured' => true]);
                break;
            case 'unfeature':
                $products->update(['is_featured' => false]);
                break;
            case 'delete':
                $products->delete();
                break;
        }

        return back()->with('success', 'Bulk action completed successfully.');
    }
}
