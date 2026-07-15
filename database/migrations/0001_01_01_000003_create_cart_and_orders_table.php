<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('session_id', 100)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'session_id']);
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
            $table->unique(['cart_id', 'product_id', 'product_variant_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('order_number', 30)->unique();
            $table->enum('status', [
                'pending', 'confirmed', 'processing', 'shipped', 'delivered',
                'completed', 'cancelled', 'refunded', 'partially_refunded'
            ])->default('pending');
            $table->enum('payment_status', [
                'unpaid', 'pending', 'paid', 'failed', 'expired', 'refunded'
            ])->default('unpaid');
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_gateway', 30)->nullable(); // midtrans, manual
            $table->string('payment_transaction_id')->nullable();
            $table->string('payment_snap_token')->nullable();
            $table->text('payment_response')->nullable();
            $table->decimal('subtotal', 15, 2);
            $table->decimal('shipping_cost', 15, 2)->default(0.00);
            $table->decimal('discount_amount', 15, 2)->default(0.00);
            $table->decimal('voucher_discount', 15, 2)->default(0.00);
            $table->decimal('loyalty_discount', 15, 2)->default(0.00);
            $table->decimal('tax_amount', 15, 2)->default(0.00);
            $table->decimal('grand_total', 15, 2);
            $table->string('shipping_courier', 50)->nullable();
            $table->string('shipping_service', 100)->nullable();
            $table->string('shipping_awb', 50)->nullable();
            $table->text('shipping_address');
            $table->string('shipping_city', 100);
            $table->string('shipping_province', 100);
            $table->string('shipping_postal_code', 10);
            $table->string('shipping_phone', 20);
            $table->string('recipient_name');
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->foreignId('voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            $table->integer('loyalty_points_used')->default(0);
            $table->integer('loyalty_points_earned')->default(0);
            $table->timestamps();

            $table->index('order_number');
            $table->index(['user_id', 'status']);
            $table->index('payment_status');
            $table->index('status');
            $table->index('created_at');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('product_name');
            $table->string('product_sku', 50);
            $table->integer('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
            $table->index(['order_id', 'product_id']);
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('discount_type', ['percentage', 'fixed']);
            $table->decimal('discount_value', 15, 2);
            $table->decimal('min_purchase', 15, 2)->default(0.00);
            $table->decimal('max_discount', 15, 2)->nullable();
            $table->integer('usage_limit')->default(0); // 0 = unlimited
            $table->integer('usage_count')->default(0);
            $table->integer('max_per_user')->default(1);
            $table->timestamp('start_date');
            $table->timestamp('end_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['code', 'is_active']);
            $table->index(['start_date', 'end_date']);
        });

        Schema::create('voucher_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained('vouchers');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('order_id')->constrained('orders');
            $table->decimal('discount_amount', 15, 2);
            $table->timestamps();
            $table->unique(['voucher_id', 'user_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_usages');
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('vouchers');
    }
};
