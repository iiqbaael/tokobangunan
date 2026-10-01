<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustment_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained('stock_adjustments')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->decimal('qty', 15, 4);
            $table->decimal('unit_cost', 15, 4)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE stock_adjustment_details ADD CONSTRAINT chk_sad_qty CHECK (qty > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_details');
    }
};