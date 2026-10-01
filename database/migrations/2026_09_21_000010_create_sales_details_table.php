<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales_headers')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->string('unit', 20);                          // satuan yang dipilih kasir
            $table->decimal('qty_input', 15, 4);                 // input kasir
            $table->decimal('conversion_qty', 15, 4)->default(1); // snapshot
            $table->decimal('qty', 15, 4);                       // = qty_input x conversion_qty
            $table->decimal('unit_price', 15, 2);                // per satuan input
            $table->decimal('subtotal', 15, 2);                  // ROUND(qty_input x unit_price)
            $table->decimal('cost_at_sale', 15, 4);              // snapshot avg_cost per satuan dasar
            $table->timestamps();
        });

        DB::statement('
            ALTER TABLE sales_details
            ADD CONSTRAINT chk_sales_details_qty CHECK (qty_input > 0)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_details');
    }
};