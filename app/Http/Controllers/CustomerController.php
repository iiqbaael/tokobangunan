<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    /**
     * Semua user login boleh lihat (dibutuhkan saat POS mencari pelanggan),
     * tapi hanya yang lolos customer.manage yang lihat tombol create/edit/
     * delete di UI. Penegakan sesungguhnya tetap di store/update/destroy.
     */
    public function index(): Response
    {
        return Inertia::render('Customers/Index', [
            'customers' => Customer::query()
                ->orderBy('name')
                ->get(['id', 'name', 'phone', 'address', 'is_active']),
            'canManage' => Gate::allows('customer.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('customer.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
        ]);

        Customer::create([
            ...$validated,
            'is_active' => true,
        ]);

        return back()->with('success', 'Pelanggan ditambahkan.');
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        Gate::authorize('customer.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);

        $customer->update($validated);

        return back()->with('success', 'Pelanggan diperbarui.');
    }

    /**
     * ERD §4.2: customers soft delete. FK sales_headers->customers pakai
     * ON DELETE RESTRICT, tapi soft delete tidak kena RESTRICT (baris fisik
     * tetap ada) -- jadi tidak perlu dicek exists() seperti kategori.
     */
    public function destroy(Customer $customer): RedirectResponse
    {
        Gate::authorize('customer.manage');

        $customer->delete();

        return back()->with('success', 'Pelanggan dihapus.');
    }
}