<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Branch>
 *
 * Default: cabang biasa (is_main = false), supaya factory aman dipakai
 * berkali-kali tanpa melanggar UNIQUE main_flag (ERD §4.1, §5 no. 22).
 * Cabang utama dibuat eksplisit lewat state main().
 */
class BranchFactory extends Factory
{
    protected static int $sequence = 0;

    public function definition(): array
    {
        static::$sequence++;

        return [
            'code' => 'CB'.str_pad((string) static::$sequence, 2, '0', STR_PAD_LEFT),
            'name' => fake()->unique()->city().' Branch',
            'address' => fake()->address(),
            'phone' => fake()->numerify('021#######'),
            'is_main' => false,
            'is_active' => true,
        ];
    }

    /**
     * Cabang utama. Hati-hati: UNIQUE main_flag menolak lebih dari satu
     * baris is_main = true dalam satu test/seed (ERD §5 no. 22).
     */
    public function main(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_main' => true,
        ]);
    }
}