<script setup>
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import ThemeToggle from "@/Components/ThemeToggle.vue";
import {
    LayoutDashboard,
    Tag,
    Users,
    Package,
    PackagePlus,
    ArrowRightLeft,
    Wallet,
    ShoppingCart,
    HandCoins,
    BarChart3,
    Building2,
    Search,
    X,
    Check,
    ChevronDown,
    Menu,
} from "lucide-vue-next";

const page = usePage();

const user = computed(() => page.props.auth.user);
const role = computed(() => user.value.role);
const abilities = computed(() => page.props.auth.abilities ?? {});
const activeBranch = computed(() => page.props.auth.active_branch ?? null);

/* Pemilih cabang aktif (khusus Owner) -- card dropdown di topbar. */
const branchOptions = computed(() => page.props.auth.branches ?? []);
const branchPickerOpen = ref(false);
const switchingBranch = ref(false);

function pickBranch(branch) {
    if (switchingBranch.value) return;
    if (activeBranch.value && activeBranch.value.id === branch.id) {
        branchPickerOpen.value = false;
        return;
    }
    router.patch(
        route("active-branch.update"),
        { branch_id: branch.id },
        {
            preserveScroll: true,
            onStart: () => (switchingBranch.value = true),
            onFinish: () => {
                switchingBranch.value = false;
                branchPickerOpen.value = false;
            },
        },
    );
}

function can(ability) {
    return !!abilities.value[ability];
}

/* ---------------------------------------------------------------------- *
 * Navigasi sidebar -- kondisi role/ability sama persis seperti versi
 * sebelumnya, cuma gaya visualnya yang berubah jadi sidebar terang.
 * ---------------------------------------------------------------------- */
const navItems = computed(() => [
    { key: "dashboard", label: "Dashboard", icon: LayoutDashboard, href: route("dashboard"), active: route().current("dashboard"), show: true },
    { key: "categories", label: "Kategori", icon: Tag, href: route("categories.index"), active: route().current("categories.index"), show: true },
    { key: "customers", label: "Pelanggan", icon: Users, href: route("customers.index"), active: route().current("customers.index"), show: true },
    { key: "items", label: "Barang", icon: Package, href: route("items.index"), active: route().current("items.index"), show: true },
    { key: "stock-adjustments", label: "Barang Masuk", icon: PackagePlus, href: route("stock-adjustments.index"), active: route().current("stock-adjustments.index"), show: role.value !== "cashier" },
    { key: "transfers", label: "Transfer Cabang", icon: ArrowRightLeft, href: route("transfers.index"), active: route().current("transfers.index"), show: role.value !== "cashier" },
    { key: "cashier-shift", label: "Shift Kasir", icon: Wallet, href: route("cashier-shift.index"), active: route().current("cashier-shift.index"), show: true },
    { key: "sales", label: "POS Penjualan", icon: ShoppingCart, href: route("sales.index"), active: route().current("sales.index"), show: can("sales.create") },
    { key: "receivables", label: "Piutang", icon: HandCoins, href: route("receivables.index"), active: route().current("receivables.index"), show: can("notes.manage") },
].filter((item) => item.show));

/* Grup Laporan -- accordion di sidebar terang. */
const reportLinks = computed(() =>
    [
        { label: "Laporan Stok", href: route("reports.stock"), show: true },
        { label: "Kartu Stok", href: route("reports.stock-card"), show: true },
        { label: "Kas Shift", href: route("reports.kas-shift"), show: true },
        { label: "Penjualan Kredit", href: route("reports.sales-credit"), show: can("notes.manage") },
        { label: "Laba Kotor", href: route("reports.gross-profit"), show: can("report.view_margin") },
        { label: "Kerugian Transfer", href: route("reports.written-off"), show: can("notes.manage") },
        { label: "Konsolidasi Semua Cabang", href: route("reports.consolidation"), show: role.value === "owner" },
    ].filter((l) => l.show),
);
const reportsActive = computed(() => route().current("reports.*"));
const reportsOpen = ref(reportsActive.value);

