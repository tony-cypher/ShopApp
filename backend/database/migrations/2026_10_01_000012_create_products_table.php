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
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('compare_at_price', 10, 2)->nullable();
            $table->string('emoji', 16)->default('🛍️');
            $table->string('image_url')->nullable();
            $table->string('badge', 16)->nullable(); // e.g. "top" -> yellow "Top Item" badge
            $table->boolean('featured')->default(false); // purple hero-style card
            $table->boolean('is_deal')->default(false);  // shows under the "Deals" pill
            $table->decimal('rating', 3, 2)->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->json('rating_chips')->nullable();     // floating rating chips on featured cards
            $table->json('options')->nullable();          // {"sizes": [...], "colors": [...]}
            $table->boolean('delivery_standard')->default(true);
            $table->boolean('delivery_pickup')->default(false);
            $table->unsignedInteger('stock')->default(100);
            $table->timestamps();

            $table->index(['category_id', 'is_deal']);
            $table->index('featured');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
