<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sliders', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->string('image');
            $table->string('mobile_image')->nullable();
            $table->string('link')->nullable();
            $table->string('button_text')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('content');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('template')->default('default');
            $table->boolean('is_active')->default(true);
            $table->boolean('show_in_menu')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index(['slug', 'is_active']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('text'); // text, json, image, boolean
            $table->string('group')->default('general');
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });

        // Insert default settings
        DB::table('settings')->insert([
            ['key' => 'site_name', 'value' => 'Wenshop Hot Wheels', 'type' => 'text', 'group' => 'general', 'label' => 'Site Name', 'is_public' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'site_description', 'value' => 'Premium Hot Wheels Collectibles Store', 'type' => 'text', 'group' => 'general', 'label' => 'Site Description', 'is_public' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'site_email', 'value' => 'support@wenshop-hotwheels.com', 'type' => 'text', 'group' => 'general', 'label' => 'Site Email', 'is_public' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'site_phone', 'value' => '+6281234567890', 'type' => 'text', 'group' => 'general', 'label' => 'Site Phone', 'is_public' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'site_logo', 'value' => null, 'type' => 'image', 'group' => 'general', 'label' => 'Site Logo', 'is_public' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'site_favicon', 'value' => null, 'type' => 'image', 'group' => 'general', 'label' => 'Favicon', 'is_public' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'midtrans_merchant_id', 'value' => null, 'type' => 'text', 'group' => 'payment', 'label' => 'Midtrans Merchant ID', 'is_public' => false, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'midtrans_client_key', 'value' => null, 'type' => 'text', 'group' => 'payment', 'label' => 'Midtrans Client Key', 'is_public' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'midtrans_server_key', 'value' => null, 'type' => 'text', 'group' => 'payment', 'label' => 'Midtrans Server Key', 'is_public' => false, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'rajaongkir_api_key', 'value' => null, 'type' => 'text', 'group' => 'shipping', 'label' => 'RajaOngkir API Key', 'is_public' => false, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'rajaongkir_origin_city', 'value' => '151', 'type' => 'text', 'group' => 'shipping', 'label' => 'Origin City ID (Jakarta Pusat)', 'is_public' => false, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'social_facebook', 'value' => null, 'type' => 'text', 'group' => 'social', 'label' => 'Facebook URL', 'is_public' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'social_instagram', 'value' => null, 'type' => 'text', 'group' => 'social', 'label' => 'Instagram URL', 'is_public' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'social_twitter', 'value' => null, 'type' => 'text', 'group' => 'social', 'label' => 'Twitter URL', 'is_public' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 20)->nullable();
            $table->string('subject');
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->text('staff_reply')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
        });

        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_token', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('sliders');
    }
};
