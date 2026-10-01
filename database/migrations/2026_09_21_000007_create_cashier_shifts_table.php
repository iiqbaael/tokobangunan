<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete(); // pembuka shift
            $table->foreignId('closed_by')->nullable()
                ->constrained('users')->restrictOnDelete(); // penutup, NULL selama open
            $table->decimal('opening_balance', 15, 2);
            $table->decimal('closing_balance', 15, 2)->nullable();
            $table->decimal('expected_balance', 15, 2)->nullable();
            $table->decimal('difference', 15, 2)->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');

            // Generated column: branch_id jika open, NULL jika closed.
            // UNIQUE => satu shift open per cabang (ERD §4.4).
            $table->unsignedBigInteger('open_branch_id')->nullable()
                ->virtualAs("IF(status = 'open', branch_id, NULL)");
            $table->unique('open_branch_id');

            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_shifts');
    }
};