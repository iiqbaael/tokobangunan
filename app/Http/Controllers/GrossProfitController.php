<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class GrossProfitController extends Controller
{
    public function __invoke(Request $request)
    {
        Gate::authorize('report.view_margin');

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

        $rows = DB::table('sales_details as sd')
            ->join('sales_headers as sh', 'sh.id', '=', 'sd.sale_id')
            ->join('items as i', 'i.id', '=', 'sd.item_id')
            ->join('branches as b', 'b.id', '=', 'sh.branch_id')
            ->where('sh.status', 'completed')
            ->when($branchId, fn ($q) => $q->where('sh.branch_id', $branchId))
            ->when($dateFrom, fn ($q) => $q->where('sh.sale_date', '>=', $dateFrom . ' 00:00:00'))
            ->when($dateTo, fn ($q) => $q->where('sh.sale_date', '<=', $dateTo . ' 23:59:59'))
            ->groupBy('b.code', 'i.id', 'i.code', 'i.name', 'i.unit')
            ->orderBy('b.code')
            ->orderBy('i.name')
            ->selectRaw('b.code as branch_code, i.code as item_code, i.name as item_name, i.unit,
                SUM(sd.qty) as qty_sold,
                SUM(sd.subtotal) as revenue,
                SUM(sd.cost_at_sale * sd.qty) as cogs')
            ->get()
            ->map(function ($r) {
                $r->profit = bcsub((string) $r->revenue, (string) $r->cogs, 2);
                $r->margin = bccomp((string) $r->revenue, '0', 2) > 0
                    ? bcmul(bcdiv($r->profit, (string) $r->revenue, 6), '100', 2)
                    : '0.00';

                return $r;
            });

        $revenue = '0';
        $cogs    = '0';
        foreach ($rows as $r) {
            $revenue = bcadd($revenue, (string) $r->revenue, 2);
            $cogs    = bcadd($cogs, (string) $r->cogs, 2);
        }
        $profit = bcsub($revenue, $cogs, 2);

        return Inertia::render('Report/GrossProfit', [
            'rows'     => $rows,
            'totals'   => [
                'revenue' => $revenue,
                'cogs'    => $cogs,
                'profit'  => $profit,
                'margin'  => bccomp($revenue, '0', 2) > 0
                    ? bcmul(bcdiv($profit, $revenue, 6), '100', 2)
                    : '0.00',
            ],
            'branches' => $isOwner
                ? Branch::where('is_active', 1)->orderBy('id')->get(['id', 'code', 'name'])
                : [],
            'filters'  => [
                'branch_id' => $branchId,
                'date_from' => $dateFrom,
                'date_to'   => $dateTo,
            ],
            'isOwner'  => $isOwner,
        ]);
    }
}