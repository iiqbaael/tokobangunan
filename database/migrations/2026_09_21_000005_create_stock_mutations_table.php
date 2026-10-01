<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->dateTime('mutation_date');
            $table->enum('mutation_type', [
                'sale_out',
                'sale_void',
                'transfer_out',
                'transfer_in',
                'adjustment_in',
                'adjustment_out',
            ]);
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('qty', 15, 4); // bertanda +/-
            $table->decimal('unit_cost', 15, 4)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['mutation_date', 'item_id', 'branch_id']);
            $table->index(['reference_type', 'reference_id']);
        });

        // unit_cost wajib untuk mutasi masuk (ERD §4.5, §7)
        DB::statement("
            ALTER TABLE stock_mutations
            ADD CONSTRAINT chk_stock_mutations_cost
            CHECK (
                mutation_type NOT IN ('adjustment_in', 'transfer_in', 'sale_void')
                OR unit_cost IS NOT NULL
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_mutations');
    }
};