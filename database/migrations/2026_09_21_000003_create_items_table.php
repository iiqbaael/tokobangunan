<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()
                ->constrained('categories')->restrictOnDelete();
            $table->string('code', 50)->unique();
            $table->string('barcode', 100)->nullable()->unique();
            $table->string('name', 150);
            $table->string('brand', 100)->nullable();
            $table->string('specification', 160)->nullable();
            $table->string('unit', 20); // satuan dasar, immutable setelah ada mutasi stok
            $table->decimal('sell_price', 15, 2)->default(0);
            $table->string('image', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};