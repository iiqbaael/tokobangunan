<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    rows: Array,
    branches: Array,
    filters: Object,
    canViewCost: Boolean,
    isOwner: Boolean,
});

const branchId = ref(props.filters.branch_id ?? '');
const lowStockOnly = ref(props.filters.low_stock ?? false);

const rupiah = (value) =>
    value === null || value === undefined ? '-' : 'Rp ' + Number(value).toLocaleString('id-ID');

const applyFilter = () => {
    router.get(
        route('reports.stock'),
        { branch_id: branchId.value || undefined, low_stock: lowStockOnly.value ? 1 : undefined },
        { preserveState: true, preserveScroll: true },
    );
};

const totalValue = () => {
    if (!props.canViewCost) return null;
    return props.rows.reduce((sum, r) => sum + Number(r.value ?? 0), 0);
};
</script>

<template>
    <Head title="Laporan Stok" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Laporan Stok</h2>
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

                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input
                                type="checkbox"
                                v-model="lowStockOnly"
                                @change="applyFilter"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            Hanya barang menipis (qty ≤ min stok)
                        </label>
                    </div>
                </div>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-gray-500">
                                <th class="px-4 py-2 font-medium">Cabang</th>
                                <th class="px-4 py-2 font-medium">Kode</th>
                                <th class="px-4 py-2 font-medium">Nama Barang</th>
                                <th class="px-4 py-2 font-medium">Kategori</th>
                                <th class="px-4 py-2 text-right font-medium">Qty</th>
                                <th class="px-4 py-2 font-medium">Satuan</th>
                                <th class="px-4 py-2 text-right font-medium">Min Stok</th>
                                <th v-if="canViewCost" class="px-4 py-2 text-right font-medium">Avg Cost</th>
                                <th v-if="canViewCost" class="px-4 py-2 text-right font-medium">Nilai Stok</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(r, idx) in rows" :key="idx" :class="r.is_low ? 'bg-red-50' : ''">
                                <td class="px-4 py-2 text-gray-700">{{ r.branch_code }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ r.item_code }}</td>
                                <td class="px-4 py-2 text-gray-900">
                                    {{ r.item_name }}
                                    <span v-if="r.is_low" class="ml-1 text-xs font-medium text-red-600">menipis</span>
                                </td>
                                <td class="px-4 py-2 text-gray-500">{{ r.category_name ?? '-' }}</td>
                                <td class="px-4 py-2 text-right text-gray-900">{{ Number(r.qty).toLocaleString('id-ID') }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ r.unit }}</td>
                                <td class="px-4 py-2 text-right text-gray-500">{{ Number(r.min_stock).toLocaleString('id-ID') }}</td>
                                <td v-if="canViewCost" class="px-4 py-2 text-right text-gray-700">{{ rupiah(r.avg_cost) }}</td>
                                <td v-if="canViewCost" class="px-4 py-2 text-right text-gray-700">{{ rupiah(r.value) }}</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="canViewCost && rows.length" class="bg-gray-50">
                            <tr>
                                <td :colspan="7" class="px-4 py-2 text-right font-medium text-gray-700">Total Nilai Stok</td>
                                <td></td>
                                <td class="px-4 py-2 text-right font-semibold text-gray-900">{{ rupiah(totalValue()) }}</td>
                            </tr>
                        </tfoot>
                    </table>

                    <p v-if="rows.length === 0" class="py-8 text-center text-sm text-gray-400">
                        Tidak ada data stok.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>