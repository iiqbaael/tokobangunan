<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->string('noteable_type', 100);
            $table->unsignedBigInteger('noteable_id');
            $table->enum('type', ['general', 'pembayaran', 'titipan'])->default('general');
            $table->text('body');
            $table->decimal('amount', 15, 2)->nullable();
            $table->date('follow_up_date')->nullable();
            $table->boolean('is_done')->default(false);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['noteable_type', 'noteable_id']);
            $table->index(['follow_up_date', 'is_done']);
        });

        DB::statement("ALTER TABLE notes ADD CONSTRAINT chk_notes_pembayaran CHECK (type <> 'pembayaran' OR (amount IS NOT NULL AND amount <> 0))");
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};