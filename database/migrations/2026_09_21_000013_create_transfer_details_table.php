<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained('transfers')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->decimal('qty_sent', 15, 4);
            $table->decimal('qty_received', 15, 4)->default(0);
            $table->decimal('unit_cost', 15, 4);
            $table->enum('difference_resolution', ['none', 'returned', 'written_off'])->default('none');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE transfer_details ADD CONSTRAINT chk_td_qty_sent CHECK (qty_sent > 0)');
        DB::statement('ALTER TABLE transfer_details ADD CONSTRAINT chk_td_qty_received CHECK (qty_received >= 0 AND qty_received <= qty_sent)');
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_details');
    }
};