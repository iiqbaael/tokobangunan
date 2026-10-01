<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_main')->default(false);

            // Generated column: 1 jika cabang utama, NULL jika bukan.
            // UNIQUE menjamin paling banyak satu is_main = 1 (NULL boleh berulang).
            $table->tinyInteger('main_flag')->nullable()
                ->virtualAs('IF(is_main = 1, 1, NULL)');
            $table->unique('main_flag');

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};