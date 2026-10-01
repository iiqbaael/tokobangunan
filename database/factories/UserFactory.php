<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 *
 * Default: role 'cashier' dengan branch_id terisi, sesuai CHECK
 * chk_users_role_branch (ERD §4.1, §7). Tidak ada email_verified_at
 * -- skema ini sengaja tidak pakai MustVerifyEmail (ERD §4.1).
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'phone' => fake()->numerify('08##########'),
            'role' => UserRole::Cashier,
            'is_active' => true,
            'last_login_at' => null,
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Owner,
            'branch_id' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
            'branch_id' => $attributes['branch_id'] ?? Branch::factory(),
        ]);
    }

    public function cashier(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Cashier,
            'branch_id' => $attributes['branch_id'] ?? Branch::factory(),
        ]);
    }

    public function unverified(): static
    {
        return $this;
    }
}