<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashier_shift_id')->constrained('cashier_shifts')->restrictOnDelete();
            $table->enum('type', ['in', 'out']);
            $table->decimal('amount', 15, 2);
            $table->string('reason', 100);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        DB::statement('
            ALTER TABLE cash_movements
            ADD CONSTRAINT chk_cash_movements_amount CHECK (amount > 0)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};