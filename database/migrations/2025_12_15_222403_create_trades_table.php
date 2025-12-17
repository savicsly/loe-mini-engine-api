<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('buy_order_id')->constrained('orders');
            $table->foreignUlid('sell_order_id')->constrained('orders');
            $table->decimal('price', 20, 8);
            $table->decimal('amount', 20, 8);
            $table->decimal('commission', 20, 8);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
