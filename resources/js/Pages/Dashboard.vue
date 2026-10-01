<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import StatusBadge from "@/Components/StatusBadge.vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import {
    Wallet,
    PackageSearch,
    ClipboardList,
    ArrowRightLeft,
    HandCoins,
    Building2,
    AlertTriangle,
    Clock,
    TrendingUp,
    ArrowUpRight,
    CalendarDays,
} from "lucide-vue-next";
import { computed, ref } from "vue";

const props = defineProps({
    isOwner: Boolean,
    branchName: String,
    activeBranch: Object,
    todaySales: Object,
    lowStockCount: Number,
    shiftStatus: [Object, Array],
    pendingAdjustments: Number,
    pendingTransfers: Number,
    totalReceivable: [String, Number],
    salesTrend: { type: Array, default: () => [] },
});

const page = usePage();
const userName = computed(() => page.props.auth.user.name);
const todayLabel = new Intl.DateTimeFormat("id-ID", {
    weekday: "long",
    day: "numeric",
    month: "long",
    year: "numeric",
}).format(new Date());
const hoveredTrendIndex = ref(null);
const pinnedTrendIndex = ref(null);

function formatMoney(v) {
    if (v === null || v === undefined) return "-";
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
    }).format(v);
}

function formatTime(v) {
    if (!v) return "-";
    return new Date(v.replace(" ", "T")).toLocaleString("id-ID", {
        dateStyle: "medium",
        timeStyle: "short",
    });
}

const trendMaximum = computed(() => Math.max(0, ...props.salesTrend.map((day) => Number(day.total) || 0)));
const trendPoints = computed(() => props.salesTrend.map((day, index) => {
    const amount = Number(day.total) || 0;

    return {
        ...day,
        amount,
        percentage: trendMaximum.value > 0 ? (amount / trendMaximum.value) * 100 : 0,
        weekday: new Intl.DateTimeFormat("id-ID", { weekday: "short" }).format(new Date(`${day.date}T00:00:00`)),
        dayNumber: new Intl.DateTimeFormat("id-ID", { day: "numeric" }).format(new Date(`${day.date}T00:00:00`)),
        index,
    };
}));
const trendTicks = computed(() => [1, 0.75, 0.5, 0.25, 0].map((fraction, index) => ({
    label: trendMaximum.value > 0
        ? formatCompactMoney(trendMaximum.value * fraction)
        : index === 4 ? formatCompactMoney(0) : "",
})));
const activeTrendPoint = computed(() => {
    const index = hoveredTrendIndex.value ?? pinnedTrendIndex.value ?? trendPoints.value.length - 1;
    return trendPoints.value[index] ?? null;
});

function formatCompactMoney(value) {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        notation: "compact",
        maximumFractionDigits: 1,
    }).format(value);
}

function formatTrendDate(value) {
    return new Intl.DateTimeFormat("id-ID", {
        weekday: "long",
        day: "numeric",
        month: "long",
    }).format(new Date(`${value}T00:00:00`));
}

function toggleTrendPoint(index) {
    pinnedTrendIndex.value = pinnedTrendIndex.value === index ? null : index;
}

/* Kartu statistik -- tiap kartu punya warna lingkaran ikon sendiri (token
   accent-* di resources/css/app.css), meniru pola kartu bulat berwarna di
   referensi desain. Kartu yang null/undefined disembunyikan otomatis. */
const statCards = computed(() => {
    const cards = [
        {
            key: "sales",
            icon: Wallet,
            accent: "sky",
            label: "Penjualan Hari Ini",
            value: formatMoney(props.todaySales.total),
            hint: `${props.todaySales.count} nota`,
            href: null,
        },
        {
            key: "stock",
            icon: PackageSearch,
            accent: "amber",
            label: "Stok Perlu Restock",
            value: `${props.lowStockCount} barang`,
            hint: null,
            href: route("reports.stock", { low_stock: 1 }),
            danger: props.lowStockCount > 0,
        },
    ];

    if (props.pendingAdjustments !== null) {
        cards.push({
            key: "adjustments",
            icon: ClipboardList,
            accent: "indigo",
            label: "Adjustment Menunggu Approval",
            value: String(props.pendingAdjustments),
            hint: null,
            href: null,
            warn: props.pendingAdjustments > 0,
        });
    }

    if (props.pendingTransfers !== null) {
        cards.push({
            key: "transfers",
            icon: ArrowRightLeft,
            accent: "emerald",
            label: "Transfer Berjalan",
            value: String(props.pendingTransfers),
            hint: null,
            href: null,
            warn: props.pendingTransfers > 0,
        });
    }

    if (props.totalReceivable !== null) {
        cards.push({
            key: "receivable",
            icon: HandCoins,
            accent: "pink",
            label: "Total Piutang",
            value: formatMoney(props.totalReceivable),
            hint: null,
            href: null,
        });
    }

    return cards;
});

