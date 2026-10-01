<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_headers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('cashier_shift_id')->constrained('cashier_shifts')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()
                ->constrained('customers')->restrictOnDelete(); // wajib jika ada pembayaran credit
            $table->string('invoice_no', 50)->unique();
            $table->dateTime('sale_date');
            $table->decimal('total', 15, 2)->default(0); // = SUM(sales_details.subtotal)
            $table->decimal('cash_received', 15, 2)->nullable();
            $table->decimal('change_given', 15, 2)->nullable();
            $table->enum('status', ['completed', 'void'])->default('completed');
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'sale_date']);
        });

        DB::statement('
            ALTER TABLE sales_headers
            ADD CONSTRAINT chk_sales_headers_total CHECK (total >= 0)
        ');

        DB::statement('
            ALTER TABLE sales_headers
            ADD CONSTRAINT chk_sales_headers_cash CHECK (
                (cash_received IS NULL OR cash_received >= 0)
                AND (change_given IS NULL OR change_given >= 0)
            )
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_headers');
    }
};