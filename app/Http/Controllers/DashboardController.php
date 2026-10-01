<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Dashboard ringkasan. Admin/Kasir dikunci ke branch_id mereka. Owner (branch_id
 * NULL) melihat konsolidasi kedua cabang plus status shift tiap cabang.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user     = $request->user();
        $isOwner  = $user->branch_id === null;
        $branchId = $isOwner ? null : (int) $user->branch_id;
        $now      = now();
        $today    = $now->toDateString();

        $todaySales = DB::table('sales_headers')
            ->where('status', 'completed')
            ->whereDate('sale_date', $today)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total')
            ->first();

        $dailySales = DB::table('sales_headers')
            ->where('status', 'completed')
            ->whereDate('sale_date', '>=', $now->copy()->subDays(6)->toDateString())
            ->whereDate('sale_date', '<=', $today)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('DATE(sale_date) as sale_day, COUNT(*) as count, COALESCE(SUM(total), 0) as total')
            ->groupByRaw('DATE(sale_date)')
            ->get()
            ->keyBy('sale_day');

        $salesTrend = collect(range(6, 0))->map(function ($daysAgo) use ($now, $dailySales) {
            $date = $now->copy()->subDays($daysAgo)->toDateString();
            $sales = $dailySales->get($date);

            return [
                'date' => $date,
                'count' => (int) ($sales->count ?? 0),
                'total' => bcadd((string) ($sales->total ?? 0), '0', 2),
            ];
        })->values();

        $lowStockCount = DB::table('stock_balances')
            ->where('min_stock', '>', 0)
            ->whereColumn('qty', '<=', 'min_stock')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->count();

        $shiftStatus = $isOwner
            ? DB::table('cashier_shifts as cs')
                ->join('branches as b', 'b.id', '=', 'cs.branch_id')
                ->where('cs.status', 'open')
                ->select('b.id as branch_id', 'b.code as branch_code', 'b.name as branch_name', 'cs.opened_at')
                ->get()
            : DB::table('cashier_shifts')
                ->where('branch_id', $branchId)
                ->where('status', 'open')
                ->first();

        $pendingAdjustments = null;
        $pendingTransfers   = null;
        if ($user->role !== 'cashier') {
            $pendingAdjustments = DB::table('stock_adjustments')
                ->where('status', 'pending')
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->count();

            $pendingTransfers = DB::table('transfers')
                ->whereIn('status', ['sent', 'partial'])
                ->when($branchId, fn ($q) => $q->where(fn ($qq) => $qq
                    ->where('from_branch_id', $branchId)
                    ->orWhere('to_branch_id', $branchId)))
                ->count();
        }

        $totalReceivable = null;
        if (Gate::allows('notes.manage')) {
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
        }

        $activeBranch = null;
        if ($isOwner) {
            $activeBranchId = $request->session()->get('active_branch_id');
            if ($activeBranchId) {
                $activeBranch = DB::table('branches')->where('id', $activeBranchId)->first(['code', 'name']);
            }
        }

        return Inertia::render('Dashboard', [
            'isOwner'             => $isOwner,
            'branchName'          => $isOwner ? null : DB::table('branches')->where('id', $branchId)->value('name'),
            'activeBranch'        => $activeBranch,
            'todaySales'          => [
                'count' => (int) $todaySales->count,
                'total' => bcadd((string) $todaySales->total, '0', 2),
            ],
            'salesTrend'          => $salesTrend,
            'lowStockCount'       => $lowStockCount,
            'shiftStatus'         => $shiftStatus,
            'pendingAdjustments'  => $pendingAdjustments,
            'pendingTransfers'    => $pendingTransfers,
            'totalReceivable'     => $totalReceivable,
        ]);
    }
}