const accentClasses = {
    sky: "bg-accent-sky-soft text-accent-sky",
    amber: "bg-accent-amber-soft text-accent-amber",
    indigo: "bg-accent-indigo-soft text-accent-indigo",
    emerald: "bg-accent-emerald-soft text-accent-emerald",
    pink: "bg-accent-pink-soft text-accent-pink",
};
const accentBorderClasses = {
    sky: "border-t-accent-sky",
    amber: "border-t-accent-amber",
    indigo: "border-t-accent-indigo",
    emerald: "border-t-accent-emerald",
    pink: "border-t-accent-pink",
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="h-11 w-1 shrink-0 rounded-full bg-brand-primary" aria-hidden="true" />
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold text-brand-primary">OPERASIONAL HARIAN</p>
                        <h2 class="mt-0.5 truncate text-xl font-bold text-text-primary">
                            Halo, {{ userName }}
                            <span v-if="!isOwner" class="font-normal text-text-secondary">· {{ branchName }}</span>
                        </h2>
                        <p class="mt-0.5 text-sm text-text-secondary">Ringkasan toko dan aktivitas cabang.</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 rounded-lg border border-border bg-page px-3 py-2 text-sm text-text-secondary">
                    <CalendarDays :size="16" class="text-brand-primary" />
                    <span class="capitalize">{{ todayLabel }}</span>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 lg:px-6">
            <!-- Banner cabang aktif (khusus Owner) -->
            <div
                v-if="isOwner && !activeBranch"
                class="flex items-center gap-3 rounded-2xl border border-status-warning bg-status-warning-soft px-4 py-3 text-sm text-status-warning"
            >
                <AlertTriangle :size="18" class="shrink-0" />
                <span>
                    Belum ada cabang aktif dipilih. Untuk bertransaksi POS atau shift, pilih dulu di menu
                    <Link :href="route('active-branch.edit')" class="font-semibold underline underline-offset-2">
                        Cabang Aktif
                    </Link>.
                </span>
            </div>
            <div
                v-else-if="isOwner"
                class="flex items-center gap-2 rounded-2xl border border-border bg-surface px-4 py-3 text-sm text-text-primary"
            >
                <Building2 :size="16" class="text-brand-primary" />
                Cabang aktif saat ini:
                <span class="font-semibold">{{ activeBranch.code }} &middot; {{ activeBranch.name }}</span>
            </div>

            <div class="flex items-end justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-text-primary">Ringkasan hari ini</h3>
                    <p class="mt-0.5 text-xs text-text-secondary">Angka utama operasional dan tindak lanjut.</p>
                </div>
            </div>

            <!-- Kartu ringkasan operasional -->
            <div
                class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                :class="{
                    'lg:grid-cols-2': statCards.length === 2,
                    'lg:grid-cols-3': statCards.length === 3,
                    'lg:grid-cols-4': statCards.length === 4,
                    'lg:grid-cols-3 xl:grid-cols-5': statCards.length >= 5,
                }"
            >
                <component
                    :is="card.href ? Link : 'div'"
                    v-for="(card, index) in statCards"
                    :key="card.key"
                    :href="card.href ?? undefined"
                    class="dashboard-stat-card dashboard-rise-in rounded-xl border border-border border-t-2 bg-surface p-5 shadow-sm"
                    :style="{ '--stagger-order': index }"
                    :class="[
                        accentBorderClasses[card.accent],
                        card.href
                            ? 'group dashboard-stat-card--hoverable dashboard-stat-card--interactive cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary focus-visible:ring-offset-2'
                            : 'group dashboard-stat-card--hoverable',
                    ]"
                >
                    <div class="flex items-start justify-between">
                        <span
                            class="flex h-11 w-11 items-center justify-center rounded-full transition duration-200"
                            :class="[accentClasses[card.accent], 'group-hover:scale-105 group-hover:shadow-sm']"
                        >
                        <component :is="card.icon" :size="19" />
                        </span>
                        <ArrowUpRight
                            v-if="card.href"
                            :size="17"
                            class="text-text-disabled transition duration-200 group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-brand-primary"
                            aria-hidden="true"
                        />
                    </div>
                    <div class="mt-3 text-sm text-text-secondary">{{ card.label }}</div>
                    <div
                        class="mt-1 text-2xl font-bold tabular-nums text-text-primary"
                        :class="{ 'text-status-danger': card.danger, 'text-status-warning': card.warn }"
                    >
                        {{ card.value }}
                    </div>
                    <div v-if="card.hint" class="mt-1 text-xs text-text-disabled">{{ card.hint }}</div>
                </component>
            </div>

            <!-- Tren penjualan 7 hari -->
            <section class="dashboard-chart-panel rounded-xl border border-border bg-surface p-5 shadow-sm sm:p-6" aria-labelledby="sales-trend-title">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-accent-sky-soft text-accent-sky">
                            <TrendingUp :size="19" />
                        </span>
                        <div>
                            <h3 id="sales-trend-title" class="text-base font-semibold text-text-primary">Tren Penjualan</h3>
                            <p class="mt-0.5 text-xs text-text-secondary">7 hari terakhir</p>
                        </div>
                    </div>
                    <div v-if="activeTrendPoint" class="min-w-36 text-right" aria-live="polite">
                        <p class="text-xs capitalize text-text-secondary">{{ formatTrendDate(activeTrendPoint.date) }}</p>
                        <p class="mt-0.5 text-lg font-bold tabular-nums text-text-primary">{{ formatMoney(activeTrendPoint.amount) }}</p>
                        <p class="text-xs text-text-secondary">{{ activeTrendPoint.count }} nota</p>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-[3.25rem_minmax(0,1fr)] gap-x-3" role="group" aria-label="Grafik penjualan tujuh hari">
                    <div class="relative h-52 pb-8" aria-hidden="true">
                        <div class="absolute inset-x-0 top-0 bottom-8 flex flex-col justify-between text-right text-[11px] tabular-nums text-text-secondary">
                            <span v-for="(tick, index) in trendTicks" :key="index">{{ tick.label }}</span>
                        </div>
                    </div>

                    <div class="relative h-52">
                        <div class="absolute inset-x-0 top-0 bottom-8 flex flex-col justify-between" aria-hidden="true">
                            <span v-for="(tick, index) in trendTicks" :key="index" class="border-t border-dashed border-border" />
                        </div>

                        <div class="absolute inset-x-0 top-0 bottom-8 grid grid-cols-7 gap-1 sm:gap-3">
                            <div v-for="point in trendPoints" :key="point.date" class="relative min-w-0">
                                <button
                                    type="button"
                                    class="group absolute inset-0 flex w-full items-end justify-center rounded-md transition-colors hover:bg-brand-primary-soft/50 focus-visible:bg-brand-primary-soft/50"
                                    :aria-label="`${formatTrendDate(point.date)}: ${formatMoney(point.amount)}, ${point.count} nota`"
                                    :aria-pressed="pinnedTrendIndex === point.index"
                                    :title="`${formatTrendDate(point.date)} · ${formatMoney(point.amount)} · ${point.count} nota`"
                                    @mouseenter="hoveredTrendIndex = point.index"
                                    @mouseleave="hoveredTrendIndex = null"
                                    @focus="hoveredTrendIndex = point.index"
                                    @blur="hoveredTrendIndex = null"
                                    @click="toggleTrendPoint(point.index)"
                                    @keydown.enter.prevent="toggleTrendPoint(point.index)"
                                    @keydown.space.prevent="toggleTrendPoint(point.index)"
                                >
                                    <span
                                        v-if="point.amount > 0"
                                        class="sales-chart-bar absolute bottom-0 w-1/2 max-w-9 rounded-t-md bg-brand-primary/80 group-hover:bg-brand-primary"
                                        :style="{ height: `${Math.max(point.percentage, 3)}%`, '--bar-order': point.index }"
                                    />
                                    <span
                                        v-else
                                        class="absolute bottom-0 h-2 w-2 rounded-full border-2 border-brand-primary bg-surface"
                                    />
                                </button>
                            </div>
                        </div>

                        <div class="absolute inset-x-0 bottom-0 grid h-7 grid-cols-7 gap-1 text-center text-[11px] text-text-secondary sm:gap-3 sm:text-xs" aria-hidden="true">
                            <div v-for="point in trendPoints" :key="point.date" class="flex flex-col leading-tight">
                                <span class="capitalize">{{ point.weekday }}</span>
                                <span>{{ point.dayNumber }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="mt-2 text-center text-[11px] text-text-disabled">Arahkan atau pilih batang untuk melihat detail harian.</p>
            </section>

            <!-- Status shift -->
            <div class="rounded-xl border border-border bg-surface p-5 shadow-sm">
                <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-text-primary">
                    <Clock :size="15" />
                    Status Shift Kasir
                </div>

                <template v-if="isOwner">
                    <div v-if="shiftStatus.length === 0" class="text-sm text-text-disabled">
                        Tidak ada shift yang sedang open di cabang mana pun.
                    </div>
                    <ul v-else class="divide-y divide-border">
                        <li
                            v-for="s in shiftStatus"
                            :key="s.branch_id"
                            class="flex items-center justify-between py-2 text-sm"
                        >
                            <span class="text-text-primary">
                                <span class="font-semibold">{{ s.branch_code }} &middot; {{ s.branch_name }}</span>
                                <span class="text-text-secondary"> &mdash; dibuka {{ formatTime(s.opened_at) }}</span>
                            </span>
                            <StatusBadge status="open" />
                        </li>
                    </ul>
                </template>

                <template v-else>
                    <div v-if="!shiftStatus" class="flex items-center justify-between text-sm">
                        <span class="text-text-secondary">Belum ada shift open di cabang ini.</span>
                        <Link
                            :href="route('cashier-shift.index')"
                            class="rounded-xl bg-brand-primary px-3 py-1.5 text-xs font-semibold text-white transition hover:opacity-90"
                        >
                            Buka Shift
                        </Link>
                    </div>
                    <div v-else class="flex items-center justify-between text-sm">
                        <span class="text-text-primary">Shift open sejak {{ formatTime(shiftStatus.opened_at) }}</span>
                        <StatusBadge status="open" />
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>