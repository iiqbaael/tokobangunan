<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Banknote, CirclePlus, PackagePlus, Plus, Search, ShoppingCart, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    branchId: { type: [Number, String], default: null },
    hasOpenShift: { type: Boolean, default: false },
    items: { type: Array, default: () => [] },
    customers: { type: Array, default: () => [] },
    canGiveCredit: { type: Boolean, default: false },
    canVoid: { type: Boolean, default: false },
    history: { type: Object, default: null }, // paginator atau null
});

// ---------- Pencarian barang ----------
const search = ref('');
const filteredItems = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.items.slice(0, 20);
    return props.items
        .filter(
            (i) =>
                i.name.toLowerCase().includes(q) ||
                i.code.toLowerCase().includes(q) ||
                (i.barcode && i.barcode.toLowerCase().includes(q))
        )
        .slice(0, 20);
});

function unitOptions(item) {
    const base = { unit: item.unit, conversion_qty: 1, sell_price: Number(item.sell_price) };
    const alts = (item.units || []).map((u) => ({
        unit: u.unit,
        conversion_qty: Number(u.conversion_qty),
        sell_price:
            u.sell_price !== null && u.sell_price !== undefined
                ? Number(u.sell_price)
                : Math.round(Number(item.sell_price) * Number(u.conversion_qty)),
    }));
    return [base, ...alts];
}

// ---------- Keranjang ----------
// unit_price di sini HANYA untuk pratinjau subtotal di layar.
// Tidak dikirim ke server -- server hitung ulang dari items.sell_price/item_units.sell_price.
const cart = reactive([]);

function addToCart(item) {
    const existingLine = cart.find((line) => line.item_id === item.id);

    if (existingLine) {
        existingLine.qty_input = (Number(existingLine.qty_input) || 0) + 1;
        search.value = '';
        return;
    }

    const options = unitOptions(item);
    const chosen = options[0];
    cart.push({
        item_id: item.id,
        name: item.name,
        options,
        unit: chosen.unit,
        preview_price: chosen.sell_price,
        qty_input: 1,
    });
    search.value = '';
}

function onUnitChange(line) {
    const opt = line.options.find((o) => o.unit === line.unit);
    line.preview_price = opt.sell_price;
}

function removeLine(index) {
    cart.splice(index, 1);
}

function lineSubtotal(line) {
    return Math.round((Number(line.qty_input) || 0) * (Number(line.preview_price) || 0));
}

const total = computed(() => cart.reduce((sum, l) => sum + lineSubtotal(l), 0));
const rupiah = (value) => `Rp ${Number(value ?? 0).toLocaleString('id-ID')}`;

// ---------- Pembayaran ----------
const payments = reactive([{ method: 'cash', amount: 0, reference_no: '' }]);
const customerId = ref(null);
const note = ref('');

const paymentMethods = computed(() => {
    const base = [
        { value: 'cash', label: 'Tunai' },
        { value: 'transfer', label: 'Transfer' },
        { value: 'ewallet', label: 'E-Wallet' },
        { value: 'card', label: 'Kartu' },
    ];
    if (props.canGiveCredit) base.push({ value: 'credit', label: 'Kredit (Bon)' });
    return base;
});

function addPaymentLine() {
    payments.push({ method: 'cash', amount: 0, reference_no: '' });
}

function removePaymentLine(index) {
    if (payments.length > 1) payments.splice(index, 1);
}

const paymentTotal = computed(() => payments.reduce((sum, p) => sum + (Number(p.amount) || 0), 0));
const hasCreditPayment = computed(() => payments.some((p) => p.method === 'credit'));
const cashPortion = computed(() =>
    payments.filter((p) => p.method === 'cash').reduce((s, p) => s + (Number(p.amount) || 0), 0)
);
const cashReceived = ref(0);
const changeGiven = computed(() => {
    const c = (Number(cashReceived.value) || 0) - cashPortion.value;
    return c > 0 ? c : 0;
});

// ---------- Validasi & submit ----------
const errors = ref({});
const processing = ref(false);

function validateBeforeSubmit() {
    const e = {};
    if (!props.hasOpenShift) e.shift = 'Belum ada shift open di cabang ini.';
    if (cart.length === 0) e.cart = 'Keranjang masih kosong.';
    if (paymentTotal.value !== total.value) {
        e.payments = `Total pembayaran (${paymentTotal.value}) harus sama dengan total belanja (${total.value}) -- ini pratinjau, server tetap yang menentukan harga final.`;
    }
    if (hasCreditPayment.value && !customerId.value) {
        e.customer = 'Pelanggan wajib dipilih untuk pembayaran kredit.';
    }
    if (cashPortion.value > 0 && Number(cashReceived.value) < cashPortion.value) {
        e.cash_received = 'Uang tunai diterima kurang dari komponen tunai.';
    }
    errors.value = e;
    return Object.keys(e).length === 0;
}

