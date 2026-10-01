<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()
                ->constrained('branches')->restrictOnDelete();
            $table->string('name', 160);
            $table->string('email', 100)->unique();
            $table->string('password', 255);
            $table->string('phone', 30)->nullable();
            $table->enum('role', ['owner', 'admin', 'cashier']);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        // owner => branch_id NULL; admin/cashier => branch_id wajib (ERD §4.1, §7)
        DB::statement("
            ALTER TABLE users
            ADD CONSTRAINT chk_users_role_branch
            CHECK (
                (role = 'owner' AND branch_id IS NULL)
                OR (role <> 'owner' AND branch_id IS NOT NULL)
            )
        ");

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};