/* ---------------------------------------------------------------------- *
 * Search cepat navbar -- fetch ke /items/quick-search, di-debounce 300ms.
 * ---------------------------------------------------------------------- */
const searchQuery = ref("");
const searchResults = ref([]);
const searchOpen = ref(false);
const searchLoading = ref(false);
let debounceTimer = null;

async function runSearch(q) {
    searchLoading.value = true;
    try {
        const res = await fetch(`/items/quick-search?q=${encodeURIComponent(q)}`, {
            headers: { Accept: "application/json" },
        });
        const data = await res.json();
        searchResults.value = data.items ?? [];
    } catch (e) {
        searchResults.value = [];
    } finally {
        searchLoading.value = false;
    }
}

watch(searchQuery, (q) => {
    clearTimeout(debounceTimer);
    const query = q.trim();

    if (!query) {
        searchResults.value = [];
        searchOpen.value = false;
        return;
    }

    searchOpen.value = true;
    debounceTimer = setTimeout(() => runSearch(query), 300);
});

onBeforeUnmount(() => clearTimeout(debounceTimer));

function clearSearch() {
    searchQuery.value = "";
    searchResults.value = [];
    searchOpen.value = false;
}

function goToItem(item) {
    searchOpen.value = false;
    router.get(route("items.index", { q: item.code }));
}

function formatMoney(v) {
    return new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", minimumFractionDigits: 0 }).format(v ?? 0);
}

const mobileNavOpen = ref(false);

/* Flash toast -- tidak berubah dari versi sebelumnya. */
const toasts = ref([]);
let toastSeq = 0;

function pushToast(variant, message) {
    const id = ++toastSeq;
    toasts.value.push({ id, variant, message });
    if (toasts.value.length > 3) toasts.value.shift();
    setTimeout(() => dismissToast(id), 4000);
}

function dismissToast(id) {
    toasts.value = toasts.value.filter((t) => t.id !== id);
}

watch(
    () => page.props.flash,
    (flash) => {
        if (!flash) return;
        if (flash.success) pushToast("success", flash.success);
        if (flash.error) pushToast("error", flash.error);
        if (flash.warning) pushToast("warning", flash.warning);
    },
    { immediate: true, deep: true },
);

const toastClasses = {
    success: "bg-status-success-soft text-status-success border-status-success",
    error: "bg-status-danger-soft text-status-danger border-status-danger",
    warning: "bg-status-warning-soft text-status-warning border-status-warning",
};
</script>