function submitSale() {
    if (!validateBeforeSubmit()) return;

    processing.value = true;
    router.post(
        route('sales.store'),
        {
            customer_id: customerId.value,
            note: note.value || null,
            cash_received: cashPortion.value > 0 ? Number(cashReceived.value) : null,
            items: cart.map((l) => ({
                item_id: l.item_id,
                unit: l.unit,
                qty_input: Number(l.qty_input),
            })),
            payments: payments.map((p) => ({
                method: p.method,
                amount: Number(p.amount),
                reference_no: p.reference_no || null,
            })),
        },
        {
            preserveScroll: true,
            onError: (err) => {
                errors.value = err;
            },
            onSuccess: () => {
                cart.splice(0, cart.length);
                payments.splice(0, payments.length, { method: 'cash', amount: 0, reference_no: '' });
                cashReceived.value = 0;
                customerId.value = null;
                note.value = '';
            },
            onFinish: () => {
                processing.value = false;
            },
        }
    );
}

// ---------- Void ----------
const voidingId = ref(null);
const voidReason = ref('');

function openVoid(saleId) {
    voidingId.value = saleId;
    voidReason.value = '';
}

function confirmVoid() {
    if (!voidReason.value.trim()) return;
    router.patch(
        route('sales.void', voidingId.value),
        { void_reason: voidReason.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                voidingId.value = null;
                voidReason.value = '';
            },
        }
    );
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="POS - Penjualan" />

        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold text-brand-primary">KASIR</p>
                    <h1 class="mt-1 text-xl font-bold text-text-primary">POS Penjualan</h1>
                </div>
                <span
                    class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold"
                    :class="hasOpenShift ? 'bg-status-success-soft text-status-success' : 'bg-status-warning-soft text-status-warning'"
                >
                    <span class="h-2 w-2 rounded-full" :class="hasOpenShift ? 'bg-status-success' : 'bg-status-warning'" />
                    {{ hasOpenShift ? 'Shift aktif' : 'Shift belum dibuka' }}
                </span>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-5 px-4 py-6 lg:px-6">
            <div v-if="!hasOpenShift" class="flex items-center gap-3 rounded-lg border border-status-warning bg-status-warning-soft p-4 text-sm text-status-warning">
                Belum ada shift open di cabang ini. Buka shift dulu sebelum bertransaksi.
            </div>

            <div class="grid grid-cols-1 items-start gap-5 xl:grid-cols-[minmax(0,1.15fr)_minmax(24rem,0.85fr)]">
                <!-- Kolom kiri: cari & pilih barang -->
                <section class="rounded-xl border border-border bg-surface p-4 shadow-sm sm:p-5">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-accent-sky-soft text-accent-sky">
                                <PackagePlus :size="18" />
                            </span>
                            <div>
                                <h2 class="text-base font-semibold text-text-primary">Pilih Barang</h2>
                                <p class="mt-0.5 text-xs text-text-secondary">Pilih barang untuk menambahkannya ke keranjang.</p>
                            </div>
                        </div>
                        <span class="rounded-full bg-surface-muted px-2.5 py-1 text-xs font-medium text-text-secondary">{{ filteredItems.length }} hasil</span>
                    </div>

                    <label class="mb-3 flex items-center gap-2 rounded-lg border border-border bg-surface/90 px-3 py-2.5 shadow-sm backdrop-blur-md transition focus-within:border-brand-primary focus-within:ring-2 focus-within:ring-brand-primary-soft">
                        <Search :size="16" class="shrink-0 text-text-disabled" />
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Cari nama, kode, atau barcode..."
                            class="w-full border-0 bg-transparent p-0 text-sm text-text-primary placeholder:text-text-disabled focus:outline-none focus:ring-0"
                        />
                    </label>

                    <ul class="grid max-h-136 grid-cols-1 gap-2 overflow-y-auto overscroll-contain pr-1 sm:grid-cols-2">
                        <li
                            v-for="item in filteredItems"
                            :key="item.id"
                            class="min-w-0"
                        >
                            <button
                                type="button"
                                class="group flex h-full min-h-20 w-full items-center gap-3 rounded-lg border border-border bg-surface p-3 text-left transition duration-200 hover:-translate-y-0.5 hover:border-brand-primary hover:bg-brand-primary-soft/30 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary"
                                :disabled="!hasOpenShift"
                                @click="addToCart(item)"
                            >
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-surface-muted text-text-secondary transition group-hover:bg-brand-primary group-hover:text-white">
                                    <CirclePlus :size="17" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-text-primary">{{ item.name }}</span>
                                    <span class="mt-1 block truncate text-xs text-text-secondary">{{ item.code }} · {{ item.unit }}</span>
                                </span>
                                <span class="shrink-0 text-right text-xs font-semibold tabular-nums text-brand-secondary">{{ rupiah(item.sell_price) }}</span>
                            </button>
                        </li>
                        <li v-if="filteredItems.length === 0" class="col-span-full rounded-lg border border-dashed border-border px-4 py-10 text-center text-sm text-text-secondary">
                            Tidak ada barang yang cocok dengan pencarian.
                        </li>
                    </ul>
                </section>

                <!-- Kolom kanan: keranjang -->
                <section class="rounded-xl border border-border bg-surface p-4 shadow-sm sm:p-5">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-accent-indigo-soft text-accent-indigo">
                                <ShoppingCart :size="18" />
                            </span>
                            <div>
                                <h2 class="text-base font-semibold text-text-primary">Keranjang</h2>
                                <p class="mt-0.5 text-xs text-text-secondary">{{ cart.length }} jenis barang</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="cart.length" class="max-h-72 space-y-2 overflow-y-auto overscroll-contain pr-1">
                        <article v-for="(line, idx) in cart" :key="idx" class="grid grid-cols-2 gap-3 rounded-lg border border-border bg-page/70 p-3 sm:grid-cols-[minmax(0,1fr)_5.5rem_6rem_auto] sm:items-center">
                            <div class="col-span-2 min-w-0 sm:col-span-1">
                                <p class="truncate text-sm font-semibold text-text-primary">{{ line.name }}</p>
                                <p class="mt-0.5 text-xs text-text-secondary">{{ rupiah(line.preview_price) }} / {{ line.unit }}</p>
                            </div>
                            <select v-model="line.unit" class="min-w-0 rounded-lg border-border bg-surface px-2 py-2 text-xs text-text-primary focus:border-brand-primary focus:ring-brand-primary" :aria-label="`Satuan ${line.name}`" @change="onUnitChange(line)">
                                <option v-for="o in line.options" :key="o.unit" :value="o.unit">{{ o.unit }}</option>
                            </select>
                            <input v-model.number="line.qty_input" type="number" min="0.01" step="1" class="w-full rounded-lg border-border bg-surface px-2 py-2 text-sm text-text-primary focus:border-brand-primary focus:ring-brand-primary" :aria-label="`Jumlah ${line.name}`" @wheel="$event.currentTarget.blur()" />
                            <div class="flex items-center justify-between gap-2 sm:justify-end">
                                <span class="text-xs font-semibold tabular-nums text-text-primary">{{ rupiah(lineSubtotal(line)) }}</span>
                                <button type="button" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-text-disabled hover:bg-status-danger-soft hover:text-status-danger" :aria-label="`Hapus ${line.name}`" title="Hapus barang" @click="removeLine(idx)">
                                    <Trash2 :size="15" />
                                </button>
                            </div>
                        </article>
                    </div>
                    <div v-else class="rounded-lg border border-dashed border-border px-4 py-8 text-center">
                        <ShoppingCart :size="22" class="mx-auto text-text-disabled" />
                        <p class="mt-2 text-sm font-medium text-text-secondary">Keranjang masih kosong</p>
                        <p class="mt-1 text-xs text-text-disabled">Pilih barang di sebelah kiri untuk memulai transaksi.</p>
                    </div>

                    <p class="mt-2 text-xs text-text-disabled">Harga dan subtotal adalah pratinjau; server menentukan nilai final.</p>
                    <p v-if="errors.cart" class="mt-2 rounded-lg bg-status-danger-soft px-3 py-2 text-sm text-status-danger">{{ errors.cart }}</p>

                    <div class="mt-4 flex items-end justify-between gap-3 rounded-lg bg-brand-dark px-4 py-3 text-white">
                        <div>
                            <p class="text-xs text-white/65">Total pratinjau</p>
                            <p class="mt-0.5 text-xl font-bold tabular-nums">{{ rupiah(total) }}</p>
                        </div>
                        <Banknote :size="20" class="mb-1 text-white/70" />
                    </div>

                    <!-- Pembayaran -->
                    <div class="mt-5 border-t border-border pt-5">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div>
                                <h2 class="text-sm font-semibold text-text-primary">Pembayaran</h2>
                                <p class="mt-0.5 text-xs text-text-secondary">Total dibayar {{ rupiah(paymentTotal) }}</p>
                            </div>
                            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-brand-primary hover:bg-brand-primary-soft" @click="addPaymentLine">
                                <Plus :size="14" /> Tambah metode
                            </button>
                        </div>
                        <div v-for="(p, idx) in payments" :key="idx" class="mb-2 grid grid-cols-1 gap-2 rounded-lg border border-border p-3 sm:grid-cols-[minmax(7rem,0.9fr)_minmax(7rem,0.9fr)_minmax(0,1fr)_auto] sm:items-center">
                            <select v-model="p.method" class="rounded-lg border-border bg-surface px-2 py-2 text-xs text-text-primary focus:border-brand-primary focus:ring-brand-primary" :aria-label="`Metode pembayaran ${idx + 1}`">
                                <option v-for="m in paymentMethods" :key="m.value" :value="m.value">{{ m.label }}</option>
                            </select>
                            <input v-model.number="p.amount" type="number" min="0" step="1" placeholder="Jumlah" class="w-full rounded-lg border-border bg-surface px-2 py-2 text-sm text-text-primary placeholder:text-text-disabled focus:border-brand-primary focus:ring-brand-primary" :aria-label="`Jumlah pembayaran ${idx + 1}`" @wheel="$event.currentTarget.blur()" />
                            <input
                                v-if="p.method !== 'cash' && p.method !== 'credit'"
                                v-model="p.reference_no"
                                type="text"
                                placeholder="No. referensi"
                                class="min-w-0 rounded-lg border-border bg-surface px-2 py-2 text-sm text-text-primary placeholder:text-text-disabled focus:border-brand-primary focus:ring-brand-primary"
                            />
                            <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg text-text-disabled hover:bg-status-danger-soft hover:text-status-danger disabled:opacity-40" :disabled="payments.length === 1" :aria-label="`Hapus metode pembayaran ${idx + 1}`" @click="removePaymentLine(idx)">
                                <Trash2 :size="14" />
                            </button>
                        </div>
                        <p v-if="errors.payments" class="mt-2 rounded-lg bg-status-danger-soft px-3 py-2 text-sm text-status-danger">{{ errors.payments }}</p>

                        <div v-if="cashPortion > 0" class="mt-3 grid grid-cols-1 gap-3 rounded-lg bg-surface-muted p-3 sm:grid-cols-2">
                            <label class="text-xs font-medium text-text-secondary">Uang diterima (tunai)
                                <input v-model.number="cashReceived" type="number" min="0" step="1" class="mt-1 block w-full rounded-lg border-border bg-surface px-3 py-2 text-sm text-text-primary focus:border-brand-primary focus:ring-brand-primary" @wheel="$event.currentTarget.blur()" />
                            </label>
                            <div class="self-end rounded-lg bg-surface px-3 py-2">
                                <p class="text-xs text-text-secondary">Kembalian</p>
                                <p class="mt-0.5 text-sm font-semibold tabular-nums text-text-primary">{{ rupiah(changeGiven) }}</p>
                            </div>
                        </div>
                        <p v-if="errors.cash_received" class="mt-2 text-sm text-status-danger">{{ errors.cash_received }}</p>

                        <div v-if="hasCreditPayment" class="mt-3">
                            <label class="text-xs font-medium text-text-secondary">Pelanggan (wajib untuk kredit)
                            <select v-model="customerId" class="mt-1 block w-full rounded-lg border-border bg-surface px-3 py-2 text-sm text-text-primary focus:border-brand-primary focus:ring-brand-primary">
                                <option :value="null">-- pilih pelanggan --</option>
                                <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }} ({{ c.phone }})</option>
                            </select>
                            </label>
                            <p v-if="errors.customer" class="mt-1 text-sm text-status-danger">{{ errors.customer }}</p>
                        </div>

                        <div class="mt-3">
                            <label class="text-xs font-medium text-text-secondary">Catatan (opsional)
                                <input v-model="note" type="text" class="mt-1 block w-full rounded-lg border-border bg-surface px-3 py-2 text-sm text-text-primary focus:border-brand-primary focus:ring-brand-primary" maxlength="255" />
                            </label>
                        </div>

                        <p v-if="errors.shift" class="mt-3 text-sm text-status-danger">{{ errors.shift }}</p>

                        <button
                            type="button"
                            :disabled="processing || !hasOpenShift"
                            class="mt-4 flex w-full items-center justify-center gap-2 rounded-lg bg-brand-primary px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:brightness-95 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50"
                            @click="submitSale"
                        >
                            <ShoppingCart :size="16" />
                            {{ processing ? 'Menyimpan...' : 'Simpan Transaksi' }}
                        </button>
                    </div>
                </section>
            </div>

            <!-- Riwayat penjualan (Admin/Owner) -->
            <section v-if="history" class="rounded-xl border border-border bg-surface p-4 shadow-sm sm:p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-text-primary">Riwayat Penjualan</h2>
                        <p class="mt-0.5 text-xs text-text-secondary">Transaksi terbaru di cabang ini.</p>
                    </div>
                    <span class="rounded-full bg-surface-muted px-2.5 py-1 text-xs font-medium text-text-secondary">{{ history.total }} transaksi</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-180 w-full divide-y divide-border text-sm">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase text-text-secondary">
                                <th class="py-3 pr-3">Invoice</th>
                                <th class="py-3 pr-3">Tanggal</th>
                                <th class="py-3 pr-3">Pelanggan</th>
                                <th class="py-3 pr-3">Kasir</th>
                                <th class="py-3 pr-3 text-right">Total</th>
                                <th class="py-3 pr-3">Status</th>
                                <th class="py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="s in history.data" :key="s.id">
                                <td class="py-3 pr-3 font-medium text-text-primary">{{ s.invoice_no }}</td>
                                <td class="py-3 pr-3 text-text-secondary">{{ s.sale_date }}</td>
                                <td class="py-3 pr-3 text-text-secondary">{{ s.customer_name ?? '-' }}</td>
                                <td class="py-3 pr-3 text-text-secondary">{{ s.creator_name }}</td>
                                <td class="py-3 pr-3 text-right font-medium tabular-nums text-text-primary">{{ rupiah(s.total) }}</td>
                                <td class="py-3 pr-3">
                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="s.status === 'void' ? 'bg-status-danger-soft text-status-danger' : 'bg-status-success-soft text-status-success'">
                                        {{ s.status === 'void' ? 'Void' : 'Selesai' }}
                                    </span>
                                    <div v-if="s.status === 'void' && s.void_reason" class="mt-1 max-w-48 text-xs text-text-secondary">{{ s.void_reason }}</div>
                                </td>
                                <td class="py-3 text-right">
                                    <button
                                        v-if="canVoid && s.status !== 'void'"
                                        type="button"
                                        class="rounded-md px-2 py-1 text-xs font-semibold text-status-danger hover:bg-status-danger-soft"
                                        @click="openVoid(s.id)"
                                    >
                                        Void
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="history.data.length === 0">
                                <td colspan="7" class="py-10 text-center text-sm text-text-secondary">Belum ada transaksi.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="history.links" class="mt-4 flex flex-wrap gap-1 border-t border-border pt-4">
                    <button
                        v-for="(link, i) in history.links"
                        :key="i"
                        :disabled="!link.url"
                        class="rounded-lg border border-border px-3 py-1.5 text-xs text-text-secondary hover:bg-surface-muted disabled:opacity-40"
                        :class="link.active ? 'border-brand-primary bg-brand-primary text-white hover:bg-brand-primary' : ''"
                        v-html="link.label"
                        @click="link.url && router.get(link.url, {}, { preserveScroll: true, preserveState: true })"
                    ></button>
                </div>
            </section>

            <!-- Modal sederhana void -->
            <div v-if="voidingId" class="fixed inset-0 z-50 flex items-center justify-center bg-brand-dark/50 px-4 py-6 backdrop-blur-sm">
                <div class="w-full max-w-sm rounded-xl border border-border bg-surface p-5 shadow-2xl">
                    <h3 class="text-base font-semibold text-text-primary">Void Transaksi</h3>
                    <p class="mt-1 text-sm text-text-secondary">Masukkan alasan pembatalan transaksi ini.</p>
                    <textarea v-model="voidReason" rows="3" class="mt-4 w-full rounded-lg border-border bg-surface text-sm text-text-primary placeholder:text-text-disabled focus:border-brand-primary focus:ring-brand-primary" placeholder="Alasan wajib diisi"></textarea>
                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" class="rounded-lg px-3 py-2 text-sm font-medium text-text-secondary hover:bg-surface-muted" @click="voidingId = null">Batal</button>
                        <button type="button" class="rounded-lg bg-status-danger px-3 py-2 text-sm font-semibold text-white transition hover:brightness-95 disabled:opacity-50" :disabled="!voidReason.trim()" @click="confirmVoid">Konfirmasi Void</button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>