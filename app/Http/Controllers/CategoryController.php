<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    /**
     * Semua user login boleh lihat (dipakai POS/master data), tapi hanya
     * yang lolos category.manage yang lihat tombol create/edit/delete di UI
     * (canManage dikirim ke Vue). Penegakan sesungguhnya tetap di
     * store/update/destroy lewat Gate::authorize.
     */
    public function index(): Response
    {
        return Inertia::render('Categories/Index', [
            'categories' => Category::query()
                ->orderBy('name')
                ->get(['id', 'name', 'is_active']),
            'canManage' => Gate::allows('category.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('category.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:categories,name'],
        ]);

        Category::create([
            'name' => $validated['name'],
            'is_active' => true,
        ]);

        return back()->with('success', 'Kategori ditambahkan.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        Gate::authorize('category.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:categories,name,' . $category->id],
            'is_active' => ['required', 'boolean'],
        ]);

        $category->update($validated);

        return back()->with('success', 'Kategori diperbarui.');
    }

    /**
     * ERD: tidak ada soft delete untuk categories (§4.2/§8), tapi FK
    * categories->items pakai ON DELETE RESTRICT -- kalau masih dipakai
    * barang (termasuk yang di-soft-delete), DB akan menolak. Dicek dulu
    * di sini biar pesannya ramah, bukan error 500 dari MySQL.
     */
    public function destroy(Category $category): RedirectResponse
    {
        Gate::authorize('category.manage');

        if ($category->items()->withTrashed()->exists()) {
            return back()->with('error', 'Kategori tidak bisa dihapus karena masih dipakai barang.');
        }

        $category->delete();

        return back()->with('success', 'Kategori dihapus.');
    }
}