<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, router } from "@inertiajs/vue3";
import { reactive } from "vue";

const props = defineProps({
    rows: Array,
    balances: Array,
    branches: Array,
    customers: Array,
    filters: Object,
    isOwner: Boolean,
});

const form = reactive({
    branch_id: props.filters.branch_id ?? "",
    customer_id: props.filters.customer_id ?? "",
    date_from: props.filters.date_from ?? "",
    date_to: props.filters.date_to ?? "",
});

function applyFilter() {
    router.get(route("reports.sales-credit"), form, {
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

function formatDate(v) {
    if (!v) return "-";
    return new Date(v.replace(" ", "T")).toLocaleString("id-ID", {
        dateStyle: "medium",
        timeStyle: "short",
    });
}
</script>

<template>
    <Head title="Laporan Penjualan Kredit" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Laporan Penjualan Kredit
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <!-- Filter -->
                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div v-if="isOwner">
                            <label class="mb-1 block text-sm text-gray-600">
                                Cabang
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
                                Pelanggan
                            </label>
                            <select
                                v-model="form.customer_id"
                                class="w-full rounded-md border-gray-300 text-sm"
                            >
                                <option value="">Semua Pelanggan</option>
                                <option
                                    v-for="c in customers"
                                    :key="c.id"
                                    :value="c.id"
                                >
                                    {{ c.name }}
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

                <!-- Ringkasan Saldo Piutang -->
                <div class="overflow-x-auto rounded-lg bg-white shadow">
                    <div class="border-b px-4 py-3 font-medium text-gray-700">
                        Ringkasan Saldo Piutang per Pelanggan
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">
                                    Pelanggan
                                </th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">
                                    Total Kredit
                                </th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">
                                    Total Dibayar
                                </th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">
                                    Saldo
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-if="balances.length === 0">
                                <td colspan="4" class="px-4 py-6 text-center text-gray-400">
                                    Tidak ada piutang.
                                </td>
                            </tr>
                            <tr v-for="b in balances" :key="b.customer_id" class="hover:bg-gray-50">
                                <td class="px-4 py-2">{{ b.customer_name }}</td>
                                <td class="px-4 py-2 text-right">{{ formatMoney(b.total_credit) }}</td>
                                <td class="px-4 py-2 text-right">{{ formatMoney(b.total_paid) }}</td>
                                <td
                                    class="px-4 py-2 text-right font-medium"
                                    :class="Number(b.balance) > 0 ? 'text-red-600' : 'text-green-600'"
                                >
                                    {{ formatMoney(b.balance) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Daftar Transaksi Kredit -->
                <div class="overflow-x-auto rounded-lg bg-white shadow">
                    <div class="border-b px-4 py-3 font-medium text-gray-700">
                        Daftar Transaksi Kredit
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">
                                    Invoice
                                </th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">
                                    Tanggal
                                </th>
                                <th v-if="isOwner" class="px-4 py-2 text-left font-medium text-gray-600">
                                    Cabang
                                </th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">
                                    Pelanggan
                                </th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">
                                    Dibuat Oleh
                                </th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">
                                    Jumlah Kredit
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-if="rows.length === 0">
                                <td :colspan="isOwner ? 6 : 5" class="px-4 py-6 text-center text-gray-400">
                                    Tidak ada transaksi kredit.
                                </td>
                            </tr>
                            <tr v-for="r in rows" :key="r.sale_id" class="hover:bg-gray-50">
                                <td class="px-4 py-2">{{ r.invoice_no }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ formatDate(r.sale_date) }}</td>
                                <td v-if="isOwner" class="px-4 py-2">{{ r.branch_code }}</td>
                                <td class="px-4 py-2">{{ r.customer_name }}</td>
                                <td class="px-4 py-2">{{ r.created_by_name }}</td>
                                <td class="px-4 py-2 text-right">{{ formatMoney(r.amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>