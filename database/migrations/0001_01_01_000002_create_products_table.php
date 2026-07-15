<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('series_id')->nullable()->constrained('series')->nullOnDelete();
            $table->string('sku', 50)->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->text('short_description')->nullable();
            $table->decimal('regular_price', 15, 2);
            $table->decimal('sale_price', 15, 2)->nullable();
            $table->decimal('cost_price', 15, 2)->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->integer('low_stock_threshold')->default(5);
            $table->string('product_type')->default('standard'); // standard, collector_edition, preorder
            $table->enum('condition', ['mint', 'near_mint', 'excellent', 'good', 'fair', 'poor', 'carded'])->default('mint');
            $table->string('scale', 20)->nullable();
            $table->string('material')->nullable();
            $table->integer('year_released')->nullable();
            $table->boolean('is_limited_edition')->default(false);
            $table->integer('limited_quantity')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('preorder_start_date')->nullable();
            $table->timestamp('preorder_end_date')->nullable();
            $table->timestamp('preorder_estimated_ship')->nullable();
            $table->decimal('weight', 8, 2)->default(0.00); // in grams
            $table->decimal('length', 8, 2)->default(0.00); // in cm
            $table->decimal('width', 8, 2)->default(0.00);
            $table->decimal('height', 8, 2)->default(0.00);
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();
            $table->integer('view_count')->default(0);
            $table->integer('sold_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'is_active']);
            $table->index(['brand_id', 'is_active']);
            $table->index('slug');
            $table->index('sku');
            $table->index('product_type');
            $table->index('condition');
            $table->index(['is_featured', 'is_active']);
            $table->index('sale_price');
            $table->index('regular_price');
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('image_path');
            $table->string('alt_text')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->index(['product_id', 'sort_order']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name');
            $table->string('sku', 50)->unique();
            $table->decimal('price_adjustment', 15, 2)->default(0.00);
            $table->integer('stock_quantity')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['product_id', 'is_active']);
        });

        Schema::create('product_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('tag_name', 50);
            $table->index(['product_id', 'tag_name']);
        });

        Schema::create('flash_sales', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['start_time', 'end_time', 'is_active']);
        });

        Schema::create('flash_sale_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flash_sale_id')->constrained('flash_sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('flash_price', 15, 2);
            $table->integer('max_quantity')->default(0);
            $table->integer('sold_quantity')->default(0);
            $table->integer('max_per_customer')->default(1);
            $table->timestamps();
            $table->unique(['flash_sale_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_sale_products');
        Schema::dropIfExists('flash_sales');
        Schema::dropIfExists('product_tags');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
    }
};
