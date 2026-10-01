<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Plus, RotateCcw, Search } from 'lucide-vue-next';

const props = defineProps({
    items: Object,
    filters: Object,
    categories: Array,
    canManage: Boolean,
    stockBranchAvailable: Boolean,
});

const page = usePage();

const selectClass =
    'mt-1 block w-full rounded-lg border-border bg-surface text-sm text-text-primary shadow-sm focus:border-brand-primary focus:ring-brand-primary';

const rupiah = (value) => 'Rp ' + Number(value ?? 0).toLocaleString('id-ID');
const quantity = (value) => Number(value ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 4 });
const isLowStock = (item) => Number(item.min_stock) > 0 && Number(item.stock ?? 0) <= Number(item.min_stock);

// ---- Filter daftar ----
const search = ref(props.filters?.q ?? '');
const categoryFilter = ref(props.filters?.category_id ?? '');
const stockFilter = ref(props.filters?.stock ?? '');
const hasActiveFilters = computed(() => Boolean(search.value.trim() || categoryFilter.value || stockFilter.value));

const doSearch = () => {
    const params = {};
    const query = search.value.trim();

    if (query) params.q = query;
    if (categoryFilter.value) params.category_id = categoryFilter.value;
    if (stockFilter.value) params.stock = stockFilter.value;

    router.get(route('items.index'), params, { preserveState: true, preserveScroll: true, replace: true });
};

const clearFilters = () => {
    search.value = '';
    categoryFilter.value = '';
    stockFilter.value = '';
    doSearch();
};

// ---- Label paginasi (file lang/id/pagination.php belum ada) ----
const pageLabel = (label) => {
    const l = label.toLowerCase();
    if (l.includes('previous')) return '« Sebelumnya';
    if (l.includes('next')) return 'Selanjutnya »';
    return label;
};

// ---- Modal tambah barang ----
const showCreateModal = ref(false);
const createForm = useForm({
    code: '',
    barcode: '',
    name: '',
    category_id: '',
    brand: '',
    specification: '',
    unit: '',
    sell_price: '',
});

const openCreate = () => {
    createForm.reset();
    createForm.clearErrors();
    showCreateModal.value = true;
};

const submitCreate = () => {
    createForm.post(route('items.store'), {
        onSuccess: () => (showCreateModal.value = false),
    });
};

// ---- Modal edit barang ----
const showEditModal = ref(false);
const editForm = useForm({
    id: null,
    code: '',
    barcode: '',
    name: '',
    category_id: '',
    brand: '',
    specification: '',
    unit: '',
    sell_price: '',
    is_active: true,
});

const openEdit = (item) => {
    editForm.clearErrors();
    editForm.id = item.id;
    editForm.code = item.code;
    editForm.barcode = item.barcode ?? '';
    editForm.name = item.name;
    editForm.category_id = item.category_id ?? '';
    editForm.brand = item.brand ?? '';
    editForm.specification = item.specification ?? '';
    editForm.unit = item.unit;
    editForm.sell_price = item.sell_price;
    editForm.is_active = item.is_active;
    showEditModal.value = true;
};

const submitEdit = () => {
    editForm.patch(route('items.update', editForm.id), {
        onSuccess: () => (showEditModal.value = false),
    });
};

// ---- Nonaktifkan barang (soft delete) ----
const confirmDelete = (item) => {
    if (! confirm(`Nonaktifkan barang "${item.name}"?`)) {
        return;
    }

    useForm({}).delete(route('items.destroy', item.id));
};

// Kategori untuk dropdown: yang aktif, plus kategori barang yang sedang diedit.
const categoryOptions = (currentId = null) =>
    props.categories.filter((c) => c.is_active || c.id === currentId);

// ---- Modal Satuan Alternatif ----
const showUnitsModal = ref(false);
const activeItem = ref(null); // item apa adanya dari props (reaktif lewat items.data)

const openUnits = (item) => {
    activeItem.value = item;
    newUnitForm.reset();
    newUnitForm.clearErrors();
    editUnitId.value = null;
    showUnitsModal.value = true;
};

// Ambil versi terbaru item dari props (supaya list satuan ikut refresh setelah aksi).
const currentItem = () =>
    props.items.data.find((i) => i.id === activeItem.value?.id) ?? activeItem.value;

