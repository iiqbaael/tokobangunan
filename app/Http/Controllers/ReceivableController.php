<?php

namespace App\Http\Controllers;

use App\Enums\NoteType;
use App\Http\Controllers\Concerns\ResolvesActiveBranch;
use App\Models\Customer;
use App\Models\Note;
use App\Services\ReceivableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Piutang pelanggan & catatan (ERD §5 no. 8, §2 no. 20, §4.7).
 * Seluruh aksi di sini butuh ability notes.manage (admin, owner) -- Kasir
 * tidak pernah menyentuh piutang. Perhitungan saldo & aturan "tidak boleh
 * negatif" ada di ReceivableService, controller ini hanya validasi input
 * + terjemahkan exception service jadi pesan flash.
 */
class ReceivableController extends Controller
{
    use ResolvesActiveBranch;

    public function __construct(private readonly ReceivableService $receivables)
    {
    }

    /**
     * Dashboard saldo piutang (turunan dari sale_payments, §5 no. 8) +
     * daftar catatan titipan/pengingat yang belum selesai.
     *
     * Saldo dihitung per pelanggan lewat ReceivableService::balance().
     * Skala toko ini kecil (1 toko, 2 cabang) jadi query per-pelanggan
     * di dalam loop cukup -- kalau jumlah pelanggan sudah besar, ganti ke
     * satu query agregat di ReceivableService.
     */
    public function index(): Response
    {
        Gate::authorize('notes.manage');

        $customers = Customer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone'])
            ->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'balance' => $this->receivables->balance($customer),
            ])
            ->values();

        $reminders = Note::query()
            ->where('noteable_type', (new Customer())->getMorphClass())
            ->whereIn('type', [NoteType::General, NoteType::Titipan])
            ->where('is_done', false)
            ->orderByRaw('follow_up_date IS NULL, follow_up_date')
            ->with('creator:id,name')
            ->get(['id', 'noteable_id', 'type', 'body', 'amount', 'follow_up_date', 'is_done', 'created_by'])
            ->map(fn (Note $note) => [
                'id' => $note->id,
                'customer_id' => $note->noteable_id,
                'type' => $note->type->value,
                'body' => $note->body,
                'amount' => $note->amount,
                'follow_up_date' => $note->follow_up_date?->toDateString(),
                'is_done' => $note->is_done,
                'created_by' => $note->creator?->name,
            ]);

        return Inertia::render('Receivables/Index', [
            'customers' => $customers,
            'reminders' => $reminders,
        ]);
    }

    /**
     * Pelunasan piutang (§5 no. 8). Kalau cash=true, wajib ada shift 'open'
     * di cabang aktif user -- dicatat sekaligus sebagai cash_movements.
     */
    public function pay(Request $request, Customer $customer): RedirectResponse
    {
        Gate::authorize('notes.manage');

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'cash' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], [
            'amount' => 'jumlah',
            'cash' => 'tunai',
        ]);

        $branchId = $validated['cash'] ? $this->activeBranchId($request) : null;

        try {
            $this->receivables->pay(
                $customer,
                $request->user(),
                $validated['amount'],
                $validated['cash'],
                $branchId,
                $validated['note'] ?? null,
            );
        } catch (RuntimeException|InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pelunasan piutang dicatat.');
    }

    /**
     * Koreksi pelunasan (§2 no. 20): baris notes baru bernilai negatif,
     * bukan update/delete baris asli. $was_cash menandakan pelunasan yang
     * dikoreksi dulunya tunai (supaya cash_movements ikut dibalik).
     */
    public function correct(Request $request, Customer $customer): RedirectResponse
    {
        Gate::authorize('notes.manage');

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'was_cash' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], [
            'amount' => 'jumlah',
            'was_cash' => 'asalnya tunai',
        ]);

        $branchId = $validated['was_cash'] ? $this->activeBranchId($request) : null;

        try {
            $this->receivables->correct(
                $customer,
                $request->user(),
                $validated['amount'],
                $validated['was_cash'],
                $branchId,
                $validated['note'] ?? null,
            );
        } catch (RuntimeException|InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Koreksi pelunasan dicatat.');
    }

    /**
     * Catatan titipan (uang customer yang ditahan toko, type=titipan) atau
     * pengingat bebas (type=general). Bukan bagian piutang, jadi tidak
     * lewat ReceivableService -- langsung ke model Note.
     */
    public function storeNote(Request $request, Customer $customer): RedirectResponse
    {
        Gate::authorize('notes.manage');

        $validated = $request->validate([
            'type' => ['required', 'in:general,titipan'],
            'body' => ['required', 'string'],
            'amount' => ['required_if:type,titipan', 'nullable', 'numeric', 'min:0.01'],
            'follow_up_date' => ['nullable', 'date'],
        ], [], [
            'body' => 'catatan',
            'amount' => 'jumlah',
            'follow_up_date' => 'tanggal tindak lanjut',
        ]);

        Note::create([
            'noteable_type' => $customer->getMorphClass(),
            'noteable_id' => $customer->id,
            'type' => NoteType::from($validated['type']),
            'body' => $validated['body'],
            'amount' => $validated['type'] === 'titipan' ? $validated['amount'] : null,
            'follow_up_date' => $validated['follow_up_date'] ?? null,
            'is_done' => false,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Catatan disimpan.');
    }

    /**
     * Tandai selesai/belum untuk catatan titipan & pengingat. Catatan
     * type='pembayaran' tidak boleh diubah lewat sini (§2 no. 20, immutable) --
     * hanya dikoreksi lewat correct() di atas.
     */
    public function toggleNote(Note $note): RedirectResponse
    {
        Gate::authorize('notes.manage');

        if ($note->type === NoteType::Pembayaran) {
            abort(403, 'Catatan pelunasan tidak boleh diubah, gunakan koreksi.');
        }

        $note->update(['is_done' => ! $note->is_done]);

        return back()->with('success', 'Status catatan diperbarui.');
    }
}