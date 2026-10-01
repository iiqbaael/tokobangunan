<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * UC-RPT-06: Laporan Konsolidasi Semua Cabang. Khusus Owner (branch_id NULL) --
 * tidak ada ability terpisah di ERD §6 untuk ini, jadi dibatasi langsung dari role.
 *
 * Piutang dihitung per pelanggan, bukan per cabang (BR-12/ERD §5 no. 8), sehingga
 * tidak punya breakdown cabang yang bermakna -- ditampilkan sebagai satu angka
 * total perusahaan. Omzet, laba kotor, dan nilai stok dipecah per cabang.
 */
class ConsolidationReportController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless($request->user()->branch_id === null, 403);

        $data = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $dateFrom = $data['date_from'] ?? null;
        $dateTo   = $data['date_to'] ?? null;

        // Omzet per cabang (nota completed dalam rentang tanggal).
        $omzetByBranch = DB::table('sales_headers as sh')
            ->join('branches as b', 'b.id', '=', 'sh.branch_id')
            ->where('sh.status', 'completed')
            ->when($dateFrom, fn ($q) => $q->where('sh.sale_date', '>=', $dateFrom . ' 00:00:00'))
            ->when($dateTo, fn ($q) => $q->where('sh.sale_date', '<=', $dateTo . ' 23:59:59'))
            ->groupBy('b.id', 'b.code', 'b.name')
            ->select('b.id', 'b.code', 'b.name', DB::raw('SUM(sh.total) as omzet'))
            ->get()
            ->keyBy('id');

        // Laba kotor per cabang, rumus sama dengan GrossProfitController.
        $labaByBranch = DB::table('sales_details as sd')
            ->join('sales_headers as sh', 'sh.id', '=', 'sd.sale_id')
            ->join('branches as b', 'b.id', '=', 'sh.branch_id')
            ->where('sh.status', 'completed')
            ->when($dateFrom, fn ($q) => $q->where('sh.sale_date', '>=', $dateFrom . ' 00:00:00'))
            ->when($dateTo, fn ($q) => $q->where('sh.sale_date', '<=', $dateTo . ' 23:59:59'))
            ->groupBy('b.id')
            ->select('b.id', DB::raw('SUM(sd.subtotal) as revenue'), DB::raw('SUM(sd.cost_at_sale * sd.qty) as cogs'))
            ->get()
            ->keyBy('id');

        // Nilai stok per cabang -- posisi saat ini, tidak terikat rentang tanggal.
        $stokByBranch = DB::table('stock_balances as sbal')
            ->join('branches as b', 'b.id', '=', 'sbal.branch_id')
            ->groupBy('b.id')
            ->select('b.id', DB::raw('SUM(sbal.qty * sbal.avg_cost) as stock_value'))
            ->get()
            ->keyBy('id');

        $branches = DB::table('branches')->where('is_active', 1)->orderBy('id')->get(['id', 'code', 'name']);

        $perBranch = $branches->map(function ($b) use ($omzetByBranch, $labaByBranch, $stokByBranch) {
            $omzet = (string) ($omzetByBranch[$b->id]->omzet ?? '0');
            $revenue = (string) ($labaByBranch[$b->id]->revenue ?? '0');
            $cogs = (string) ($labaByBranch[$b->id]->cogs ?? '0');
            $profit = bcsub($revenue, $cogs, 2);

            return [
                'branch_id'   => $b->id,
                'branch_code' => $b->code,
                'branch_name' => $b->name,
                'omzet'       => bcadd($omzet, '0', 2),
                'profit'      => $profit,
                'stock_value' => bcadd((string) ($stokByBranch[$b->id]->stock_value ?? '0'), '0', 2),
            ];
        });

        $totals = [
            'omzet'       => $perBranch->reduce(fn ($c, $r) => bcadd($c, $r['omzet'], 2), '0'),
            'profit'      => $perBranch->reduce(fn ($c, $r) => bcadd($c, $r['profit'], 2), '0'),
            'stock_value' => $perBranch->reduce(fn ($c, $r) => bcadd($c, $r['stock_value'], 2), '0'),
        ];

        // Piutang total perusahaan (BR-12): SUM(sale_payments credit) - SUM(notes pembayaran).
        $totalCredit = (string) (DB::table('sale_payments as sp')
            ->join('sales_headers as sh', 'sh.id', '=', 'sp.sale_id')
            ->where('sp.method', 'credit')
            ->where('sh.status', 'completed')
            ->sum('sp.amount') ?: '0');

        $totalPaid = (string) (DB::table('notes')
            ->where('type', 'pembayaran')
            ->where('noteable_type', 'customer')
            ->sum('amount') ?: '0');

        $totalReceivable = bcsub(bcadd($totalCredit, '0', 2), bcadd($totalPaid, '0', 2), 2);

        return Inertia::render('Report/Consolidation', [
            'perBranch'       => $perBranch,
            'totals'          => $totals,
            'totalReceivable' => $totalReceivable,
            'filters'         => [
                'date_from' => $dateFrom,
                'date_to'   => $dateTo,
            ],
        ]);
    }
}