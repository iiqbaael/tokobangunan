<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->decimal('qty', 15, 4)->default(0);
            $table->decimal('avg_cost', 15, 4)->default(0);
            $table->decimal('min_stock', 15, 4)->default(0); // per cabang
            $table->timestamp('updated_at')->nullable();

            $table->unique(['branch_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
    }
};