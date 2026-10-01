<?php

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\StockBalance;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('item index filters low stock by category and the users branch', function () {
    $branch = Branch::create(['code' => 'ITEM1', 'name' => 'Cabang Barang', 'is_main' => true]);
    $otherBranch = Branch::create(['code' => 'ITEM2', 'name' => 'Cabang Lain', 'is_main' => false]);
    $user = User::create([
        'branch_id' => $branch->id,
        'name' => 'Admin Barang',
        'email' => 'items-'.uniqid().'@toko.test',
        'password' => 'password',
        'role' => UserRole::Admin,
    ]);
    $category = Category::create(['name' => 'Bahan Uji', 'is_active' => true]);
    $lowStockItem = Item::create([
        'category_id' => $category->id,
        'code' => 'ITEM-LOW',
        'name' => 'Barang Stok Rendah',
        'unit' => 'pcs',
        'sell_price' => 1000,
    ]);
    $normalStockItem = Item::create([
        'category_id' => $category->id,
        'code' => 'ITEM-OK',
        'name' => 'Barang Stok Aman',
        'unit' => 'pcs',
        'sell_price' => 1000,
    ]);
    $otherBranchItem = Item::create([
        'category_id' => $category->id,
        'code' => 'ITEM-OTHER',
        'name' => 'Barang Cabang Lain',
        'unit' => 'pcs',
        'sell_price' => 1000,
    ]);

    StockBalance::create(['branch_id' => $branch->id, 'item_id' => $lowStockItem->id, 'qty' => 2, 'avg_cost' => 500, 'min_stock' => 5]);
    StockBalance::create(['branch_id' => $branch->id, 'item_id' => $normalStockItem->id, 'qty' => 10, 'avg_cost' => 500, 'min_stock' => 5]);
    StockBalance::create(['branch_id' => $otherBranch->id, 'item_id' => $otherBranchItem->id, 'qty' => 1, 'avg_cost' => 500, 'min_stock' => 5]);

    $this->actingAs($user)
        ->get(route('items.index', ['q' => 'ITEM-LOW', 'category_id' => $category->id, 'stock' => 'low']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Items/Index')
            ->has('items.data', 1)
            ->where('items.data.0.id', $lowStockItem->id)
            ->where('items.data.0.stock', 2)
            ->where('items.data.0.min_stock', 5)
            ->where('filters.q', 'ITEM-LOW')
            ->where('filters.category_id', $category->id)
            ->where('filters.stock', 'low')
            ->where('stockBranchAvailable', true));
});