<template>
    <div class="min-h-screen bg-page font-sans text-text-primary">
        <!-- Flash toast -->
        <div class="fixed right-4 top-4 z-50 flex w-80 flex-col gap-2">
            <div
                v-for="t in toasts"
                :key="t.id"
                class="flex items-start justify-between gap-3 rounded-xl border px-4 py-3 text-sm font-medium shadow-md"
                :class="toastClasses[t.variant]"
            >
                <span>{{ t.message }}</span>
                <button type="button" class="opacity-60 hover:opacity-100" @click="dismissToast(t.id)">✕</button>
            </div>
        </div>

        <div class="flex min-h-screen">
            <!-- ============ Sidebar terang (desktop) ============ -->
            <aside class="sticky top-0 hidden h-screen w-64 shrink-0 flex-col border-r border-brand-primary/20 bg-brand-dark backdrop-blur-xl lg:flex">
                <Link :href="route('dashboard')" class="flex h-20 shrink-0 items-center gap-3 border-b border-brand-primary/20 bg-brand-primary/10 px-5">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-primary text-xs font-bold text-white shadow-sm shadow-brand-primary/30">
                        TB
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-bold leading-tight text-white">TB Sumber Baru</span>
                        <span class="mt-1 block text-xs text-white/70">Sistem Operasional</span>
                    </span>
                </Link>

                <nav class="flex min-h-0 flex-1 flex-col gap-1.5 overflow-y-auto overscroll-contain px-3 py-4">
                    <Link
                        v-for="item in navItems"
                        :key="item.key"
                        :href="item.href"
                        class="group relative flex h-12 items-center gap-3.5 rounded-xl px-3.5 text-sm font-medium transition-all duration-200 ease-out"
                        :class="item.active
                            ? 'border border-brand-primary/45 bg-brand-primary/25 text-white shadow-sm shadow-black/15 backdrop-blur-md'
                            : 'border border-transparent text-white/80 hover:bg-white/10 hover:text-white'"
                    >
                        <span v-if="item.active" class="absolute bottom-3 left-0 top-3 w-1 rounded-full bg-brand-primary" />
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center">
                            <component :is="item.icon" :size="20" :stroke-width="1.8" :class="item.active ? 'text-brand-primary' : 'text-white/75 group-hover:text-brand-primary'" />
                        </span>
                        <span class="truncate">{{ item.label }}</span>
                    </Link>

                    <!-- Laporan: accordion -->
                    <div>
                        <button
                            type="button"
                            class="group relative flex h-12 w-full items-center gap-3.5 rounded-xl px-3.5 text-sm font-medium transition-all duration-200 ease-out"
                            :class="reportsActive
                                ? 'border border-brand-primary/45 bg-brand-primary/25 text-white shadow-sm shadow-black/15 backdrop-blur-md'
                                : 'border border-transparent text-white/80 hover:bg-white/10 hover:text-white'"
                            @click="reportsOpen = !reportsOpen"
                        >
                            <span v-if="reportsActive" class="absolute bottom-3 left-0 top-3 w-1 rounded-full bg-brand-primary" />
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center">
                                <BarChart3 :size="20" :stroke-width="1.8" :class="reportsActive ? 'text-brand-primary' : 'text-white/75 group-hover:text-brand-primary'" />
                            </span>
                            <span class="flex-1 truncate text-left">Laporan</span>
                            <ChevronDown
                                :size="15"
                                class="shrink-0 text-white/60 transition-transform"
                                :class="reportsOpen ? 'rotate-180' : ''"
                            />
                        </button>

                        <div v-if="reportsOpen" class="ml-3.75 mt-0.5 flex flex-col gap-0.5 border-l border-white/20 pl-4">
                            <Link
                                v-for="l in reportLinks"
                                :key="l.href"
                                :href="l.href"
                                class="rounded-lg px-3 py-2 text-sm text-white/75 transition-colors duration-200 hover:bg-white/10 hover:text-white"
                            >
                                {{ l.label }}
                            </Link>
                        </div>
                    </div>
                </nav>

            </aside>

            <!-- ============ Drawer sidebar (mobile) ============ -->
            <div v-if="mobileNavOpen" class="fixed inset-0 z-40 lg:hidden">
                <div class="absolute inset-0 bg-black/40" @click="mobileNavOpen = false" />
                <aside class="absolute left-0 top-0 flex h-full w-64 flex-col gap-1 bg-surface p-4">
                    <div class="mb-4 flex items-center justify-between">
                        <span class="text-sm font-bold text-text-primary">TB Sumber Baru</span>
                        <button type="button" class="text-text-secondary" @click="mobileNavOpen = false"><X :size="18" /></button>
                    </div>
                    <Link
                        v-for="item in navItems"
                        :key="item.key"
                        :href="item.href"
                        class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium"
                        :class="item.active ? 'bg-brand-primary-soft text-brand-primary' : 'text-text-secondary hover:bg-surface-muted'"
                        @click="mobileNavOpen = false"
                    >
                        <component :is="item.icon" :size="17" /> {{ item.label }}
                    </Link>
                    <div class="mt-2 border-t border-border pt-2 text-xs font-semibold text-text-disabled">Laporan</div>
                    <Link
                        v-for="l in reportLinks"
                        :key="l.href"
                        :href="l.href"
                        class="rounded-xl px-3 py-2 text-sm text-text-secondary hover:bg-surface-muted"
                        @click="mobileNavOpen = false"
                    >
                        {{ l.label }}
                    </Link>
                </aside>
            </div>

            <!-- ============ Main column ============ -->
            <div class="flex min-h-screen flex-1 flex-col">
                <!-- Topbar -->
                <div class="sticky top-0 z-30 flex min-h-20 flex-wrap items-center gap-x-2 gap-y-2 border-b border-border bg-surface/95 px-3 py-2 shadow-md shadow-brand-dark/5 backdrop-blur lg:h-20 lg:flex-nowrap lg:gap-5 lg:px-6 lg:py-0">
                    <button type="button" class="order-1 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-text-secondary transition hover:bg-surface-muted hover:text-brand-primary lg:order-none lg:hidden" aria-label="Buka navigasi" @click="mobileNavOpen = true">
                        <Menu :size="20" />
                    </button>

                    <!-- Live search -->
                    <div class="relative order-3 w-full max-w-none lg:order-none lg:w-full lg:max-w-xl">
                        <div class="flex min-h-11 items-center gap-2.5 rounded-xl border border-border bg-page/75 px-3.5 py-2 transition focus-within:border-brand-primary focus-within:bg-surface focus-within:ring-2 focus-within:ring-brand-primary-soft">
                            <Search :size="15" class="shrink-0 text-text-disabled" />
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Cari nama atau kode barang..."
                                class="w-full border-0 bg-transparent p-0 text-sm text-text-primary placeholder:text-text-disabled focus:outline-none focus:ring-0"
                                @focus="searchQuery && (searchOpen = true)"
                            />
                            <button v-if="searchQuery" type="button" class="text-text-disabled hover:text-text-secondary" @click="clearSearch">
                                <X :size="14" />
                            </button>
                        </div>

                        <div
                            v-if="searchOpen"
                            class="absolute left-0 right-0 top-[calc(100%+6px)] z-30 max-h-80 overflow-y-auto rounded-xl border border-border bg-surface shadow-xl"
                        >
                            <div v-if="searchLoading" class="px-4 py-3 text-xs text-text-secondary">Mencari...</div>
                            <div v-else-if="searchResults.length === 0" class="px-4 py-6 text-center text-sm text-text-secondary">
                                Tidak ada barang cocok dengan "{{ searchQuery }}"
                            </div>
                            <button
                                v-for="it in searchResults"
                                :key="it.id"
                                type="button"
                                class="flex w-full items-center justify-between gap-3 border-t border-border px-4 py-2.5 text-left first:border-t-0 hover:bg-surface-muted"
                                @click="goToItem(it)"
                            >
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-semibold text-text-primary">{{ it.name }}</div>
                                    <div class="text-xs text-text-secondary">{{ it.code }} · satuan {{ it.unit }}</div>
                                </div>
                                <div class="shrink-0 text-right">
                                    <div class="text-sm font-bold tabular-nums text-brand-secondary">{{ formatMoney(it.sell_price) }}</div>
                                    <div
                                        v-if="it.stock !== null"
                                        class="mt-0.5 inline-block rounded px-1.5 py-0.5 text-[10px] font-semibold"
                                        :class="it.stock <= 5 ? 'bg-status-danger-soft text-status-danger' : 'bg-status-success-soft text-status-success'"
                                    >
                                        {{ it.stock }} {{ it.unit }}
                                    </div>
                                </div>
                            </button>
                        </div>
                    </div>

                    <div class="order-2 ml-auto flex items-center gap-2 lg:order-none lg:gap-3">
                        <ThemeToggle />

                        <!-- Owner: card pemilih cabang aktif -->
                        <div v-if="role === 'owner'" class="relative">
                            <button
                                type="button"
                                class="flex items-center gap-2 rounded-xl border px-3 py-1.5 text-xs transition"
                                :aria-label="activeBranch ? `Cabang aktif: ${activeBranch.name}` : 'Pilih cabang aktif'"
                                :title="activeBranch ? activeBranch.name : 'Pilih cabang aktif'"
                                :class="activeBranch
                                    ? 'border-border bg-page text-text-primary hover:bg-surface-muted'
                                    : 'border-status-warning bg-status-warning-soft text-status-warning'"
                                @click="branchPickerOpen = !branchPickerOpen"
                            >
                                <Building2 :size="14" />
                                <span class="hidden font-semibold sm:inline">{{ activeBranch ? activeBranch.name : 'Pilih cabang aktif' }}</span>
                                <ChevronDown :size="13" class="transition" :class="branchPickerOpen ? 'rotate-180' : ''" />
                            </button>

                            <div v-if="branchPickerOpen" class="fixed inset-0 z-30" @click="branchPickerOpen = false" />

                            <Transition
                                enter-active-class="transition duration-150 ease-out"
                                enter-from-class="translate-y-1 scale-95 opacity-0"
                                enter-to-class="translate-y-0 scale-100 opacity-100"
                                leave-active-class="transition duration-100 ease-in"
                                leave-from-class="opacity-100"
                                leave-to-class="opacity-0"
                            >
                                <div
                                    v-if="branchPickerOpen"
                                    class="absolute right-0 z-40 mt-2 w-72 origin-top-right rounded-2xl border border-border bg-surface p-2 shadow-xl"
                                >
                                    <div class="px-3 pb-2 pt-1.5 text-[11px] font-semibold uppercase tracking-wide text-text-secondary">
                                        Cabang aktif
                                    </div>
                                    <button
                                        v-for="b in branchOptions"
                                        :key="b.id"
                                        type="button"
                                        :disabled="switchingBranch"
                                        class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition disabled:opacity-60"
                                        :class="activeBranch && activeBranch.id === b.id
                                            ? 'bg-brand-primary-soft'
                                            : 'hover:bg-surface-muted'"
                                        @click="pickBranch(b)"
                                    >
                                        <span
                                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                                            :class="activeBranch && activeBranch.id === b.id
                                                ? 'bg-brand-primary text-white'
                                                : 'bg-surface-muted text-text-secondary'"
                                        >
                                            <Building2 :size="16" />
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-semibold text-text-primary">{{ b.name }}</span>
                                            <span class="block text-xs text-text-secondary">{{ b.code }}</span>
                                        </span>
                                        <Check v-if="activeBranch && activeBranch.id === b.id" :size="16" class="text-brand-primary" />
                                    </button>
                                    <p v-if="!branchOptions.length" class="px-3 py-3 text-xs text-text-secondary">
                                        Belum ada cabang aktif.
                                    </p>
                                </div>
                            </Transition>
                        </div>

                        <div v-else-if="activeBranch" class="flex items-center gap-2 rounded-xl border border-border bg-page px-3 py-1.5 text-xs text-text-primary">
                            <Building2 :size="14" />
                            <span class="font-semibold">{{ activeBranch.name }}</span>
                        </div>

                        <div class="relative z-50">
                            <Dropdown align="right" width="48">
                                <template #trigger>
                                    <button
                                        type="button"
                                        class="flex items-center gap-2 rounded-xl border border-border bg-surface px-2 py-1.5 text-left transition hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-brand-primary-soft"
                                        aria-label="Menu profil"
                                    >
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-secondary text-xs font-semibold text-white">
                                            {{ user.name.charAt(0).toUpperCase() }}
                                        </span>
                                        <span class="hidden min-w-0 sm:block">
                                            <span class="block max-w-32 truncate text-xs font-semibold text-text-primary">{{ user.name }}</span>
                                            <span class="block text-[10px] capitalize text-text-disabled">{{ role }}</span>
                                        </span>
                                        <ChevronDown :size="14" class="hidden text-text-disabled sm:block" />
                                    </button>
                                </template>
                                <template #content>
                                    <DropdownLink :href="route('profile.edit')">Profil</DropdownLink>
                                    <DropdownLink :href="route('logout')" method="post" as="button">Keluar</DropdownLink>
                                </template>
                            </Dropdown>
                        </div>
                    </div>
                </div>

                <!-- Page heading -->
                <header v-if="$slots.header" class="border-b border-border bg-surface px-4 py-5 lg:px-6">
                    <slot name="header" />
                </header>

                <main class="flex-1">
                    <slot />
                </main>
            </div>
        </div>

        <div v-if="searchOpen" class="fixed inset-0 z-20" @click="searchOpen = false" />
    </div>
</template>