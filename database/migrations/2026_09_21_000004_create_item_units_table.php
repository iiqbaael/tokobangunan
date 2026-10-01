<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->string('unit', 20); // validasi service: unit <> items.unit
            $table->decimal('conversion_qty', 15, 4);
            $table->string('barcode', 100)->nullable()->unique();
            $table->decimal('sell_price', 15, 2)->nullable(); // NULL = otomatis
            $table->timestamps();

            $table->unique(['item_id', 'unit']);
        });

        DB::statement('
            ALTER TABLE item_units
            ADD CONSTRAINT chk_item_units_conversion CHECK (conversion_qty > 0)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('item_units');
    }
};