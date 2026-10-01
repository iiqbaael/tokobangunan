<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ReportController extends Controller
{
    /**
     * Dipakai Kartu Stok (wajib satu cabang).
     * Admin/Cashier: cabangnya sendiri.
     * Owner: dari request, lalu cabang aktif di sesi, lalu cabang utama.
     */
    private function resolveBranchId(Request $request): int
    {
        $user = $request->user();

        if ($user->branch_id !== null) {
            return (int) $user->branch_id;
        }

        return $request->integer('branch_id')
            ?: (int) session('active_branch_id')
            ?: (int) Branch::where('is_main', 1)->value('id');
    }

    private function branchOptions(Request $request)
    {
        if ($request->user()->branch_id !== null) {
            return [];
        }

        return Branch::where('is_active', 1)->orderBy('id')->get(['id', 'code', 'name']);
    }

    public function stock(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'low_stock' => ['nullable', 'boolean'],
        ]);

        $user        = $request->user();
        $isOwner     = $user->branch_id === null;
        $canViewCost = Gate::allows('item.view_cost');
        $lowStock    = (bool) ($data['low_stock'] ?? false);

        // Admin/Cashier dikunci ke cabangnya; Owner kosong = semua cabang.
        $branchId = $isOwner ? ($data['branch_id'] ?? null) : (int) $user->branch_id;

        $query = DB::table('stock_balances as sb')
            ->join('items as i', 'i.id', '=', 'sb.item_id')
            ->join('branches as b', 'b.id', '=', 'sb.branch_id')
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->whereNull('i.deleted_at')
            ->when($branchId, fn ($q) => $q->where('sb.branch_id', $branchId))
            ->when($lowStock, fn ($q) => $q->where('sb.min_stock', '>', 0)->whereColumn('sb.qty', '<=', 'sb.min_stock'))
            ->orderBy('b.code')
            ->orderBy('i.name')
            ->select(
                'b.code as branch_code',
                'i.code as item_code',
                'i.name as item_name',
                'c.name as category_name',
                'i.unit',
                'sb.qty',
                'sb.min_stock',
                'sb.avg_cost'
            );

        $rows = $query->get()->map(function ($r) use ($canViewCost) {
            $r->is_low = bccomp((string) $r->min_stock, '0', 4) > 0
                && bccomp((string) $r->qty, (string) $r->min_stock, 4) <= 0;

            if ($canViewCost) {
                $r->value = bcmul((string) $r->qty, (string) $r->avg_cost, 2);
            } else {
                unset($r->avg_cost);
            }

            return $r;
        });

        return Inertia::render('Report/Stock', [
            'rows'        => $rows,
            'branches'    => $this->branchOptions($request),
            'filters'     => [
                'branch_id' => $branchId,
                'low_stock' => $lowStock,
            ],
            'canViewCost' => $canViewCost,
            'isOwner'     => $isOwner,
        ]);
    }

    public function stockCard(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'item_id'   => ['nullable', 'integer', 'exists:items,id'],
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $branchId    = $this->resolveBranchId($request);
        $canViewCost = Gate::allows('item.view_cost');
        $itemId      = $data['item_id'] ?? null;
        $dateFrom    = $data['date_from'] ?? null;
        $dateTo      = $data['date_to'] ?? null;

        $item    = null;
        $opening = '0.0000';
        $rows    = [];
        $closing = '0.0000';

        if ($itemId) {
            $item = Item::withTrashed()->find($itemId, ['id', 'code', 'name', 'unit']);

            if ($dateFrom) {
                $opening = (string) DB::table('stock_mutations')
                    ->where('branch_id', $branchId)
                    ->where('item_id', $itemId)
                    ->where('mutation_date', '<', $dateFrom . ' 00:00:00')
                    ->sum('qty');
                $opening = bcadd($opening, '0', 4);
            }

            $query = DB::table('stock_mutations')
                ->where('branch_id', $branchId)
                ->where('item_id', $itemId);

            if ($dateFrom) {
                $query->where('mutation_date', '>=', $dateFrom . ' 00:00:00');
            }
            if ($dateTo) {
                $query->where('mutation_date', '<=', $dateTo . ' 23:59:59');
            }

            $mutations = $query->orderBy('mutation_date')->orderBy('id')->get();

            $docNos  = $this->resolveDocumentNumbers($mutations);
            $balance = $opening;

            foreach ($mutations as $m) {
                $qty     = bcadd((string) $m->qty, '0', 4);
                $balance = bcadd($balance, $qty, 4);

                $rows[] = [
                    'id'          => $m->id,
                    'date'        => $m->mutation_date,
                    'type'        => $m->mutation_type,
                    'document_no' => $docNos[$m->reference_type . ':' . $m->reference_id] ?? null,
                    'qty_in'      => bccomp($qty, '0', 4) > 0 ? $qty : null,
                    'qty_out'     => bccomp($qty, '0', 4) < 0 ? ltrim($qty, '-') : null,
                    'balance'     => $balance,
                    'unit_cost'   => $canViewCost ? $m->unit_cost : null,
                    'note'        => $m->note,
                ];
            }

            $closing = $balance;
        }

        return Inertia::render('Report/StockCard', [
            'filters'     => [
                'branch_id' => $branchId,
                'item_id'   => $itemId,
                'date_from' => $dateFrom,
                'date_to'   => $dateTo,
            ],
            'branches'    => $this->branchOptions($request),
            'items'       => Item::where('is_active', 1)->orderBy('name')->get(['id', 'code', 'name', 'unit']),
            'item'        => $item,
            'opening'     => $opening,
            'rows'        => $rows,
            'closing'     => $closing,
            'canViewCost' => $canViewCost,
        ]);
    }

    /**
     * Laporan Kas Shift. Semua user login boleh lihat, discope ke cabangnya
     * (pola sama dengan stock()) -- bukan hanya shift yang ia buka sendiri.
     * Tidak ada avg_cost/margin di sini, jadi tidak perlu Gate item.view_cost.
     */
    public function kasShift(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'status'    => ['nullable', 'in:open,closed'],
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $user     = $request->user();
        $isOwner  = $user->branch_id === null;
        $branchId = $isOwner ? ($data['branch_id'] ?? null) : (int) $user->branch_id;
        $status   = $data['status'] ?? null;
        $dateFrom = $data['date_from'] ?? null;
        $dateTo   = $data['date_to'] ?? null;

        $rows = DB::table('cashier_shifts as cs')
            ->join('branches as b', 'b.id', '=', 'cs.branch_id')
            ->join('users as u', 'u.id', '=', 'cs.user_id')
            ->leftJoin('users as cu', 'cu.id', '=', 'cs.closed_by')
            ->when($branchId, fn ($q) => $q->where('cs.branch_id', $branchId))
            ->when($status, fn ($q) => $q->where('cs.status', $status))
            ->when($dateFrom, fn ($q) => $q->whereDate('cs.opened_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('cs.opened_at', '<=', $dateTo))
            ->orderByDesc('cs.opened_at')
            ->select(
                'cs.id',
                'b.code as branch_code',
                'u.name as opened_by_name',
                'cu.name as closed_by_name',
                'cs.opening_balance',
                'cs.closing_balance',
                'cs.expected_balance',
                'cs.difference',
                'cs.status',
                'cs.opened_at',
                'cs.closed_at',
                'cs.note'
            )
            ->get();

        return Inertia::render('Report/KasShift', [
            'rows'     => $rows,
            'branches' => $this->branchOptions($request),
            'filters'  => [
                'branch_id' => $branchId,
                'status'    => $status,
                'date_from' => $dateFrom,
                'date_to'   => $dateTo,
            ],
            'isOwner'  => $isOwner,
        ]);
    }

    /**
     * Ubah reference_type/reference_id (alias morph map) menjadi nomor dokumen.
     * Satu query per tipe, bukan per baris.
     *
     * @return array<string,string> key "tipe:id" => nomor dokumen
     */

        /**
     * Laporan Penjualan Kredit. Akses dibatasi notes.manage (admin, owner) --
     * sama seperti menu Piutang, karena data ini sensitif (siapa berutang, berapa).
     * Saldo piutang per pelanggan pakai rumus ERD §5 no. 8: SUM(sale_payments credit,
     * sale completed) - SUM(notes.amount type=pembayaran).
     */
    public function salesCredit(Request $request)
    {
        \Illuminate\Support\Facades\Gate::authorize('notes.manage');

        $data = $request->validate([
            'branch_id'   => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'date_from'   => ['nullable', 'date'],
            'date_to'     => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $user       = $request->user();
        $isOwner    = $user->branch_id === null;
        $branchId   = $isOwner ? ($data['branch_id'] ?? null) : (int) $user->branch_id;
        $customerId = $data['customer_id'] ?? null;
        $dateFrom   = $data['date_from'] ?? null;
        $dateTo     = $data['date_to'] ?? null;

        // Daftar transaksi kredit (baris sale_payments method=credit, sale completed).
        $rows = DB::table('sale_payments as sp')
            ->join('sales_headers as sh', 'sh.id', '=', 'sp.sale_id')
            ->join('branches as b', 'b.id', '=', 'sh.branch_id')
            ->join('customers as c', 'c.id', '=', 'sh.customer_id')
            ->join('users as u', 'u.id', '=', 'sh.created_by')
            ->where('sp.method', 'credit')
            ->where('sh.status', 'completed')
            ->when($branchId, fn ($q) => $q->where('sh.branch_id', $branchId))
            ->when($customerId, fn ($q) => $q->where('sh.customer_id', $customerId))
            ->when($dateFrom, fn ($q) => $q->whereDate('sh.sale_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('sh.sale_date', '<=', $dateTo))
            ->orderByDesc('sh.sale_date')
            ->select(
                'sh.id as sale_id',
                'sh.invoice_no',
                'sh.sale_date',
                'b.code as branch_code',
                'c.id as customer_id',
                'c.name as customer_name',
                'u.name as created_by_name',
                'sp.amount'
            )
            ->get();

        // Ringkasan saldo piutang per pelanggan (rumus ERD §5 no. 8), discope cabang yang sama.
        $creditByCustomer = DB::table('sale_payments as sp')
            ->join('sales_headers as sh', 'sh.id', '=', 'sp.sale_id')
            ->where('sp.method', 'credit')
            ->where('sh.status', 'completed')
            ->when($branchId, fn ($q) => $q->where('sh.branch_id', $branchId))
            ->groupBy('sh.customer_id')
            ->select('sh.customer_id', DB::raw('SUM(sp.amount) as total_credit'))
            ->pluck('total_credit', 'customer_id');

        $paidByCustomer = DB::table('notes')
            ->where('type', 'pembayaran')
            ->where('noteable_type', 'customer')
            ->whereIn('noteable_id', $creditByCustomer->keys())
            ->groupBy('noteable_id')
            ->select('noteable_id', DB::raw('SUM(amount) as total_paid'))
            ->pluck('total_paid', 'noteable_id');

        $customerNames = DB::table('customers')
            ->whereIn('id', $creditByCustomer->keys())
            ->pluck('name', 'id');

        $balances = $creditByCustomer->map(function ($totalCredit, $customerId) use ($paidByCustomer, $customerNames) {
            $paid = (string) ($paidByCustomer[$customerId] ?? '0');

            return [
                'customer_id'   => $customerId,
                'customer_name' => $customerNames[$customerId] ?? '-',
                'total_credit'  => bcadd((string) $totalCredit, '0', 2),
                'total_paid'    => bcadd($paid, '0', 2),
                'balance'       => bcsub(bcadd((string) $totalCredit, '0', 2), bcadd($paid, '0', 2), 2),
            ];
        })->values();

        return Inertia::render('Report/SalesCredit', [
            'rows'      => $rows,
            'balances'  => $balances,
            'branches'  => $this->branchOptions($request),
            'customers' => DB::table('customers')->where('is_active', 1)->orderBy('name')->get(['id', 'name']),
            'filters'   => [
                'branch_id'   => $branchId,
                'customer_id' => $customerId,
                'date_from'   => $dateFrom,
                'date_to'     => $dateTo,
            ],
            'isOwner'   => $isOwner,
        ]);
    }

        /**
     * Laporan Kerugian Transfer (UC-RPT-05, ERD §5 no. 17). Kerugian dibebankan
     * ke cabang asal (from_branch_id): stok sudah keluar saat status 'sent',
     * dan resolusi 'written_off' tidak menambah mutasi apa pun untuk menutupnya.
     */
    public function writtenOff(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $user     = $request->user();
        $isOwner  = $user->branch_id === null;
        $branchId = $isOwner ? ($data['branch_id'] ?? null) : (int) $user->branch_id;
        $dateFrom = $data['date_from'] ?? null;
        $dateTo   = $data['date_to'] ?? null;

        $rows = DB::table('transfer_details as td')
            ->join('transfers as t', 't.id', '=', 'td.transfer_id')
            ->join('branches as fb', 'fb.id', '=', 't.from_branch_id')
            ->join('branches as tb', 'tb.id', '=', 't.to_branch_id')
            ->join('items as i', 'i.id', '=', 'td.item_id')
            ->where('td.difference_resolution', 'written_off')
            // Kerugian ditanggung cabang asal, jadi discope dari from_branch_id.
            ->when($branchId, fn ($q) => $q->where('t.from_branch_id', $branchId))
            ->when($dateFrom, fn ($q) => $q->whereDate('t.transfer_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('t.transfer_date', '<=', $dateTo))
            ->orderByDesc('t.transfer_date')
            ->select(
                't.id as transfer_id',
                't.transfer_no',
                't.transfer_date',
                'fb.code as from_branch_code',
                'tb.code as to_branch_code',
                'i.code as item_code',
                'i.name as item_name',
                'td.qty_sent',
                'td.qty_received',
                'td.unit_cost'
            )
            ->get()
            ->map(function ($row) {
                $lostQty = bcsub((string) $row->qty_sent, (string) $row->qty_received, 4);
                $row->lost_qty = $lostQty;
                $row->loss_amount = bcmul($lostQty, (string) $row->unit_cost, 2);

                return $row;
            });

        $totalLoss = $rows->reduce(fn ($carry, $row) => bcadd($carry, $row->loss_amount, 2), '0');

        return Inertia::render('Report/WrittenOff', [
            'rows'      => $rows,
            'totalLoss' => $totalLoss,
            'branches'  => $this->branchOptions($request),
            'filters'   => [
                'branch_id' => $branchId,
                'date_from' => $dateFrom,
                'date_to'   => $dateTo,
            ],
            'isOwner'   => $isOwner,
        ]);
    }
    private function resolveDocumentNumbers($mutations): array
    {
        $map = [
            'sale'             => ['sales_headers', 'invoice_no'],
            'transfer'         => ['transfers', 'transfer_no'],
            'stock_adjustment' => ['stock_adjustments', 'adjustment_no'],
        ];

        $result = [];

        foreach ($map as $type => [$table, $column]) {
            $ids = $mutations->where('reference_type', $type)->pluck('reference_id')->unique()->values();

            if ($ids->isEmpty()) {
                continue;
            }

            foreach (DB::table($table)->whereIn('id', $ids)->pluck($column, 'id') as $id => $no) {
                $result[$type . ':' . $id] = $no;
            }
        }

        return $result;
    }
}