<?php

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('dashboard provides a complete seven-day sales trend', function () {
    $branch = Branch::create([
        'code' => 'DASH',
        'name' => 'Dashboard Test',
        'is_main' => true,
    ]);
    $user = User::create([
        'branch_id' => $branch->id,
        'name' => 'Admin Test',
        'email' => 'dashboard-'.uniqid().'@toko.test',
        'password' => 'password',
        'role' => UserRole::Admin,
    ]);
    $today = now()->startOfDay();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('salesTrend', 7)
            ->where('salesTrend.0.date', $today->copy()->subDays(6)->toDateString())
            ->where('salesTrend.6.date', $today->toDateString())
            ->where('salesTrend.6.total', '0.00')
            ->where('salesTrend.6.count', 0));
});

test('dashboard trend counts completed sales from the users branch only', function () {
    $branch = Branch::create(['code' => 'DASH1', 'name' => 'Cabang Satu', 'is_main' => true]);
    $otherBranch = Branch::create(['code' => 'DASH2', 'name' => 'Cabang Dua', 'is_main' => false]);

    $makeUser = fn (Branch $branch, string $email) => User::create([
        'branch_id' => $branch->id,
        'name' => 'Admin Test',
        'email' => $email,
        'password' => 'password',
        'role' => UserRole::Admin,
    ]);
    $user = $makeUser($branch, 'dashboard-one-'.uniqid().'@toko.test');
    $otherUser = $makeUser($otherBranch, 'dashboard-two-'.uniqid().'@toko.test');

    $makeShift = fn (Branch $branch, User $user) => DB::table('cashier_shifts')->insertGetId([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'opening_balance' => '0.00',
        'status' => 'closed',
        'opened_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $shift = $makeShift($branch, $user);
    $otherShift = $makeShift($otherBranch, $otherUser);

    $makeSale = function (Branch $branch, User $user, int $shiftId, int $daysAgo, string $total, string $status) {
        DB::table('sales_headers')->insert([
            'branch_id' => $branch->id,
            'cashier_shift_id' => $shiftId,
            'invoice_no' => 'DASH-'.uniqid(),
            'sale_date' => now()->subDays($daysAgo),
            'total' => $total,
            'status' => $status,
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    };

    $makeSale($branch, $user, $shift, 1, '100.00', 'completed');
    $makeSale($branch, $user, $shift, 0, '250.00', 'completed');
    $makeSale($branch, $user, $shift, 0, '999.00', 'void');
    $makeSale($otherBranch, $otherUser, $otherShift, 0, '800.00', 'completed');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('salesTrend.5.total', '100.00')
            ->where('salesTrend.5.count', 1)
            ->where('salesTrend.6.total', '250.00')
            ->where('salesTrend.6.count', 1));
});