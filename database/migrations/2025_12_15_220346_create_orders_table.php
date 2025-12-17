<?php

declare(strict_types=1);

use App\Enum\OrderSide;
use App\Enum\OrderStatus;
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
        Schema::create('orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->onDelete('cascade');
            $table->string('symbol', 10);
            $table->enum('side', OrderSide::cases());
            $table->decimal('price', 15, 8);
            $table->decimal('amount', 20, 8);
            $table->enum('status', OrderStatus::cases());
            $table->timestamps();

            $table->index(['symbol', 'side', 'price', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
