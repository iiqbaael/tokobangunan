<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, router } from "@inertiajs/vue3";
import { reactive } from "vue";

const props = defineProps({
    rows: Array,
    totalLoss: [String, Number],
    branches: Array,
    filters: Object,
    isOwner: Boolean,
});

const form = reactive({
    branch_id: props.filters.branch_id ?? "",
    date_from: props.filters.date_from ?? "",
    date_to: props.filters.date_to ?? "",
});

function applyFilter() {
    router.get(route("reports.written-off"), form, {
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

function formatQty(v) {
    if (v === null || v === undefined) return "-";
    return new Intl.NumberFormat("id-ID", {
        maximumFractionDigits: 4,
    }).format(v);
}

function formatDate(v) {
    if (!v) return "-";
    return new Date(v).toLocaleDateString("id-ID", { dateStyle: "medium" });
}
</script>

<template>
    <Head title="Laporan Kerugian Transfer" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Laporan Kerugian Transfer
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                <p class="text-sm text-gray-500">
                    Selisih transfer antar cabang yang diselesaikan sebagai
                    <span class="font-medium">written_off</span> (dianggap
                    kerugian). Kerugian dibebankan ke cabang asal, karena stok
                    sudah keluar saat transfer dikirim.
                </p>

                <!-- Filter -->
                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div v-if="isOwner">
                            <label class="mb-1 block text-sm text-gray-600">
                                Cabang Asal
                            </label>
                            <select
                                v-model="form.branch_id"
                                class="w-full rounded-md border-gray-300 text-sm"
                            >
                                <option value="">Semua Cabang</option>
                                <option
                                    v-for="b in branches"
                                    :key="b.id"
                                    :value="b.id"
                                >
                                    {{ b.code }} - {{ b.name }}
                                </option>
                            </select>
                        </div>

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

                <!-- Ringkasan -->
                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="text-sm text-gray-600">Total Kerugian</div>
                    <div class="text-2xl font-semibold text-red-600">
                        {{ formatMoney(totalLoss) }}
                    </div>
                </div>

                <!-- Tabel -->
                <div class="overflow-x-auto rounded-lg bg-white shadow">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-4 py-2 text-left font-medium text-gray-600"
                                >
                                    No. Transfer
                                </th>
                                <th
                                    class="px-4 py-2 text-left font-medium text-gray-600"
                                >
                                    Tanggal
                                </th>
                                <th
                                    class="px-4 py-2 text-left font-medium text-gray-600"
                                >
                                    Cabang Asal
                                </th>
                                <th
                                    class="px-4 py-2 text-left font-medium text-gray-600"
                                >
                                    Cabang Tujuan
                                </th>
                                <th
                                    class="px-4 py-2 text-left font-medium text-gray-600"
                                >
                                    Barang
                                </th>
                                <th
                                    class="px-4 py-2 text-right font-medium text-gray-600"
                                >
                                    Qty Kirim
                                </th>
                                <th
                                    class="px-4 py-2 text-right font-medium text-gray-600"
                                >
                                    Qty Terima
                                </th>
                                <th
                                    class="px-4 py-2 text-right font-medium text-gray-600"
                                >
                                    Qty Hilang
                                </th>
                                <th
                                    class="px-4 py-2 text-right font-medium text-gray-600"
                                >
                                    Harga Satuan
                                </th>
                                <th
                                    class="px-4 py-2 text-right font-medium text-gray-600"
                                >
                                    Kerugian
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-if="rows.length === 0">
                                <td
                                    colspan="10"
                                    class="px-4 py-6 text-center text-gray-400"
                                >
                                    Tidak ada kerugian transfer pada rentang
                                    ini.
                                </td>
                            </tr>
                            <tr
                                v-for="r in rows"
                                :key="`${r.transfer_id}-${r.item_code}`"
                                class="hover:bg-gray-50"
                            >
                                <td class="px-4 py-2 font-medium text-gray-800">
                                    {{ r.transfer_no }}
                                </td>
                                <td class="px-4 py-2 text-gray-500">
                                    {{ formatDate(r.transfer_date) }}
                                </td>
                                <td class="px-4 py-2">
                                    {{ r.from_branch_code }}
                                </td>
                                <td class="px-4 py-2">
                                    {{ r.to_branch_code }}
                                </td>
                                <td class="px-4 py-2">
                                    {{ r.item_code }} - {{ r.item_name }}
                                </td>
                                <td class="px-4 py-2 text-right">
                                    {{ formatQty(r.qty_sent) }}
                                </td>
                                <td class="px-4 py-2 text-right">
                                    {{ formatQty(r.qty_received) }}
                                </td>
                                <td class="px-4 py-2 text-right text-red-600">
                                    {{ formatQty(r.lost_qty) }}
                                </td>
                                <td class="px-4 py-2 text-right">
                                    {{ formatMoney(r.unit_cost) }}
                                </td>
                                <td
                                    class="px-4 py-2 text-right font-medium text-red-600"
                                >
                                    {{ formatMoney(r.loss_amount) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
