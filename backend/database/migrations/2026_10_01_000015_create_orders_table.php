<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('status', 20)->default('confirmed'); // confirmed|shipped|delivered|cancelled
            $table->string('payment_status', 20)->default('test_paid'); // test only
            $table->string('payment_method', 20)->default('test_card');
            $table->string('card_last4', 4)->nullable();
            $table->string('delivery_method', 20)->default('standard'); // standard|pickup
            $table->decimal('subtotal', 10, 2);
            $table->decimal('shipping', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->json('address')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
