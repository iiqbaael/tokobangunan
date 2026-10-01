<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $mainId = DB::table('branches')->where('code', 'CBU')->value('id');

        $users = [
            ['name' => 'Owner', 'email' => 'owner@toko.test', 'role' => 'owner', 'branch_id' => null],
            ['name' => 'Admin Cabang Utama', 'email' => 'admin@toko.test', 'role' => 'admin', 'branch_id' => $mainId],
            ['name' => 'Kasir Cabang Utama', 'email' => 'kasir@toko.test', 'role' => 'cashier', 'branch_id' => $mainId],
        ];

        foreach ($users as $user) {
            User::firstOrCreate(
                ['email' => $user['email']],
                $user + [
                    'password' => 'password',
                    'is_active' => true,
                ]
            );
        }
    }
}