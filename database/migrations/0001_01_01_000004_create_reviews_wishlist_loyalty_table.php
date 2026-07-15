<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->integer('rating')->comment('1-5 stars');
            $table->text('review')->nullable();
            $table->text('admin_reply')->nullable();
            $table->timestamp('admin_reply_at')->nullable();
            $table->boolean('is_verified_purchase')->default(false);
            $table->boolean('is_approved')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'product_id', 'order_id']);
            $table->index(['product_id', 'is_approved']);
            $table->index(['product_id', 'rating']);
        });

        Schema::create('review_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('reviews')->cascadeOnDelete();
            $table->string('image_path');
            $table->timestamps();
        });

        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'product_id']);
            $table->index('user_id');
        });

        Schema::create('loyalty_point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('type'); // earn, redeem, expire, adjustment
            $table->integer('points');
            $table->integer('balance_before');
            $table->integer('balance_after');
            $table->text('description')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'type']);
            $table->index('created_at');
        });

        Schema::create('loyalty_point_configs', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Insert default loyalty configs
        DB::table('loyalty_point_configs')->insert([
            ['key' => 'earn_rate_percentage', 'value' => '1', 'description' => 'Percentage of order total earned as points', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'redemption_rate', 'value' => '100', 'description' => '1 point = 1 IDR redemption rate', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'minimum_redemption_points', 'value' => '1000', 'description' => 'Minimum points required for redemption', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'points_expiry_days', 'value' => '365', 'description' => 'Points expiry in days', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'registration_bonus_points', 'value' => '500', 'description' => 'Bonus points on registration', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_point_configs');
        Schema::dropIfExists('loyalty_point_transactions');
        Schema::dropIfExists('wishlists');
        Schema::dropIfExists('review_images');
        Schema::dropIfExists('reviews');
    }
};
