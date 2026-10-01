<?php

namespace Database\Seeders;

use App\Enums\CashMovementType;
use App\Enums\NoteType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\CashMovement;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\Note;
use App\Models\SalesHeader;
use App\Models\StockAdjustment;
use App\Models\Transfer;
use App\Models\User;
use App\Services\CashierShiftService;
use App\Services\CashMovementService;
use App\Services\ReceivableService;
use App\Services\SaleService;
use App\Services\StockAdjustmentService;
use App\Services\TransferService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DemoStoreSeeder extends Seeder
{
    public function run(): void
    {
        $branches = $this->seedBranches();
        $users = $this->seedUsers($branches);
        $items = $this->seedItems();
        $customers = $this->seedCustomers();

        $this->seedOpeningStock($branches, $users, $items);
        $this->seedRestocks($branches, $users, $items);
        $this->seedPendingAdjustment($branches['CBU'], $users['admins']['CBU'], $items);
        $this->seedTransfers($branches, $users['owner'], $items);
        $this->seedSalesHistory($branches, $users, $items, $customers);
        $openShifts = $this->ensureOpenShifts($branches, $users['cashiers']);
        $this->seedCurrentSales($branches, $users, $items, $customers, $openShifts);
        $this->seedCurrentCashMovements($branches, $users['cashiers'], $openShifts);
        $this->seedReceivables($users['admins']['CBU'], $customers);
        $this->seedFollowUpNote($users['admins']['CBU'], array_values($customers)[0]);

        $this->command?->info('Dataset demo TB Sumber Baru selesai. Login demo: demo.owner@tbsumberbaru.test / password.');
    }

    private function seedBranches(): array
    {
        return [
            'CBU' => Branch::firstOrCreate(
                ['code' => 'CBU'],
                ['name' => 'Cabang Utama', 'is_main' => true, 'is_active' => true],
            ),
            'CB2' => Branch::firstOrCreate(
                ['code' => 'CB2'],
                ['name' => 'Cabang Cibubur', 'is_main' => false, 'is_active' => true],
            ),
        ];
    }

    private function seedUsers(array $branches): array
    {
        $owner = $this->firstOrCreateUser('demo.owner@tbsumberbaru.test', 'Pemilik Demo TB Sumber Baru', null, UserRole::Owner);
        $admins = [
            'CBU' => $this->firstOrCreateUser('admin@toko.test', 'Admin Cabang Utama', $branches['CBU']->id, UserRole::Admin),
            'CB2' => $this->firstOrCreateUser('admin.cb2@tbsumberbaru.test', 'Admin Cabang Cibubur', $branches['CB2']->id, UserRole::Admin),
        ];
        $cashiers = [
            'CBU' => $this->firstOrCreateUser('kasir@toko.test', 'Kasir Cabang Utama', $branches['CBU']->id, UserRole::Cashier),
            'CB2' => $this->firstOrCreateUser('kasir.cb2@tbsumberbaru.test', 'Kasir Cabang Cibubur', $branches['CB2']->id, UserRole::Cashier),
        ];

        return compact('owner', 'admins', 'cashiers');
    }

    private function firstOrCreateUser(string $email, string $name, ?int $branchId, UserRole $role): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'branch_id' => $branchId,
                'password' => 'password',
                'role' => $role,
                'is_active' => true,
            ],
        );
    }

    private function seedItems(): array
    {
        $items = [];

        foreach ($this->catalog() as $product) {
            $category = Category::firstOrCreate(
                ['name' => $product['category']],
                ['is_active' => true],
            );

            $item = Item::withTrashed()->firstOrCreate(
                ['code' => $product['code']],
                [
                    'category_id' => $category->id,
                    'barcode' => null,
                    'name' => $product['name'],
                    'brand' => $product['brand'],
                    'specification' => $product['specification'],
                    'unit' => $product['unit'],
                    'sell_price' => $product['price'],
                    'is_active' => true,
                ],
            );

            if ($item->trashed()) {
                $item->restore();
            }

            if ($product['alternative'] !== null) {
                ItemUnit::firstOrCreate(
                    ['item_id' => $item->id, 'unit' => $product['alternative']['unit']],
                    [
                        'conversion_qty' => $product['alternative']['conversion_qty'],
                        'barcode' => null,
                        'sell_price' => $product['alternative']['price'],
                    ],
                );
            }

            $items[$product['code']] = ['model' => $item, ...$product];
        }

        return $items;
    }

    private function seedCustomers(): array
    {
        $records = [
            ['name' => 'Bengkel Las Sinar Baja', 'phone' => '081290400001', 'address' => 'Cibinong, Bogor'],
            ['name' => 'CV Karya Mandiri Konstruksi', 'phone' => '081290400002', 'address' => 'Cileungsi, Bogor'],
            ['name' => 'Pak Dedi - Renovasi Rumah', 'phone' => '081290400003', 'address' => 'Gunung Putri, Bogor'],
            ['name' => 'Bu Rina - Toko Material', 'phone' => '081290400004', 'address' => 'Cibubur, Jakarta Timur'],
            ['name' => 'PT Griya Tumbuh Properti', 'phone' => '081290400005', 'address' => 'Kota Wisata, Bogor'],
            ['name' => 'Bapak Hendra', 'phone' => '081290400006', 'address' => 'Citeureup, Bogor'],
            ['name' => 'UD Sumber Rezeki', 'phone' => '081290400007', 'address' => 'Jonggol, Bogor'],
            ['name' => 'Ibu Maya - Renovasi Dapur', 'phone' => '081290400008', 'address' => 'Harjamukti, Depok'],
            ['name' => 'CV Pilar Konstruksi', 'phone' => '081290400009', 'address' => 'Tapos, Depok'],
            ['name' => 'Pak Arif - Pemborong', 'phone' => '081290400010', 'address' => 'Sukamakmur, Bogor'],
            ['name' => 'PT Cipta Ruang Indonesia', 'phone' => '081290400011', 'address' => 'Cimanggis, Depok'],
            ['name' => 'Bapak Tono', 'phone' => '081290400012', 'address' => 'Klapanunggal, Bogor'],
        ];

        return collect($records)->mapWithKeys(function (array $record) {
            $customer = Customer::withTrashed()->firstOrCreate(
                ['phone' => $record['phone']],
                $record + ['is_active' => true],
            );

            if ($customer->trashed()) {
                $customer->restore();
            }

            return [$record['phone'] => $customer];
        })->all();
    }

    private function seedOpeningStock(array $branches, array $users, array $items): void
    {
        $service = app(StockAdjustmentService::class);

        foreach (['CBU' => 'stock_main', 'CB2' => 'stock_second'] as $branchCode => $stockKey) {
            $reference = "DEMO-INITIAL-{$branchCode}";

            if (StockAdjustment::where('reference_no', $reference)->exists()) {
                continue;
            }

            $lines = [];
            foreach ($items as $product) {
                $lines[] = [
                    'item_id' => $product['model']->id,
                    'qty' => (string) $product[$stockKey],
                    'unit_cost' => (string) $product['cost'],
                    'note' => 'Stok awal dataset demo',
                ];
            }

            $at = now()->subDays(30)->setTime(8, 0);
            Carbon::setTestNow($at);
            try {
                $service->create([
                    'branch_id' => $branches[$branchCode]->id,
                    'adjustment_date' => $at->toDateString(),
                    'adjustment_type' => 'in',
                    'reason' => 'initial_stock',
                    'reference_no' => $reference,
                    'description' => '[DEMO] Stok awal katalog sebelum operasional berjalan.',
                    'items' => $lines,
                ], $users['admins'][$branchCode]);
            } finally {
                Carbon::setTestNow();
            }
        }
    }

    private function seedRestocks(array $branches, array $users, array $items): void
    {
        $service = app(StockAdjustmentService::class);
        $restockCodes = ['DEMO-SEM-001', 'DEMO-BESI-010', 'DEMO-PVC-001', 'DEMO-CAT-001'];

        foreach (['CBU', 'CB2'] as $branchCode) {
            $reference = "DEMO-RESTOCK-{$branchCode}";
            if (StockAdjustment::where('reference_no', $reference)->exists()) {
                continue;
            }

            $lines = [];
            foreach ($restockCodes as $code) {
                $product = $items[$code];
                $lines[] = [
                    'item_id' => $product['model']->id,
                    'qty' => $branchCode === 'CBU' ? '24' : '12',
                    'unit_cost' => (string) $product['cost'],
                    'note' => 'Restock berkala demo',
                ];
            }

            $at = now()->subDays(6)->setTime(10, 0);
            Carbon::setTestNow($at);
            try {
                $service->create([
                    'branch_id' => $branches[$branchCode]->id,
                    'adjustment_date' => $at->toDateString(),
                    'adjustment_type' => 'in',
                    'reason' => 'restock',
                    'reference_no' => $reference,
                    'description' => '[DEMO] Restock semen, besi, pipa, dan cat dari distributor.',
                    'items' => $lines,
                ], $users['admins'][$branchCode]);
            } finally {
                Carbon::setTestNow();
            }
        }
    }

    private function seedPendingAdjustment(Branch $branch, User $admin, array $items): void
    {
        $reference = 'DEMO-DAMAGED-CBU';
        if (StockAdjustment::where('reference_no', $reference)->exists()) {
            return;
        }

        app(StockAdjustmentService::class)->create([
            'branch_id' => $branch->id,
            'adjustment_date' => now()->subDay()->toDateString(),
            'adjustment_type' => 'out',
            'reason' => 'damaged',
            'reference_no' => $reference,
            'description' => '[DEMO] Pemeriksaan barang pecah saat bongkar muat, menunggu approval.',
            'items' => [[
                'item_id' => $items['DEMO-KER-001']['model']->id,
                'qty' => '2',
                'note' => 'Dua dus keramik pecah di sudut kemasan.',
            ]],
        ], $admin);
    }

    private function seedTransfers(array $branches, User $owner, array $items): void
    {
        $service = app(TransferService::class);
        $source = $branches['CBU'];
        $destination = $branches['CB2'];

        $this->createTransferIfMissing(
            $service,
            $source,
            $destination,
            $owner,
            $items,
            'DEMO-TRANSFER-RECEIVED',
            [
                ['code' => 'DEMO-SEM-001', 'qty' => '8'],
                ['code' => 'DEMO-PVC-001', 'qty' => '12'],
            ],
            true,
        );

        $this->createTransferIfMissing(
            $service,
            $source,
            $destination,
            $owner,
            $items,
            'DEMO-TRANSFER-IN-TRANSIT',
            [
                ['code' => 'DEMO-BESI-010', 'qty' => '18'],
                ['code' => 'DEMO-HOLLOW-001', 'qty' => '10'],
            ],
            false,
        );
    }

    private function createTransferIfMissing(
        TransferService $service,
        Branch $source,
        Branch $destination,
        User $owner,
        array $items,
        string $marker,
        array $lines,
        bool $receive,
    ): void {
        if (Transfer::where('note', 'like', $marker.'%')->exists()) {
            return;
        }

        $transfer = $service->create([
            'from_branch_id' => $source->id,
            'to_branch_id' => $destination->id,
            'transfer_date' => now()->subDays(2)->toDateString(),
            'note' => $marker.' - pemerataan stok antarcabang.',
            'items' => collect($lines)->map(fn (array $line) => [
                'item_id' => $items[$line['code']]['model']->id,
                'qty_sent' => $line['qty'],
                'note' => 'Pemerataan stok demo',
            ])->all(),
        ], $owner);

        $transfer = $service->send($transfer, $owner);

        if ($receive) {
            $received = $transfer->details
                ->mapWithKeys(fn ($detail) => [$detail->id => (string) $detail->qty_sent])
                ->all();
            $service->receive($transfer, $received, $owner);
        }
    }

    private function seedSalesHistory(array $branches, array $users, array $items, array $customers): void
    {
        $shiftService = app(CashierShiftService::class);
        $cashMovements = app(CashMovementService::class);
        $sales = app(SaleService::class);
        $customerList = array_values($customers);
        $baskets = $this->baskets();
        $methods = ['cash', 'transfer', 'ewallet', 'card', 'credit'];

        for ($daysAgo = 13; $daysAgo >= 1; $daysAgo--) {
            $date = now()->startOfDay()->subDays($daysAgo);

            foreach (['CBU', 'CB2'] as $branchIndex => $branchCode) {
                $branch = $branches[$branchCode];
                $cashier = $users['cashiers'][$branchCode];
                $admin = $users['admins'][$branchCode];
                $shiftMarker = 'DEMO-SHIFT-'.$branchCode.'-'.$date->format('Ymd');

                if (CashierShift::where('branch_id', $branch->id)->where('status', 'open')->exists()) {
                    continue;
                }

                if (CashierShift::where('note', 'like', $shiftMarker.'%')->exists()) {
                    continue;
                }

                try {
                    DB::transaction(function () use ($date, $branch, $branchCode, $branchIndex, $cashier, $admin, $customerList, $baskets, $methods, $shiftService, $cashMovements, $sales, $items, $daysAgo, $shiftMarker) {
                        Carbon::setTestNow($date->copy()->setTime(7, 30));
                        $opening = '250000';
                        $shift = $shiftService->open($branch->id, $cashier, $opening, $shiftMarker);
                        $cashSales = '0.00';

                        for ($saleIndex = 0; $saleIndex < 2; $saleIndex++) {
                            Carbon::setTestNow($date->copy()->setTime(9 + ($saleIndex * 3), 15));
                            $method = $methods[($daysAgo + $branchIndex + $saleIndex) % count($methods)];
                            $customer = $customerList[($daysAgo + $branchIndex + $saleIndex) % count($customerList)];
                            $marker = 'DEMO-SALE-'.$branchCode.'-'.$date->format('Ymd').'-'.($saleIndex + 1);
                            $basket = $baskets[($daysAgo + $branchIndex + $saleIndex) % count($baskets)];
                            $actor = $method === 'credit' ? $admin : $cashier;
                            $sale = $this->createSale($sales, $branch, $actor, $items, $basket, $customer, $method, $marker);

                            if ($method === 'cash') {
                                $cashSales = bcadd($cashSales, (string) $sale->total, 2);
                            }
                        }

                        $cashIn = '0.00';
                        $cashOut = '0.00';
                        if ($daysAgo % 3 === 0) {
                            Carbon::setTestNow($date->copy()->setTime(16, 0));
                            $cashIn = '50000.00';
                            $cashOut = '25000.00';
                            $cashMovements->record($branch->id, $cashier, CashMovementType::In, $cashIn, 'setor_brankas', '[DEMO] Setoran kas penjualan harian.');
                            $cashMovements->record($branch->id, $cashier, CashMovementType::Out, $cashOut, 'operasional_toko', '[DEMO] Pembelian kebutuhan operasional.');
                        }

                        $closing = bcadd($opening, $cashSales, 2);
                        $closing = bcadd($closing, $cashIn, 2);
                        $closing = bcsub($closing, $cashOut, 2);
                        Carbon::setTestNow($date->copy()->setTime(18, 0));
                        $shiftService->close($shift, $cashier, $closing, '[DEMO] Rekonsiliasi kas sesuai transaksi.');
                    });
                } finally {
                    Carbon::setTestNow();
                }
            }
        }
    }

    private function ensureOpenShifts(array $branches, array $cashiers): array
    {
        $shifts = [];
        $service = app(CashierShiftService::class);

        foreach (['CBU', 'CB2'] as $branchCode) {
            $branch = $branches[$branchCode];
            $shift = CashierShift::where('branch_id', $branch->id)->where('status', 'open')->first();

            if ($shift === null) {
                $shift = $service->open($branch->id, $cashiers[$branchCode], '250000', '[DEMO] Shift kasir aktif untuk demonstrasi POS.');
            }

            $shifts[$branchCode] = $shift;
        }

        return $shifts;
    }

    private function seedCurrentSales(array $branches, array $users, array $items, array $customers, array $shifts): void
    {
        $sales = app(SaleService::class);
        $baskets = $this->baskets();
        $customerList = array_values($customers);

        foreach (['CBU', 'CB2'] as $branchIndex => $branchCode) {
            $shift = $shifts[$branchCode];
            if (! str_starts_with((string) $shift->note, '[DEMO]')) {
                continue;
            }

            foreach (['cash', 'transfer'] as $saleIndex => $method) {
                $marker = 'DEMO-TODAY-'.$branchCode.'-'.($saleIndex + 1);
                if (SalesHeader::where('note', $marker)->exists()) {
                    continue;
                }

                $this->createSale(
                    $sales,
                    $branches[$branchCode],
                    $users['cashiers'][$branchCode],
                    $items,
                    $baskets[($branchIndex + $saleIndex + 1) % count($baskets)],
                    $customerList[($branchIndex + $saleIndex) % count($customerList)],
                    $method,
                    $marker,
                );
            }
        }
    }

    private function createSale(
        SaleService $service,
        Branch $branch,
        User $actor,
        array $items,
        array $basket,
        Customer $customer,
        string $method,
        string $marker,
    ): SalesHeader {
        $lines = [];
        $total = '0.00';

        foreach ($basket as [$code, $quantity]) {
            $item = $items[$code]['model'];
            $lines[] = ['item_id' => $item->id, 'unit' => $item->unit, 'qty_input' => (string) $quantity];
            $lineTotal = bcmul((string) $item->sell_price, (string) $quantity, 2);
            $total = bcadd($total, $lineTotal, 2);
        }

        $payment = ['method' => $method, 'amount' => $total];
        if (! in_array($method, ['cash', 'credit'], true)) {
            $payment['reference_no'] = 'DEMO-REF-'.substr(md5($marker), 0, 12);
        }

        return $service->create($branch->id, [
            'customer_id' => $method === 'credit' ? $customer->id : null,
            'note' => $marker,
            'cash_received' => $method === 'cash' ? bcadd($total, '10000.00', 2) : null,
            'items' => $lines,
            'payments' => [$payment],
        ], $actor);
    }

    private function seedCurrentCashMovements(array $branches, array $cashiers, array $shifts): void
    {
        $service = app(CashMovementService::class);

        foreach (['CBU', 'CB2'] as $branchCode) {
            $shift = $shifts[$branchCode];
            if (! str_starts_with((string) $shift->note, '[DEMO]')) {
                continue;
            }

            foreach ([
                ['in', 150000, 'setor_brankas', 'DEMO-CASH-IN-'.$branchCode],
                ['out', 35000, 'operasional_toko', 'DEMO-CASH-OUT-'.$branchCode],
            ] as [$type, $amount, $reason, $marker]) {
                if (CashMovement::where('cashier_shift_id', $shift->id)->where('note', $marker)->exists()) {
                    continue;
                }

                $service->record(
                    $branches[$branchCode]->id,
                    $cashiers[$branchCode],
                    CashMovementType::from($type),
                    $amount,
                    $reason,
                    $marker,
                );
            }
        }
    }

    private function seedReceivables(User $admin, array $customers): void
    {
        $service = app(ReceivableService::class);

        foreach ($customers as $phone => $customer) {
            $balance = $service->balance($customer);
            $marker = 'DEMO-REPAYMENT-'.$phone;

            if (bccomp($balance, '0.00', 2) > 0 && ! Note::where('body', 'like', $marker.'%')->exists()) {
                $amount = bccomp($balance, '250000.00', 2) > 0 ? '250000.00' : $balance;
                $service->pay($customer, $admin, $amount, false, null, $marker.' - pembayaran bertahap via transfer.');
            }
        }
    }

    private function seedFollowUpNote(User $admin, Customer $customer): void
    {
        $marker = 'DEMO-FOLLOWUP-'.$customer->phone;
        if (Note::where('body', 'like', $marker.'%')->exists()) {
            return;
        }

        Note::create([
            'noteable_type' => $customer->getMorphClass(),
            'noteable_id' => $customer->id,
            'type' => NoteType::General,
            'body' => $marker.' - konfirmasi jadwal pengiriman material proyek.',
            'follow_up_date' => now()->addDays(3)->toDateString(),
            'created_by' => $admin->id,
        ]);
    }

    private function baskets(): array
    {
        return [
            [['DEMO-SEM-001', 3], ['DEMO-BESI-010', 4], ['DEMO-PVC-001', 2]],
            [['DEMO-PASIR-001', 2], ['DEMO-BATA-001', 160], ['DEMO-MORTAR-001', 3]],
            [['DEMO-CAT-001', 2], ['DEMO-KER-001', 4], ['DEMO-EL-001', 6]],
            [['DEMO-HOLLOW-001', 12], ['DEMO-GYPSUM-001', 8], ['DEMO-KABEL-001', 15]],
            [['DEMO-TOOLS-001', 1], ['DEMO-PIPA-002', 3], ['DEMO-SEAL-001', 4]],
            [['DEMO-SPLIT-001', 2], ['DEMO-BATA-002', 36], ['DEMO-WATER-001', 2]],
        ];
    }

    private function catalog(): array
    {
        return [
            ['code' => 'DEMO-SEM-001', 'category' => 'Semen & Mortar', 'name' => 'Semen PCC 50 kg', 'brand' => 'NusaBuild', 'specification' => 'Portland composite, zak 50 kg', 'unit' => 'sak', 'price' => 75500, 'cost' => 69000, 'stock_main' => 220, 'stock_second' => 130, 'alternative' => ['unit' => 'palet', 'conversion_qty' => '40', 'price' => null]],
            ['code' => 'DEMO-SEM-002', 'category' => 'Semen & Mortar', 'name' => 'Semen putih 40 kg', 'brand' => 'NusaBuild', 'specification' => 'Semen putih serbaguna, zak 40 kg', 'unit' => 'sak', 'price' => 92500, 'cost' => 84000, 'stock_main' => 55, 'stock_second' => 32, 'alternative' => null],
            ['code' => 'DEMO-MORTAR-001', 'category' => 'Semen & Mortar', 'name' => 'Mortar perekat bata ringan 40 kg', 'brand' => 'RekatPro', 'specification' => 'Thin bed adhesive, zak 40 kg', 'unit' => 'sak', 'price' => 68500, 'cost' => 59000, 'stock_main' => 95, 'stock_second' => 48, 'alternative' => ['unit' => 'palet', 'conversion_qty' => '40', 'price' => null]],
            ['code' => 'DEMO-MORTAR-002', 'category' => 'Semen & Mortar', 'name' => 'Mortar acian putih 40 kg', 'brand' => 'RekatPro', 'specification' => 'Acian instan interior-eksterior', 'unit' => 'sak', 'price' => 71500, 'cost' => 62000, 'stock_main' => 72, 'stock_second' => 40, 'alternative' => null],
            ['code' => 'DEMO-MORTAR-003', 'category' => 'Semen & Mortar', 'name' => 'Perekat keramik 25 kg', 'brand' => 'RekatPro', 'specification' => 'Tile adhesive, area basah/kering', 'unit' => 'sak', 'price' => 62500, 'cost' => 53000, 'stock_main' => 68, 'stock_second' => 36, 'alternative' => null],
            ['code' => 'DEMO-BESI-008', 'category' => 'Besi & Baja', 'name' => 'Besi beton polos 8 mm x 12 m', 'brand' => 'Baja Prima', 'specification' => 'Batang panjang 12 meter', 'unit' => 'batang', 'price' => 46500, 'cost' => 38500, 'stock_main' => 180, 'stock_second' => 100, 'alternative' => null],
            ['code' => 'DEMO-BESI-010', 'category' => 'Besi & Baja', 'name' => 'Besi beton ulir SNI 10 mm x 12 m', 'brand' => 'Baja Prima', 'specification' => 'Tulangan ulir, panjang 12 meter', 'unit' => 'batang', 'price' => 73500, 'cost' => 63500, 'stock_main' => 170, 'stock_second' => 90, 'alternative' => ['unit' => 'ikat', 'conversion_qty' => '100', 'price' => null]],
            ['code' => 'DEMO-BESI-012', 'category' => 'Besi & Baja', 'name' => 'Besi beton ulir SNI 12 mm x 12 m', 'brand' => 'Baja Prima', 'specification' => 'Tulangan ulir, panjang 12 meter', 'unit' => 'batang', 'price' => 104000, 'cost' => 91000, 'stock_main' => 120, 'stock_second' => 66, 'alternative' => null],
            ['code' => 'DEMO-WIRE-001', 'category' => 'Besi & Baja', 'name' => 'Wiremesh M6 2,1 x 5,4 m', 'brand' => 'Baja Prima', 'specification' => 'Lembaran untuk dak/lantai kerja', 'unit' => 'lembar', 'price' => 485000, 'cost' => 420000, 'stock_main' => 28, 'stock_second' => 16, 'alternative' => null],
            ['code' => 'DEMO-HOLLOW-001', 'category' => 'Besi & Baja', 'name' => 'Hollow galvanis 4 x 4 cm x 6 m', 'brand' => 'RangkaKuat', 'specification' => 'Tebal 0,30 mm, batang 6 meter', 'unit' => 'batang', 'price' => 82500, 'cost' => 69500, 'stock_main' => 130, 'stock_second' => 72, 'alternative' => null],
            ['code' => 'DEMO-PASIR-001', 'category' => 'Pasir, Batu & Bata', 'name' => 'Pasir beton ayak', 'brand' => 'Material Lokal', 'specification' => 'Satuan volume, kualitas cor', 'unit' => 'm3', 'price' => 325000, 'cost' => 260000, 'stock_main' => 75, 'stock_second' => 42, 'alternative' => ['unit' => 'pickup', 'conversion_qty' => '1', 'price' => 390000]],
            ['code' => 'DEMO-SPLIT-001', 'category' => 'Pasir, Batu & Bata', 'name' => 'Batu split 1/2', 'brand' => 'Material Lokal', 'specification' => 'Agregat beton, satuan volume', 'unit' => 'm3', 'price' => 355000, 'cost' => 285000, 'stock_main' => 60, 'stock_second' => 34, 'alternative' => null],
            ['code' => 'DEMO-BATA-001', 'category' => 'Pasir, Batu & Bata', 'name' => 'Bata merah press', 'brand' => 'Material Lokal', 'specification' => 'Ukuran standar, kondisi siap pasang', 'unit' => 'buah', 'price' => 1050, 'cost' => 780, 'stock_main' => 5000, 'stock_second' => 2800, 'alternative' => ['unit' => '100 buah', 'conversion_qty' => '100', 'price' => null]],
            ['code' => 'DEMO-BATA-002', 'category' => 'Pasir, Batu & Bata', 'name' => 'Bata ringan AAC tebal 7,5 cm', 'brand' => 'DindingRingan', 'specification' => 'Ukuran 60 x 20 x 7,5 cm', 'unit' => 'buah', 'price' => 10200, 'cost' => 8500, 'stock_main' => 900, 'stock_second' => 510, 'alternative' => ['unit' => 'kubik', 'conversion_qty' => '111', 'price' => null]],
            ['code' => 'DEMO-PVC-001', 'category' => 'Pipa & Sanitair', 'name' => 'Pipa PVC AW 1/2 inch x 4 m', 'brand' => 'AlirJaya', 'specification' => 'Kelas AW, tekanan tinggi', 'unit' => 'batang', 'price' => 34500, 'cost' => 27000, 'stock_main' => 150, 'stock_second' => 86, 'alternative' => null],
            ['code' => 'DEMO-PIPA-002', 'category' => 'Pipa & Sanitair', 'name' => 'Pipa PVC AW 2 inch x 4 m', 'brand' => 'AlirJaya', 'specification' => 'Kelas AW, pembuangan utama', 'unit' => 'batang', 'price' => 112000, 'cost' => 93000, 'stock_main' => 72, 'stock_second' => 40, 'alternative' => null],
            ['code' => 'DEMO-EL-001', 'category' => 'Pipa & Sanitair', 'name' => 'Elbow PVC 1/2 inch', 'brand' => 'AlirJaya', 'specification' => 'Sambungan sudut 90 derajat', 'unit' => 'pcs', 'price' => 2500, 'cost' => 1250, 'stock_main' => 450, 'stock_second' => 260, 'alternative' => null],
            ['code' => 'DEMO-KERAN-001', 'category' => 'Pipa & Sanitair', 'name' => 'Kran air kuningan 1/2 inch', 'brand' => 'AlirJaya', 'specification' => 'Kran taman/mesin cuci', 'unit' => 'pcs', 'price' => 38500, 'cost' => 29000, 'stock_main' => 48, 'stock_second' => 26, 'alternative' => null],
            ['code' => 'DEMO-CAT-001', 'category' => 'Cat & Pelapis', 'name' => 'Cat tembok interior 5 kg', 'brand' => 'WarnaRuang', 'specification' => 'Daya sebar standar, warna putih', 'unit' => 'pail', 'price' => 248000, 'cost' => 205000, 'stock_main' => 52, 'stock_second' => 31, 'alternative' => null],
            ['code' => 'DEMO-CAT-002', 'category' => 'Cat & Pelapis', 'name' => 'Cat eksterior weatherproof 20 kg', 'brand' => 'WarnaRuang', 'specification' => 'Pelapis tahan cuaca, warna dasar', 'unit' => 'pail', 'price' => 895000, 'cost' => 760000, 'stock_main' => 25, 'stock_second' => 14, 'alternative' => null],
            ['code' => 'DEMO-WATER-001', 'category' => 'Cat & Pelapis', 'name' => 'Pelapis anti bocor 4 kg', 'brand' => 'WarnaRuang', 'specification' => 'Waterproofing elastis, pail 4 kg', 'unit' => 'pail', 'price' => 218000, 'cost' => 176000, 'stock_main' => 38, 'stock_second' => 22, 'alternative' => null],
            ['code' => 'DEMO-KER-001', 'category' => 'Keramik & Finishing', 'name' => 'Keramik lantai 40 x 40 cm', 'brand' => 'LantaiIndah', 'specification' => 'Isi 6 keping, 1,44 m2 per dus', 'unit' => 'dus', 'price' => 68500, 'cost' => 54500, 'stock_main' => 95, 'stock_second' => 55, 'alternative' => ['unit' => 'm2', 'conversion_qty' => '0.6944', 'price' => null]],
            ['code' => 'DEMO-GYPSUM-001', 'category' => 'Keramik & Finishing', 'name' => 'Papan gypsum 9 mm 1,2 x 2,4 m', 'brand' => 'PlafonRapi', 'specification' => 'Papan plafon standar', 'unit' => 'lembar', 'price' => 81500, 'cost' => 67500, 'stock_main' => 85, 'stock_second' => 48, 'alternative' => null],
            ['code' => 'DEMO-KABEL-001', 'category' => 'Listrik & Penerangan', 'name' => 'Kabel NYM 2 x 1,5 mm', 'brand' => 'ArusAman', 'specification' => 'Kabel instalasi rumah, harga per meter', 'unit' => 'm', 'price' => 8500, 'cost' => 6200, 'stock_main' => 1800, 'stock_second' => 950, 'alternative' => ['unit' => 'roll 50 m', 'conversion_qty' => '50', 'price' => null]],
            ['code' => 'DEMO-LAMP-001', 'category' => 'Listrik & Penerangan', 'name' => 'Lampu LED 12 watt', 'brand' => 'ArusAman', 'specification' => 'Cahaya putih, fitting E27', 'unit' => 'pcs', 'price' => 32500, 'cost' => 23000, 'stock_main' => 85, 'stock_second' => 48, 'alternative' => null],
            ['code' => 'DEMO-TOOLS-001', 'category' => 'Perkakas & Bahan Pendukung', 'name' => 'Bor listrik 10 mm', 'brand' => 'PerkasaTeknik', 'specification' => 'Kecepatan variabel, kabel 2 m', 'unit' => 'unit', 'price' => 425000, 'cost' => 345000, 'stock_main' => 12, 'stock_second' => 7, 'alternative' => null],
            ['code' => 'DEMO-NAIL-001', 'category' => 'Perkakas & Bahan Pendukung', 'name' => 'Paku beton 7 cm', 'brand' => 'PerkasaTeknik', 'specification' => 'Kemasan kiloan', 'unit' => 'kg', 'price' => 28500, 'cost' => 20500, 'stock_main' => 75, 'stock_second' => 42, 'alternative' => null],
            ['code' => 'DEMO-SEAL-001', 'category' => 'Perkakas & Bahan Pendukung', 'name' => 'Sealant silikon putih 300 ml', 'brand' => 'RekatPro', 'specification' => 'Untuk kaca dan sambungan sanitair', 'unit' => 'tube', 'price' => 38500, 'cost' => 28500, 'stock_main' => 44, 'stock_second' => 25, 'alternative' => null],
        ];
    }
}