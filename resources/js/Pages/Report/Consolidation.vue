<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, router } from "@inertiajs/vue3";
import { reactive } from "vue";

const props = defineProps({
    perBranch: Array,
    totals: Object,
    totalReceivable: [String, Number],
    filters: Object,
});

const form = reactive({
    date_from: props.filters.date_from ?? "",
    date_to: props.filters.date_to ?? "",
});

function applyFilter() {
    router.get(route("reports.consolidation"), form, {
        preserveState: true,
        replace: true,
    });
}

function formatMoney(v) {
    if (v === null || v === undefined) return "-";
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
    }).format(v);
}
</script>

<template>
    <Head title="Laporan Konsolidasi" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Laporan Konsolidasi Semua Cabang
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                <!-- Filter -->
                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">
                                Dari Tanggal
                            </label>
                            <input
                                type="date"
                                v-model="form.date_from"
                                class="w-full rounded-md border-gray-300 text-sm"
                            />
                        </div>

                        <div>
                            <label class="mb-1 block text-sm text-gray-600">
                                Sampai Tanggal
                            </label>
                            <input
                                type="date"
                                v-model="form.date_to"
                                class="w-full rounded-md border-gray-300 text-sm"
                            />
                        </div>
                    </div>

                    <p class="mt-2 text-xs text-gray-400">
                        Omzet dan laba mengikuti rentang tanggal di atas.
                        Nilai stok dan piutang adalah posisi saat ini
                        (tidak terikat rentang tanggal).
                    </p>

                    <div class="mt-4">
                        <button
                            type="button"
                            @click="applyFilter"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                        >
                            Terapkan Filter
                        </button>
                    </div>
                </div>

                <!-- Kartu ringkasan -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <div class="rounded-lg bg-white p-4 shadow">
                        <div class="text-sm text-gray-600">
                            Total Omzet
                        </div>
                        <div class="text-xl font-semibold text-gray-800">
                            {{ formatMoney(totals.omzet) }}
                        </div>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow">
                        <div class="text-sm text-gray-600">
                            Total Laba Kotor
                        </div>
                        <div class="text-xl font-semibold text-green-600">
                            {{ formatMoney(totals.profit) }}
                        </div>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow">
                        <div class="text-sm text-gray-600">
                            Total Nilai Stok
                        </div>
                        <div class="text-xl font-semibold text-gray-800">
                            {{ formatMoney(totals.stock_value) }}
                        </div>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow">
                        <div class="text-sm text-gray-600">
                            Total Piutang
                        </div>
                        <div class="text-xl font-semibold text-amber-600">
                            {{ formatMoney(totalReceivable) }}
                        </div>
                        <div class="mt-1 text-xs text-gray-400">
                            Perusahaan (per pelanggan, bukan per cabang)
                        </div>
                    </div>
                </div>

                <!-- Breakdown per cabang -->
                <div class="overflow-x-auto rounded-lg bg-white shadow">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">
                                    Cabang
                                </th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">
                                    Omzet
                                </th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">
                                    Laba Kotor
                                </th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">
                                    Nilai Stok
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr
                                v-for="r in perBranch"
                                :key="r.branch_id"
                                class="hover:bg-gray-50"
                            >
                                <td class="px-4 py-2 font-medium text-gray-800">
                                    {{ r.branch_code }} - {{ r.branch_name }}
                                </td>
                                <td class="px-4 py-2 text-right">
                                    {{ formatMoney(r.omzet) }}
                                </td>
                                <td class="px-4 py-2 text-right text-green-600">
                                    {{ formatMoney(r.profit) }}
                                </td>
                                <td class="px-4 py-2 text-right">
                                    {{ formatMoney(r.stock_value) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50 font-semibold">
                            <tr>
                                <td class="px-4 py-2">Total</td>
                                <td class="px-4 py-2 text-right">
                                    {{ formatMoney(totals.omzet) }}
                                </td>
                                <td class="px-4 py-2 text-right text-green-600">
                                    {{ formatMoney(totals.profit) }}
                                </td>
                                <td class="px-4 py-2 text-right">
                                    {{ formatMoney(totals.stock_value) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>