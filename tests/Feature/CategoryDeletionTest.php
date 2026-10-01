<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;

test('category cannot be deleted while a soft-deleted item references it', function () {
    $owner = User::create([
        'branch_id' => null,
        'name' => 'Owner Category Test',
        'email' => 'category-delete-'.uniqid().'@toko.test',
        'password' => 'password',
        'role' => UserRole::Owner,
    ]);
    $category = Category::create([
        'name' => 'Kategori Terarsip',
        'is_active' => true,
    ]);
    $item = Item::create([
        'category_id' => $category->id,
        'code' => 'ARCH-CAT-ITEM',
        'name' => 'Barang Terarsip',
        'unit' => 'pcs',
        'sell_price' => 10000,
    ]);
    $item->delete();

    $this->actingAs($owner)
        ->from(route('categories.index'))
        ->delete(route('categories.destroy', $category))
        ->assertRedirect(route('categories.index'))
        ->assertSessionHas('error', 'Kategori tidak bisa dihapus karena masih dipakai barang.');

    expect(Category::find($category->id))->not->toBeNull();
});