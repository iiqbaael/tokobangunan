<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    rows: Array,
    totals: Object,
    branches: Array,
    filters: Object,
    isOwner: Boolean,
});

const branchId = ref(props.filters.branch_id ?? '');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');

const rupiah = (v) =>
    v === null || v === undefined ? '-' : 'Rp ' + Number(v).toLocaleString('id-ID');
const qtyFmt = (v) => Number(v).toLocaleString('id-ID', { maximumFractionDigits: 4 });

const applyFilter = () => {
    router.get(
        route('reports.gross-profit'),
        {
            branch_id: branchId.value || undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
        },
        { preserveState: true, preserveScroll: true },
    );
};
</script>

<template>
    <Head title="Laba Kotor" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Laporan Laba Kotor</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-6xl space-y-4 sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <div class="flex flex-wrap items-end gap-3">
                        <div v-if="isOwner">
                            <label class="block text-sm font-medium text-gray-700">Cabang</label>
                            <select
                                v-model="branchId"
                                @change="applyFilter"
                                class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">Semua Cabang</option>
                                <option v-for="b in branches" :key="b.id" :value="b.id">
                                    {{ b.code }} — {{ b.name }}
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
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <div class="text-xs text-gray-500">Penjualan</div>
                        <div class="text-lg font-semibold text-gray-900">{{ rupiah(totals.revenue) }}</div>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <div class="text-xs text-gray-500">HPP</div>
                        <div class="text-lg font-semibold text-gray-900">{{ rupiah(totals.cogs) }}</div>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <div class="text-xs text-gray-500">Laba Kotor</div>
                        <div class="text-lg font-semibold text-green-700">{{ rupiah(totals.profit) }}</div>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <div class="text-xs text-gray-500">Margin</div>
                        <div class="text-lg font-semibold text-gray-900">{{ totals.margin }}%</div>
                    </div>
                </div>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-gray-500">
                                <th class="px-4 py-2 font-medium">Cabang</th>
                                <th class="px-4 py-2 font-medium">Kode</th>
                                <th class="px-4 py-2 font-medium">Nama Barang</th>
                                <th class="px-4 py-2 text-right font-medium">Qty Terjual</th>
                                <th class="px-4 py-2 font-medium">Satuan</th>
                                <th class="px-4 py-2 text-right font-medium">Penjualan</th>
                                <th class="px-4 py-2 text-right font-medium">HPP</th>
                                <th class="px-4 py-2 text-right font-medium">Laba</th>
                                <th class="px-4 py-2 text-right font-medium">Margin</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(r, idx) in rows" :key="idx">
                                <td class="px-4 py-2 text-gray-700">{{ r.branch_code }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ r.item_code }}</td>
                                <td class="px-4 py-2 text-gray-900">{{ r.item_name }}</td>
                                <td class="px-4 py-2 text-right text-gray-900">{{ qtyFmt(r.qty_sold) }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ r.unit }}</td>
                                <td class="px-4 py-2 text-right text-gray-700">{{ rupiah(r.revenue) }}</td>
                                <td class="px-4 py-2 text-right text-gray-700">{{ rupiah(r.cogs) }}</td>
                                <td class="px-4 py-2 text-right font-medium text-gray-900">{{ rupiah(r.profit) }}</td>
                                <td class="px-4 py-2 text-right text-gray-500">{{ r.margin }}%</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="rows.length === 0" class="py-8 text-center text-sm text-gray-400">
                        Tidak ada penjualan pada rentang ini.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>