const newUnitForm = useForm({
    unit: '',
    conversion_qty: '',
    barcode: '',
    sell_price: '',
});

const submitNewUnit = () => {
    newUnitForm.post(route('items.units.store', activeItem.value.id), {
        preserveScroll: true,
        onSuccess: () => newUnitForm.reset(),
    });
};

// ---- Edit satuan (inline di dalam modal) ----
const editUnitId = ref(null);
const editUnitForm = useForm({
    unit: '',
    conversion_qty: '',
    barcode: '',
    sell_price: '',
});

const startEditUnit = (unit) => {
    editUnitId.value = unit.id;
    editUnitForm.clearErrors();
    editUnitForm.unit = unit.unit;
    editUnitForm.conversion_qty = unit.conversion_qty;
    editUnitForm.barcode = unit.barcode ?? '';
    editUnitForm.sell_price = unit.sell_price ?? '';
};

const cancelEditUnit = () => {
    editUnitId.value = null;
};

const submitEditUnit = () => {
    editUnitForm.patch(route('units.update', editUnitId.value), {
        preserveScroll: true,
        onSuccess: () => (editUnitId.value = null),
    });
};

const confirmDeleteUnit = (unit) => {
    if (! confirm(`Hapus satuan "${unit.unit}"?`)) {
        return;
    }

    useForm({}).delete(route('units.destroy', unit.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Barang" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Barang
            </h2>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-6 lg:px-6">
                <div
                    v-if="page.props.flash?.success"
                    class="mb-4 rounded-lg border border-status-success bg-status-success-soft p-4 text-sm text-status-success"
                >
                    {{ page.props.flash.success }}
                </div>
                <div
                    v-if="page.props.flash?.error"
                    class="mb-4 rounded-lg border border-status-danger bg-status-danger-soft p-4 text-sm text-status-danger"
                >
                    {{ page.props.flash.error }}
                </div>

                <section class="rounded-xl border border-border bg-surface p-4 shadow-sm sm:p-6">
                    <div class="mb-5 flex flex-col gap-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-semibold text-text-primary">Daftar Barang</h3>
                                <p class="mt-0.5 text-sm text-text-secondary">Cari dan saring katalog barang.</p>
                            </div>
                            <PrimaryButton v-if="canManage" @click="openCreate">
                                <Plus :size="16" class="mr-2" />
                                Tambah Barang
                            </PrimaryButton>
                        </div>

                        <form
                            class="grid grid-cols-1 items-end gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(15rem,1.5fr)_minmax(11rem,1fr)_minmax(11rem,1fr)_auto_auto]"
                            @submit.prevent="doSearch"
                        >
                            <div>
                                <InputLabel for="items_search" value="Cari barang" />
                                <TextInput
                                    id="items_search"
                                    v-model="search"
                                    type="search"
                                    placeholder="Nama, kode, atau barcode"
                                    class="mt-1 block w-full"
                                />
                            </div>
                            <div>
                                <InputLabel for="items_category" value="Kategori" />
                                <select id="items_category" v-model="categoryFilter" :class="selectClass">
                                    <option value="">Semua kategori</option>
                                    <option v-for="category in categories" :key="category.id" :value="category.id">
                                        {{ category.name }}{{ category.is_active ? '' : ' (nonaktif)' }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <InputLabel for="items_stock" value="Kondisi stok" />
                                <select id="items_stock" v-model="stockFilter" :class="selectClass">
                                    <option value="">Semua stok</option>
                                    <option value="low" :disabled="!stockBranchAvailable">Stok menipis</option>
                                </select>
                            </div>
                            <SecondaryButton type="submit" class="h-10 gap-2">
                                <Search :size="15" />
                                Terapkan
                            </SecondaryButton>
                            <button
                                v-if="hasActiveFilters"
                                type="button"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-border text-text-secondary hover:bg-surface-muted hover:text-text-primary"
                                aria-label="Reset filter"
                                title="Reset filter"
                                @click="clearFilters"
                            >
                                <RotateCcw :size="15" />
                            </button>
                        </form>
                        <p v-if="!stockBranchAvailable" class="-mt-2 text-xs text-text-secondary">
                            Pilih cabang aktif untuk melihat dan menyaring stok.
                        </p>
                    </div>

                    <div class="mb-3 text-xs text-text-secondary">
                        Menampilkan {{ items.from ?? 0 }}–{{ items.to ?? 0 }} dari {{ items.total }} barang
                    </div>

                    <div class="space-y-3 md:hidden">
                        <article
                            v-for="item in items.data"
                            :key="item.id"
                            class="rounded-lg border border-border bg-surface p-4 transition hover:border-brand-primary/50 hover:shadow-sm"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-text-primary">{{ item.name }}</p>
                                    <p class="mt-1 text-xs text-text-secondary">{{ item.code }}<span v-if="item.category_name"> · {{ item.category_name }}</span></p>
                                    <p v-if="item.brand || item.specification" class="mt-1 text-xs text-text-secondary">
                                        {{ [item.brand, item.specification].filter(Boolean).join(' · ') }}
                                    </p>
                                </div>
                                <span
                                    class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold"
                                    :class="item.is_active ? 'bg-status-success-soft text-status-success' : 'bg-surface-muted text-text-secondary'"
                                >
                                    {{ item.is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-3 border-t border-border pt-3 text-xs">
                                <div>
                                    <p class="text-text-secondary">Harga jual</p>
                                    <p class="mt-1 font-semibold text-text-primary">{{ rupiah(item.sell_price) }}</p>
                                </div>
                                <div>
                                    <p class="text-text-secondary">Satuan</p>
                                    <p class="mt-1 font-semibold text-text-primary">{{ item.unit }}</p>
                                </div>
                                <div v-if="stockBranchAvailable" class="col-span-2">
                                    <p class="text-text-secondary">Stok saat ini</p>
                                    <p class="mt-1 font-semibold" :class="isLowStock(item) ? 'text-status-warning' : 'text-text-primary'">
                                        {{ quantity(item.stock) }} {{ item.unit }}
                                        <span class="font-normal text-text-secondary">/ minimum {{ quantity(item.min_stock) }}</span>
                                    </p>
                                </div>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-x-4 gap-y-2 border-t border-border pt-3 text-xs font-semibold">
                                <button type="button" class="text-text-secondary hover:text-brand-primary" @click="openUnits(item)">Satuan</button>
                                <template v-if="canManage">
                                    <button type="button" class="text-brand-primary hover:text-brand-secondary" @click="openEdit(item)">Edit</button>
                                    <button type="button" class="text-status-danger hover:brightness-90" @click="confirmDelete(item)">Nonaktifkan</button>
                                </template>
                            </div>
                        </article>
                        <p v-if="items.data.length === 0" class="rounded-lg border border-dashed border-border px-4 py-10 text-center text-sm text-text-secondary">
                            Tidak ada barang yang cocok dengan filter ini.
                        </p>
                    </div>

                    <div class="hidden overflow-x-auto md:block">
                        <table class="min-w-220 w-full divide-y divide-border text-sm">
                            <thead>
                                <tr class="text-left text-xs font-semibold uppercase text-text-secondary">
                                    <th class="py-3 pr-3">Barang</th>
                                    <th class="py-3 pr-3">Kategori</th>
                                    <th v-if="stockBranchAvailable" class="py-3 pr-3 text-right">Stok</th>
                                    <th class="py-3 pr-3">Satuan</th>
                                    <th class="py-3 pr-3 text-right">Harga jual</th>
                                    <th class="py-3 pr-3">Status</th>
                                    <th class="py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="item in items.data" :key="item.id">
                                    <td class="py-3 pr-3">
                                        <p class="font-semibold text-text-primary">{{ item.name }}</p>
                                        <p class="mt-0.5 text-xs text-text-secondary">{{ item.code }}<span v-if="item.brand || item.specification"> · {{ [item.brand, item.specification].filter(Boolean).join(' · ') }}</span></p>
                                    </td>
                                    <td class="py-3 pr-3 text-text-secondary">{{ item.category_name || '-' }}</td>
                                    <td v-if="stockBranchAvailable" class="py-3 pr-3 text-right">
                                        <span class="font-semibold tabular-nums" :class="isLowStock(item) ? 'text-status-warning' : 'text-text-primary'">
                                            {{ quantity(item.stock) }} {{ item.unit }}
                                        </span>
                                        <p class="mt-0.5 text-xs text-text-disabled">Min. {{ quantity(item.min_stock) }}</p>
                                    </td>
                                    <td class="py-3 pr-3 text-text-secondary">
                                        {{ item.unit }}
                                        <span v-if="item.units.length" class="ml-1 text-xs text-text-disabled">+{{ item.units.length }}</span>
                                    </td>
                                    <td class="py-3 pr-3 text-right font-medium tabular-nums text-text-primary">{{ rupiah(item.sell_price) }}</td>
                                    <td class="py-3 pr-3">
                                        <span
                                            class="rounded-full px-2.5 py-1 text-[11px] font-semibold"
                                            :class="item.is_active ? 'bg-status-success-soft text-status-success' : 'bg-surface-muted text-text-secondary'"
                                        >
                                            {{ item.is_active ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-right whitespace-nowrap">
                                        <button type="button" class="mr-3 text-text-secondary hover:text-brand-primary" @click="openUnits(item)">Satuan</button>
                                        <template v-if="canManage">
                                            <button type="button" class="mr-3 text-brand-primary hover:text-brand-secondary" @click="openEdit(item)">Edit</button>
                                            <button type="button" class="text-status-danger hover:brightness-90" @click="confirmDelete(item)">Nonaktifkan</button>
                                        </template>
                                    </td>
                                </tr>
                                <tr v-if="items.data.length === 0">
                                    <td :colspan="stockBranchAvailable ? 7 : 6" class="py-12 text-center text-text-secondary">
                                        Tidak ada barang yang cocok dengan filter ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginasi -->
                    <div v-if="items.last_page > 1" class="mt-5 flex flex-wrap items-center gap-1 border-t border-border pt-4">
                        <template v-for="link in items.links" :key="link.label">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-scroll
                                class="rounded-lg border px-3 py-1.5 text-sm"
                                :class="link.active
                                    ? 'border-brand-primary bg-brand-primary text-white'
                                    : 'border-border text-text-secondary hover:bg-surface-muted hover:text-text-primary'"
                            >
                                {{ pageLabel(link.label) }}
                            </Link>
                            <span
                                v-else
                                class="rounded-lg border border-border px-3 py-1.5 text-sm text-text-disabled"
                            >
                                {{ pageLabel(link.label) }}
                            </span>
                        </template>
                    </div>
                </section>
        </div>

        <!-- Modal Tambah Barang -->
        <Modal :show="showCreateModal" @close="showCreateModal = false">
            <form @submit.prevent="submitCreate" class="p-6">
                <h3 class="text-lg font-medium text-gray-900">Tambah Barang</h3>

                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="create_code" value="Kode Barang" />
                        <TextInput id="create_code" v-model="createForm.code" type="text" class="mt-1 block w-full" autofocus />
                        <InputError :message="createForm.errors.code" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="create_barcode" value="Barcode (opsional)" />
                        <TextInput id="create_barcode" v-model="createForm.barcode" type="text" class="mt-1 block w-full" />
                        <InputError :message="createForm.errors.barcode" class="mt-2" />
                    </div>
                </div>

                <div class="mt-4">
                    <InputLabel for="create_name" value="Nama Barang" />
                    <TextInput id="create_name" v-model="createForm.name" type="text" class="mt-1 block w-full" />
                    <InputError :message="createForm.errors.name" class="mt-2" />
                </div>

                <div class="mt-4">
                    <InputLabel for="create_category" value="Kategori" />
                    <select id="create_category" v-model="createForm.category_id" :class="selectClass">
                        <option value="">- Tanpa kategori -</option>
                        <option v-for="c in categoryOptions()" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <InputError :message="createForm.errors.category_id" class="mt-2" />
                </div>

                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="create_brand" value="Merek (opsional)" />
                        <TextInput id="create_brand" v-model="createForm.brand" type="text" class="mt-1 block w-full" />
                        <InputError :message="createForm.errors.brand" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="create_spec" value="Spesifikasi (opsional)" />
                        <TextInput id="create_spec" v-model="createForm.specification" type="text" class="mt-1 block w-full" />
                        <InputError :message="createForm.errors.specification" class="mt-2" />
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="create_unit" value="Satuan Dasar" />
                        <TextInput id="create_unit" v-model="createForm.unit" type="text" class="mt-1 block w-full" placeholder="mis. sak, batang, kg" />
                        <InputError :message="createForm.errors.unit" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="create_price" value="Harga Jual (Rp)" />
                        <TextInput id="create_price" v-model="createForm.sell_price" type="number" min="0" step="0.01" class="mt-1 block w-full" />
                        <InputError :message="createForm.errors.sell_price" class="mt-2" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showCreateModal = false">Batal</SecondaryButton>
                    <PrimaryButton :disabled="createForm.processing">Simpan</PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Modal Edit Barang -->
        <Modal :show="showEditModal" @close="showEditModal = false">
            <form @submit.prevent="submitEdit" class="p-6">
                <h3 class="text-lg font-medium text-gray-900">Edit Barang</h3>

                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="edit_code" value="Kode Barang" />
                        <TextInput id="edit_code" v-model="editForm.code" type="text" class="mt-1 block w-full" />
                        <InputError :message="editForm.errors.code" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="edit_barcode" value="Barcode (opsional)" />
                        <TextInput id="edit_barcode" v-model="editForm.barcode" type="text" class="mt-1 block w-full" />
                        <InputError :message="editForm.errors.barcode" class="mt-2" />
                    </div>
                </div>

                <div class="mt-4">
                    <InputLabel for="edit_name" value="Nama Barang" />
                    <TextInput id="edit_name" v-model="editForm.name" type="text" class="mt-1 block w-full" />
                    <InputError :message="editForm.errors.name" class="mt-2" />
                </div>

                <div class="mt-4">
                    <InputLabel for="edit_category" value="Kategori" />
                    <select id="edit_category" v-model="editForm.category_id" :class="selectClass">
                        <option value="">- Tanpa kategori -</option>
                        <option v-for="c in categoryOptions(editForm.category_id)" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <InputError :message="editForm.errors.category_id" class="mt-2" />
                </div>

                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="edit_brand" value="Merek (opsional)" />
                        <TextInput id="edit_brand" v-model="editForm.brand" type="text" class="mt-1 block w-full" />
                        <InputError :message="editForm.errors.brand" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="edit_spec" value="Spesifikasi (opsional)" />
                        <TextInput id="edit_spec" v-model="editForm.specification" type="text" class="mt-1 block w-full" />
                        <InputError :message="editForm.errors.specification" class="mt-2" />
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="edit_unit" value="Satuan Dasar" />
                        <TextInput id="edit_unit" v-model="editForm.unit" type="text" class="mt-1 block w-full" />
                        <p class="mt-1 text-xs text-gray-400">Tidak bisa diubah setelah barang punya mutasi stok.</p>
                        <InputError :message="editForm.errors.unit" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="edit_price" value="Harga Jual (Rp)" />
                        <TextInput id="edit_price" v-model="editForm.sell_price" type="number" min="0" step="0.01" class="mt-1 block w-full" />
                        <InputError :message="editForm.errors.sell_price" class="mt-2" />
                    </div>
                </div>

                <div class="mt-4 flex items-center">
                    <input
                        id="edit_is_active"
                        v-model="editForm.is_active"
                        type="checkbox"
                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <label for="edit_is_active" class="ml-2 text-sm text-gray-700">Aktif</label>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showEditModal = false">Batal</SecondaryButton>
                    <PrimaryButton :disabled="editForm.processing">Simpan</PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Modal Satuan Alternatif -->
        <Modal :show="showUnitsModal" max-width="2xl" @close="showUnitsModal = false">
            <div class="p-6" v-if="activeItem">
                <h3 class="text-lg font-medium text-gray-900">
                    Satuan Alternatif — {{ activeItem.name }}
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                    Satuan dasar: <span class="font-medium">{{ activeItem.unit }}</span>
                </p>

                <table class="mt-4 min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="py-2 text-left text-xs font-medium text-gray-500">Satuan</th>
                            <th class="py-2 text-left text-xs font-medium text-gray-500">Konversi ke {{ activeItem.unit }}</th>
                            <th class="py-2 text-left text-xs font-medium text-gray-500">Barcode</th>
                            <th class="py-2 text-right text-xs font-medium text-gray-500">Harga Jual</th>
                            <th v-if="canManage" class="py-2 text-right text-xs font-medium text-gray-500">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="u in currentItem()?.units ?? []" :key="u.id">
                            <template v-if="editUnitId === u.id">
                                <td class="py-2 pr-2">
                                    <TextInput v-model="editUnitForm.unit" type="text" class="w-24" />
                                    <InputError :message="editUnitForm.errors.unit" class="mt-1 text-xs" />
                                </td>
                                <td class="py-2 pr-2">
                                    <TextInput v-model="editUnitForm.conversion_qty" type="number" min="0" step="0.0001" class="w-28" />
                                    <InputError :message="editUnitForm.errors.conversion_qty" class="mt-1 text-xs" />
                                </td>
                                <td class="py-2 pr-2">
                                    <TextInput v-model="editUnitForm.barcode" type="text" class="w-28" />
                                    <InputError :message="editUnitForm.errors.barcode" class="mt-1 text-xs" />
                                </td>
                                <td class="py-2 pl-2">
                                    <TextInput v-model="editUnitForm.sell_price" type="number" min="0" step="0.01" class="w-28" placeholder="otomatis" />
                                    <InputError :message="editUnitForm.errors.sell_price" class="mt-1 text-xs" />
                                </td>
                                <td class="py-2 text-right whitespace-nowrap">
                                    <button class="mr-2 text-indigo-600 hover:text-indigo-900" @click="submitEditUnit">Simpan</button>
                                    <button class="text-gray-500 hover:text-gray-700" @click="cancelEditUnit">Batal</button>
                                </td>
                            </template>
                            <template v-else>
                                <td class="py-2 text-sm text-gray-900">{{ u.unit }}</td>
                                <td class="py-2 text-sm text-gray-500">1 {{ u.unit }} = {{ u.conversion_qty }} {{ activeItem.unit }}</td>
                                <td class="py-2 text-sm text-gray-500">{{ u.barcode || '-' }}</td>
                                <td class="py-2 text-right text-sm text-gray-900">
                                    {{ u.sell_price ? rupiah(u.sell_price) : 'otomatis' }}
                                </td>
                                <td v-if="canManage" class="py-2 text-right text-sm whitespace-nowrap">
                                    <button class="mr-3 text-indigo-600 hover:text-indigo-900" @click="startEditUnit(u)">Edit</button>
                                    <button class="text-red-600 hover:text-red-900" @click="confirmDeleteUnit(u)">Hapus</button>
                                </td>
                            </template>
                        </tr>
                        <tr v-if="(currentItem()?.units ?? []).length === 0">
                            <td :colspan="canManage ? 5 : 4" class="py-3 text-center text-sm text-gray-400">
                                Belum ada satuan alternatif.
                            </td>
                        </tr>
                    </tbody>
                </table>

                <form v-if="canManage" @submit.prevent="submitNewUnit" class="mt-6 border-t pt-4">
                    <p class="mb-2 text-sm font-medium text-gray-700">Tambah Satuan</p>
                    <div class="grid grid-cols-4 gap-3">
                        <div>
                            <TextInput v-model="newUnitForm.unit" type="text" placeholder="mis. kg" class="w-full" />
                            <InputError :message="newUnitForm.errors.unit" class="mt-1 text-xs" />
                        </div>
                        <div>
                            <TextInput v-model="newUnitForm.conversion_qty" type="number" min="0" step="0.0001" :placeholder="`per ${activeItem.unit}`" class="w-full" />
                            <InputError :message="newUnitForm.errors.conversion_qty" class="mt-1 text-xs" />
                        </div>
                        <div>
                            <TextInput v-model="newUnitForm.barcode" type="text" placeholder="barcode (opsional)" class="w-full" />
                            <InputError :message="newUnitForm.errors.barcode" class="mt-1 text-xs" />
                        </div>
                        <div>
                            <TextInput v-model="newUnitForm.sell_price" type="number" min="0" step="0.01" placeholder="harga (opsional)" class="w-full" />
                            <InputError :message="newUnitForm.errors.sell_price" class="mt-1 text-xs" />
                        </div>
                    </div>
                    <div class="mt-3 flex justify-end">
                        <PrimaryButton :disabled="newUnitForm.processing">+ Tambah Satuan</PrimaryButton>
                    </div>
                </form>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showUnitsModal = false">Tutup</SecondaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>