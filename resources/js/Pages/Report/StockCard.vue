<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    filters: Object,
    branches: Array,
    items: Array,
    item: Object,
    opening: String,
    rows: Array,
    closing: String,
    canViewCost: Boolean,
});

const branchId = ref(props.filters.branch_id ?? '');
const itemId = ref(props.filters.item_id ?? '');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');

const typeLabels = {
    sale_out: 'Penjualan',
    sale_void: 'Void Penjualan',
    transfer_out: 'Transfer Keluar',
    transfer_in: 'Transfer Masuk',
    adjustment_in: 'Penyesuaian Masuk',
    adjustment_out: 'Penyesuaian Keluar',
};

const qtyFmt = (v) =>
    v === null || v === undefined ? '' : Number(v).toLocaleString('id-ID', { maximumFractionDigits: 4 });

const rupiah = (v) =>
    v === null || v === undefined ? '-' : 'Rp ' + Number(v).toLocaleString('id-ID', { maximumFractionDigits: 4 });

const dateFmt = (v) => (v ? String(v).replace('T', ' ').substring(0, 16) : '-');

const applyFilter = () => {
    router.get(
        route('reports.stock-card'),
        {
            branch_id: branchId.value || undefined,
            item_id: itemId.value || undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
        },
        { preserveState: true, preserveScroll: true },
    );
};

const resetFilter = () => {
    dateFrom.value = '';
    dateTo.value = '';
    applyFilter();
};
</script>

<template>
    <Head title="Kartu Stok" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Kartu Stok</h2>
                <Link :href="route('reports.stock')" class="text-sm text-indigo-600 hover:underline">
                    ← Laporan Stok
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-6xl space-y-4 sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <div class="flex flex-wrap items-end gap-3">
                        <div v-if="branches.length">
                            <label class="block text-sm font-medium text-gray-700">Cabang</label>
                            <select
                                v-model="branchId"
                                @change="applyFilter"
                                class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option v-for="b in branches" :key="b.id" :value="b.id">
                                    {{ b.code }} — {{ b.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Barang</label>
                            <select
                                v-model="itemId"
                                @change="applyFilter"
                                class="mt-1 block w-72 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">— Pilih barang —</option>
                                <option v-for="i in items" :key="i.id" :value="i.id">
                                    {{ i.code }} — {{ i.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Dari</label>
                            <input
                                type="date"
                                v-model="dateFrom"
                                @change="applyFilter"
                                class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Sampai</label>
                            <input
                                type="date"
                                v-model="dateTo"
                                @change="applyFilter"
                                class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>

                        <button
                            v-if="dateFrom || dateTo"
                            type="button"
                            @click="resetFilter"
                            class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                        >
                            Reset tanggal
                        </button>
                    </div>
                </div>

                <div v-if="!item" class="overflow-hidden bg-white p-8 text-center text-sm text-gray-400 shadow-sm sm:rounded-lg">
                    Pilih barang untuk menampilkan kartu stok.
                </div>

                <div v-else class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="border-b border-gray-100 px-4 py-3 text-sm text-gray-700">
                        <span class="font-medium text-gray-900">{{ item.code }} — {{ item.name }}</span>
                        <span class="ml-2 text-gray-500">(satuan dasar: {{ item.unit }})</span>
                    </div>

                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-gray-500">
                                <th class="px-4 py-2 font-medium">Tanggal</th>
                                <th class="px-4 py-2 font-medium">Jenis</th>
                                <th class="px-4 py-2 font-medium">No. Dokumen</th>
                                <th class="px-4 py-2 text-right font-medium">Masuk</th>
                                <th class="px-4 py-2 text-right font-medium">Keluar</th>
                                <th class="px-4 py-2 text-right font-medium">Saldo</th>
                                <th v-if="canViewCost" class="px-4 py-2 text-right font-medium">Unit Cost</th>
                                <th class="px-4 py-2 font-medium">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr class="bg-gray-50">
                                <td colspan="5" class="px-4 py-2 font-medium text-gray-700">Saldo awal</td>
                                <td class="px-4 py-2 text-right font-medium text-gray-900">{{ qtyFmt(opening) }}</td>
                                <td v-if="canViewCost"></td>
                                <td></td>
                            </tr>
                            <tr v-for="r in rows" :key="r.id">
                                <td class="px-4 py-2 text-gray-700">{{ dateFmt(r.date) }}</td>
                                <td class="px-4 py-2 text-gray-700">{{ typeLabels[r.type] ?? r.type }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ r.document_no ?? '-' }}</td>
                                <td class="px-4 py-2 text-right text-green-700">{{ qtyFmt(r.qty_in) }}</td>
                                <td class="px-4 py-2 text-right text-red-700">{{ qtyFmt(r.qty_out) }}</td>
                                <td class="px-4 py-2 text-right font-medium text-gray-900">{{ qtyFmt(r.balance) }}</td>
                                <td v-if="canViewCost" class="px-4 py-2 text-right text-gray-500">{{ rupiah(r.unit_cost) }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ r.note ?? '' }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="5" class="px-4 py-2 font-medium text-gray-700">Saldo akhir</td>
                                <td class="px-4 py-2 text-right font-semibold text-gray-900">{{ qtyFmt(closing) }}</td>
                                <td v-if="canViewCost"></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>

                    <p v-if="rows.length === 0" class="py-6 text-center text-sm text-gray-400">
                        Tidak ada mutasi pada rentang ini.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>