<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales_headers')->restrictOnDelete();
            $table->enum('method', ['cash', 'transfer', 'ewallet', 'card', 'credit']);
            $table->decimal('amount', 15, 2);
            $table->string('reference_no', 100)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        DB::statement('
            ALTER TABLE sale_payments
            ADD CONSTRAINT chk_sale_payments_amount CHECK (amount > 0)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
    }
};