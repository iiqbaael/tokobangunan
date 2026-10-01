<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Branch;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            ['code' => 'CBU', 'name' => 'Cabang Utama', 'is_main' => 1],
            ['code' => 'CB2', 'name' => 'Cabang 2', 'is_main' => 0],
        ];

        foreach ($branches as $branch) {
            Branch::firstOrCreate(
                ['code' => $branch['code']],
                $branch + [
                    'is_active' => true,
                ]
            );
        }
    }
}