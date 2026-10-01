<script setup>
import { computed, ref, watch } from "vue";
import { Link, usePage, router } from "@inertiajs/vue3";

const page = usePage();

const user = computed(() => page.props.auth?.user ?? null);
const role = computed(() => user.value?.role ?? null);
const isOwner = computed(() => role.value === "owner");
const activeBranch = computed(() => page.props.activeBranch ?? null);
const abilities = computed(() => page.props.abilities ?? {});

function can(ability) {
    return !!abilities.value[ability];
}

// Nama cabang yang tampil di navbar: Owner pakai activeBranch, role lain pakai branch sendiri
const branchLabel = computed(() => {
    if (isOwner.value) {
        return activeBranch.value?.name ?? null;
    }
    return user.value?.branch?.name ?? null;
});

// Menu utama — beberapa item dibatasi ability, bukan cuma disembunyikan CSS
// (props abilities memang tidak dikirim server kalau role tidak berhak, jadi v-if di sini aman)
const navItems = computed(() =>
    [
        { label: "Dashboard", route: "/dashboard", show: true },
        { label: "Cabang Aktif", route: "/active-branch", show: isOwner.value },
        {
            label: "Kategori",
            route: "/categories",
            show: can("category.manage") || isOwner.value,
        },
        { label: "Pelanggan", route: "/customers", show: true },
        { label: "Barang", route: "/items", show: role.value !== "cashier" },
        {
            label: "Barang Masuk",
            route: "/stock-adjustments",
            show: role.value !== "cashier",
        },
        {
            label: "Transfer",
            route: "/transfers",
            show: role.value !== "cashier",
        },
        { label: "Shift Kasir", route: "/cashier-shifts", show: true },
        { label: "POS", route: "/pos", show: true },
        {
            label: "Piutang",
            route: "/receivables",
            show: role.value !== "cashier",
        },
    ].filter((item) => item.show),
);

const reportItems = [
    { label: "Laba Kotor", route: "/reports/gross-profit" },
    { label: "Piutang", route: "/reports/receivables" },
    { label: "Barang Cepat/Lambat", route: "/reports/item-velocity" },
    { label: "Kas per Shift", route: "/reports/cashier-shifts" },
    { label: "Kerugian Transfer", route: "/reports/transfer-losses" },
    { label: "Konsolidasi 2 Cabang", route: "/reports/consolidated" },
];

const showReportsMenu = ref(false);
const showAccountMenu = ref(false);

function logout() {
    router.post("/logout");
}

// Banner persisten: Owner belum pilih cabang aktif (§6.2, §7.2 PRD Frontend)
const showNoBranchBanner = computed(() => isOwner.value && !activeBranch.value);

// Flash toast (§6.7 Design System) — auto-dismiss 4 detik, maksimal 3 tumpuk
const toasts = ref([]);
let toastSeq = 0;

