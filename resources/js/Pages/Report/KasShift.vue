<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, router } from "@inertiajs/vue3";
import { reactive } from "vue";

const props = defineProps({
    rows: Array,
    branches: Array,
    filters: Object,
    isOwner: Boolean,
});

const form = reactive({
    branch_id: props.filters.branch_id ?? "",
    status: props.filters.status ?? "",
    date_from: props.filters.date_from ?? "",
    date_to: props.filters.date_to ?? "",
});

function applyFilter() {
    router.get(route("reports.kas-shift"), form, {
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

function formatDateTime(v) {
    if (!v) return "-";
    return new Date(v.replace(" ", "T")).toLocaleString("id-ID", {
        dateStyle: "medium",
        timeStyle: "short",
    });
}
</script>

<template>
    <Head title="Laporan Kas Shift" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Laporan Kas Shift
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
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
                                Status
                            </label>
                            <select
                                v-model="form.status"
                                class="w-full rounded-md border-gray-300 text-sm"
                            >
                                <option value="">Semua</option>
                                <option value="open">Open</option>
                                <option value="closed">Closed</option>
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

                <!-- Tabel -->
                <div class="overflow-x-auto rounded-lg bg-white shadow">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    v-if="isOwner"
                                    class="px-4 py-2 text-left font-medium text-gray-600"
                                >
                                    Cabang
                                </th>
                                <th
                                    class="px-4 py-2 text-left font-medium text-gray-600"
                                >
                                    Dibuka Oleh
                                </th>
                                <th
                                    class="px-4 py-2 text-left font-medium text-gray-600"
                                >
                                    Ditutup Oleh
                                </th>
                                <th
                                    class="px-4 py-2 text-right font-medium text-gray-600"
                                >
                                    Saldo Awal
                                </th>
                                <th
                                    class="px-4 py-2 text-right font-medium text-gray-600"
                                >
                                    Saldo Akhir
                                </th>
                                <th
                                    class="px-4 py-2 text-right font-medium text-gray-600"
                                >
                                    Saldo Diharapkan
                                </th>
                                <th
                                    class="px-4 py-2 text-right font-medium text-gray-600"
                                >
                                    Selisih
                                </th>
                                <th
                                    class="px-4 py-2 text-left font-medium text-gray-600"
                                >
                                    Status
                                </th>
                                <th
                                    class="px-4 py-2 text-left font-medium text-gray-600"
                                >
                                    Dibuka
                                </th>
                                <th
                                    class="px-4 py-2 text-left font-medium text-gray-600"
                                >
                                    Ditutup
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-if="rows.length === 0">
                                <td
                                    :colspan="isOwner ? 10 : 9"
                                    class="px-4 py-6 text-center text-gray-400"
                                >
                                    Tidak ada data shift.
                                </td>
                            </tr>
                            <tr
                                v-for="r in rows"
                                :key="r.id"
                                class="hover:bg-gray-50"
                            >
                                <td v-if="isOwner" class="px-4 py-2">
                                    {{ r.branch_code }}
                                </td>
                                <td class="px-4 py-2">
                                    {{ r.opened_by_name }}
                                </td>
                                <td class="px-4 py-2">
                                    {{ r.closed_by_name ?? "-" }}
                                </td>
                                <td class="px-4 py-2 text-right">
                                    {{ formatMoney(r.opening_balance) }}
                                </td>
                                <td class="px-4 py-2 text-right">
                                    {{ formatMoney(r.closing_balance) }}
                                </td>
                                <td class="px-4 py-2 text-right">
                                    {{ formatMoney(r.expected_balance) }}
                                </td>
                                <td
                                    class="px-4 py-2 text-right"
                                    :class="{
                                        'text-red-600':
                                            r.difference !== null &&
                                            Number(r.difference) !== 0,
                                        'text-green-600':
                                            r.difference !== null &&
                                            Number(r.difference) === 0,
                                    }"
                                >
                                    {{ formatMoney(r.difference) }}
                                </td>
                                <td class="px-4 py-2">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="
                                            r.status === 'open'
                                                ? 'bg-yellow-100 text-yellow-800'
                                                : 'bg-gray-100 text-gray-600'
                                        "
                                    >
                                        {{ r.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-gray-500">
                                    {{ formatDateTime(r.opened_at) }}
                                </td>
                                <td class="px-4 py-2 text-gray-500">
                                    {{ formatDateTime(r.closed_at) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>