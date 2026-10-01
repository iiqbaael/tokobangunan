<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('transfer_no', 50)->unique();
            $table->date('transfer_date');
            $table->enum('status', ['draft', 'sent', 'partial', 'received', 'cancelled'])->default('draft');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE transfers ADD CONSTRAINT chk_transfers_branches CHECK (from_branch_id <> to_branch_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};