function pushToast(variant, message) {
    const id = ++toastSeq;
    toasts.value.push({ id, variant, message });
    if (toasts.value.length > 3) {
        toasts.value.shift();
    }
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
    <div class="min-h-screen bg-page text-text-primary flex flex-col">
        <!-- Navbar -->
        <header class="bg-surface border-b border-border">
            <div
                class="max-w-[1440px] mx-auto px-6 h-16 flex items-center justify-between gap-6"
            >
                <div class="flex items-center gap-8 min-w-0">
                    <Link
                        href="/dashboard"
                        class="font-bold text-lg text-brand-secondary shrink-0"
                    >
                        TB Sumber Baru
                    </Link>
                    <nav
                        class="hidden lg:flex items-center gap-1 overflow-x-auto"
                    >
                        <Link
                            v-for="item in navItems"
                            :key="item.route"
                            :href="item.route"
                            class="px-3 py-2 rounded-md text-sm font-semibold text-text-secondary hover:bg-surface-muted hover:text-text-primary whitespace-nowrap"
                            :class="{
                                'bg-brand-primary-soft text-brand-secondary':
                                    page.url.startsWith(item.route),
                            }"
                        >
                            {{ item.label }}
                        </Link>

                        <!-- Dropdown Laporan -->
                        <div class="relative">
                            <button
                                type="button"
                                class="px-3 py-2 rounded-md text-sm font-semibold text-text-secondary hover:bg-surface-muted hover:text-text-primary whitespace-nowrap"
                                @click="showReportsMenu = !showReportsMenu"
                            >
                                Laporan ▾
                            </button>
                            <div
                                v-if="showReportsMenu"
                                class="absolute left-0 mt-1 w-56 bg-surface border border-border rounded-md shadow-lg py-1 z-20"
                                @mouseleave="showReportsMenu = false"
                            >
                                <Link
                                    v-for="r in reportItems"
                                    :key="r.route"
                                    :href="r.route"
                                    class="block px-4 py-2 text-sm text-text-primary hover:bg-surface-muted"
                                    @click="showReportsMenu = false"
                                >
                                    {{ r.label }}
                                </Link>
                            </div>
                        </div>
                    </nav>
                </div>

                <!-- Info user + role + cabang aktif -->
                <div class="relative flex items-center gap-3 shrink-0">
                    <div
                        class="hidden sm:flex flex-col items-end leading-tight"
                    >
                        <span class="text-sm font-semibold text-text-primary">{{
                            user?.name
                        }}</span>
                        <span class="text-xs text-text-secondary">
                            {{ role
                            }}<template v-if="branchLabel">
                                · {{ branchLabel }}</template
                            >
                        </span>
                    </div>
                    <button
                        type="button"
                        class="w-9 h-9 rounded-full bg-brand-primary-soft text-brand-secondary font-semibold flex items-center justify-center"
                        @click="showAccountMenu = !showAccountMenu"
                    >
                        {{ user?.name?.charAt(0)?.toUpperCase() }}
                    </button>
                    <div
                        v-if="showAccountMenu"
                        class="absolute right-0 top-12 w-48 bg-surface border border-border rounded-md shadow-lg py-1 z-20"
                        @mouseleave="showAccountMenu = false"
                    >
                        <Link
                            href="/profile"
                            class="block px-4 py-2 text-sm text-text-primary hover:bg-surface-muted"
                        >
                            Profil
                        </Link>
                        <button
                            type="button"
                            class="w-full text-left px-4 py-2 text-sm text-status-danger hover:bg-surface-muted"
                            @click="logout"
                        >
                            Logout
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Banner persisten: Owner belum pilih cabang aktif -->
        <div
            v-if="showNoBranchBanner"
            class="bg-status-warning-soft text-status-warning border-b border-status-warning px-6 py-2 text-sm font-semibold text-center"
        >
            Pilih cabang aktif dulu untuk mengakses POS, Shift Kasir, dan
            laporan per cabang.
            <Link href="/active-branch" class="underline">Pilih sekarang</Link>
        </div>

        <!-- Flash toast -->
        <div class="fixed top-4 right-4 z-50 flex flex-col gap-2 w-80">
            <div
                v-for="t in toasts"
                :key="t.id"
                class="border rounded-md px-4 py-3 text-sm font-medium shadow-md flex items-start justify-between gap-3"
                :class="toastClasses[t.variant]"
            >
                <span>{{ t.message }}</span>
                <button
                    type="button"
                    class="opacity-60 hover:opacity-100"
                    @click="dismissToast(t.id)"
                >
                    ✕
                </button>
            </div>
        </div>

        <!-- Konten halaman -->
        <main class="flex-1 max-w-[1440px] w-full mx-auto px-6 py-6">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-xl font-bold text-text-primary">
                    <slot name="title" />
                </h1>
                <div>
                    <slot name="actions" />
                </div>
            </div>
            <slot />
        </main>
    </div>
</template>
