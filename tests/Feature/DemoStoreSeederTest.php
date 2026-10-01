<?php

use Database\Seeders\DemoStoreSeeder;
use Database\Seeders\BranchSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\DB;

test('demo store seeder builds a realistic repeatable operating dataset', function () {
    app(BranchSeeder::class)->run();
    app(UserSeeder::class)->run();
    $seeder = app(DemoStoreSeeder::class);
    app(BranchSeeder::class)->run();
    app(UserSeeder::class)->run();
    $seeder->run();

    $firstCounts = collect([
        'branches',
        'users',
        'categories',
        'items',
        'item_units',
        'customers',
        'stock_balances',
        'stock_mutations',
        'stock_adjustments',
        'stock_adjustment_details',
        'transfers',
        'transfer_details',
        'cashier_shifts',
        'sales_headers',
        'sales_details',
        'sale_payments',
        'cash_movements',
        'notes',
        'audit_logs',
    ])->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()]);

    $seeder->run();

    $secondCounts = $firstCounts->keys()
        ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()]);

    expect($firstCounts->get('items'))->toBeGreaterThanOrEqual(25)
        ->and($firstCounts->get('customers'))->toBeGreaterThanOrEqual(8)
        ->and($firstCounts->get('sales_headers'))->toBeGreaterThanOrEqual(20)
        ->and(DB::table('cashier_shifts')->where('status', 'open')->count())->toBe(2)
        ->and($secondCounts->all())->toBe($firstCounts->all());
});