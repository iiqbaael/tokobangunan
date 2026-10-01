<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';

const props = defineProps({
    adjustments: Object,
    filters: Object,
    branches: Array,
    items: Array,
    canCreate: Boolean,
});

const page = usePage();

const selectClass =
    'mt-1 block w-full rounded-lg border-border bg-surface text-sm text-text-primary shadow-sm focus:border-brand-primary focus:ring-brand-primary';

const rupiah = (value) => (value === null || value === undefined ? '-' : 'Rp ' + Number(value).toLocaleString('id-ID'));

const statusLabel = { pending: 'Menunggu', approved: 'Disetujui', rejected: 'Ditolak' };
const statusClass = {
    pending: 'bg-status-warning-soft text-status-warning',
    approved: 'bg-status-success-soft text-status-success',
    rejected: 'bg-status-danger-soft text-status-danger',
};

const reasonLabel = {
    restock: 'Restock',
    initial_stock: 'Stok Awal',
    damaged: 'Rusak',
    lost: 'Hilang',
    correction: 'Koreksi',
    other: 'Lainnya',
};

const REASONS_IN_ONLY = ['restock', 'initial_stock'];
const REASONS_OUT_ONLY = ['damaged', 'lost'];
// correction & other: boleh in atau out

// ---- Filter status ----
const statusFilter = ref(props.filters?.status ?? '');

const applyFilter = () => {
    router.get(
        route('stock-adjustments.index'),
        statusFilter.value ? { status: statusFilter.value } : {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const pageLabel = (label) => {
    const l = label.toLowerCase();
    if (l.includes('previous')) return '« Sebelumnya';
    if (l.includes('next')) return 'Selanjutnya »';
    return label;
};

// ---- Modal tambah dokumen ----
const showCreateModal = ref(false);

const blankLine = () => ({ item_id: '', qty: '', unit_cost: '' });

const form = useForm({
    branch_id: props.branches.length === 1 ? props.branches[0].id : '',
    adjustment_date: new Date().toISOString().slice(0, 10),
    adjustment_type: 'in',
    reason: 'restock',
    reference_no: '',
    description: '',
    items: [blankLine()],
});

const openCreate = () => {
    form.reset();
    form.clearErrors();
    form.branch_id = props.branches.length === 1 ? props.branches[0].id : '';
    form.adjustment_date = new Date().toISOString().slice(0, 10);
    form.adjustment_type = 'in';
    form.reason = 'restock';
    form.items = [blankLine()];
    showCreateModal.value = true;
};

// Saat reason berubah, samakan adjustment_type otomatis kalau reason itu tipenya tetap.
const onReasonChange = () => {
    if (REASONS_IN_ONLY.includes(form.reason)) form.adjustment_type = 'in';
    if (REASONS_OUT_ONLY.includes(form.reason)) form.adjustment_type = 'out';
};

const typeIsLocked = computed(() =>
    REASONS_IN_ONLY.includes(form.reason) || REASONS_OUT_ONLY.includes(form.reason),
);

const requireCost = computed(() => REASONS_IN_ONLY.includes(form.reason) && form.adjustment_type === 'in');

const addLine = () => form.items.push(blankLine());
const removeLine = (idx) => {
    if (form.items.length > 1) form.items.splice(idx, 1);
};

const itemLabel = (item) => `${item.code} — ${item.name} (${item.unit})`;

const submitCreate = () => {
    form.post(route('stock-adjustments.store'), {
        onSuccess: () => (showCreateModal.value = false),
    });
};

// ---- Approve / Reject ----
const approve = (adjustment) => {
    if (! confirm(`Setujui dokumen ${adjustment.adjustment_no}? Stok akan langsung diperbarui.`)) {
        return;
    }
    useForm({}).patch(route('stock-adjustments.approve', adjustment.id), { preserveScroll: true });
};

const showRejectModal = ref(false);
const rejectTarget = ref(null);
const rejectForm = useForm({ rejected_reason: '' });

const openReject = (adjustment) => {
    rejectTarget.value = adjustment;
    rejectForm.reset();
    rejectForm.clearErrors();
    showRejectModal.value = true;
};

const submitReject = () => {
    rejectForm.patch(route('stock-adjustments.reject', rejectTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => (showRejectModal.value = false),
    });
};

// Tombol approve/reject hanya untuk dokumen pending, disembunyikan untuk pembuat
// dokumen itu sendiri (kecuali Owner) -- pengecekan pasti tetap di server (Gate).
const currentUserId = page.props.auth.user.id;
const isOwner = page.props.auth.user.role === 'owner';
const canReview = (adjustment) =>
    adjustment.status === 'pending' && (isOwner || adjustment.created_by !== currentUserId);
</script>

<template>
    <Head title="Barang Masuk & Penyesuaian Stok" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Barang Masuk & Penyesuaian Stok
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
                    <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-text-primary">Daftar Dokumen</h3>
                            <p class="mt-0.5 text-sm text-text-secondary">Barang masuk, koreksi, dan penyesuaian stok.</p>
                        </div>

                        <div class="flex w-full flex-wrap items-end gap-3 sm:w-auto">
                            <div class="min-w-44 flex-1 sm:flex-none">
                                <InputLabel for="adjustment_status" value="Status" />
                                <select id="adjustment_status" v-model="statusFilter" @change="applyFilter" :class="selectClass">
                                <option value="">Semua Status</option>
                                <option value="pending">Menunggu</option>
                                <option value="approved">Disetujui</option>
                                <option value="rejected">Ditolak</option>
                                </select>
                            </div>
                            <PrimaryButton v-if="canCreate" @click="openCreate">
                                Buat Dokumen
                            </PrimaryButton>
                        </div>
                    </div>

                    <div class="mb-3 text-xs text-text-secondary">
                        {{ adjustments.total }} dokumen
                    </div>

                    <div class="space-y-4">
                        <div
                            v-for="a in adjustments.data"
                            :key="a.id"
                            class="rounded-xl border border-border bg-surface p-4 shadow-sm transition duration-200 hover:border-brand-primary/40 hover:shadow-md sm:p-5"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="font-semibold text-text-primary">
                                        {{ a.adjustment_no }}
                                    </p>
                                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-text-secondary">
                                        <span class="rounded-md bg-surface-muted px-2 py-1 font-medium">{{ a.branch_name }}</span>
                                        <span>{{ a.adjustment_date }}</span>
                                        <span class="rounded-md px-2 py-1 font-semibold" :class="a.adjustment_type === 'in' ? 'bg-status-success-soft text-status-success' : 'bg-status-warning-soft text-status-warning'">
                                            {{ a.adjustment_type === 'in' ? 'Stok masuk' : 'Stok keluar' }}
                                        </span>
                                        <span>{{ reasonLabel[a.reason] }}</span>
                                        <span v-if="a.reference_no">Ref: {{ a.reference_no }}</span>
                                    </div>
                                </div>
                                <div class="text-left sm:text-right">
                                    <span :class="statusClass[a.status]" class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold">
                                        {{ statusLabel[a.status] }}
                                    </span>
                                    <p class="mt-1 text-xs text-text-secondary">
                                        oleh {{ a.creator_name }}
                                        <span v-if="a.approver_name"> · disetujui {{ a.approver_name }}</span>
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4 overflow-x-auto rounded-lg border border-border">
                            <table class="min-w-full text-sm">
                                <tbody class="divide-y divide-border">
                                    <tr v-for="(line, idx) in a.items" :key="idx">
                                        <td class="py-2 pl-3 font-medium text-text-primary">{{ line.item_name }}</td>
                                        <td class="py-2 px-3 text-right text-text-secondary">{{ line.qty }} {{ line.unit }}</td>
                                        <td class="py-2 pl-3 pr-3 text-right tabular-nums text-text-secondary">{{ rupiah(line.unit_cost) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            </div>

                            <p v-if="a.status === 'rejected' && a.rejected_reason" class="mt-3 rounded-lg bg-status-danger-soft px-3 py-2 text-sm text-status-danger">
                                Alasan ditolak: {{ a.rejected_reason }}
                            </p>

                            <div v-if="canReview(a)" class="mt-4 flex flex-wrap justify-end gap-2 border-t border-border pt-3">
                                <SecondaryButton @click="openReject(a)">Tolak</SecondaryButton>
                                <PrimaryButton @click="approve(a)">Setujui</PrimaryButton>
                            </div>
                        </div>

                        <p v-if="adjustments.data.length === 0" class="rounded-lg border border-dashed border-border py-12 text-center text-sm text-text-secondary">
                            Tidak ada dokumen untuk status ini.
                        </p>
                    </div>

                    <!-- Paginasi -->
                    <div v-if="adjustments.last_page > 1" class="mt-5 flex flex-wrap gap-1 border-t border-border pt-4">
                        <template v-for="link in adjustments.links" :key="link.label">
                            <button
                                v-if="link.url"
                                class="rounded-lg border px-3 py-1.5 text-sm"
                                :class="link.active
                                    ? 'border-brand-primary bg-brand-primary text-white'
                                    : 'border-border text-text-secondary hover:bg-surface-muted hover:text-text-primary'"
                                @click="router.get(link.url, {}, { preserveState: true, preserveScroll: true })"
                            >
                                {{ pageLabel(link.label) }}
                            </button>
                            <span v-else class="rounded-lg border border-border px-3 py-1.5 text-sm text-text-disabled">
                                {{ pageLabel(link.label) }}
                            </span>
                        </template>
                    </div>
                </section>
        </div>

        <!-- Modal Buat Dokumen -->
        <Modal :show="showCreateModal" max-width="3xl" @close="showCreateModal = false">
            <form @submit.prevent="submitCreate" class="p-6">
                <h3 class="text-lg font-semibold text-text-primary">Buat Dokumen Penyesuaian Stok</h3>
                <p class="mt-1 text-sm text-text-secondary">Catat penerimaan barang atau koreksi stok cabang.</p>

                <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <InputLabel value="Cabang" />
                        <select v-model="form.branch_id" :class="selectClass">
                            <option value="">- Pilih -</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                        <InputError :message="form.errors.branch_id" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel value="Tanggal" />
                        <TextInput v-model="form.adjustment_date" type="date" class="mt-1 block w-full" />
                        <InputError :message="form.errors.adjustment_date" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel value="Nomor Referensi (opsional)" />
                        <TextInput v-model="form.reference_no" type="text" class="mt-1 block w-full" />
                        <InputError :message="form.errors.reference_no" class="mt-2" />
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Alasan" />
                        <select v-model="form.reason" @change="onReasonChange" :class="selectClass">
                            <option value="restock">Restock</option>
                            <option value="initial_stock">Stok Awal</option>
                            <option value="damaged">Rusak</option>
                            <option value="lost">Hilang</option>
                            <option value="correction">Koreksi</option>
                            <option value="other">Lainnya</option>
                        </select>
                        <InputError :message="form.errors.reason" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel value="Tipe" />
                        <select v-model="form.adjustment_type" :disabled="typeIsLocked" :class="selectClass">
                            <option value="in">Masuk</option>
                            <option value="out">Keluar</option>
                        </select>
                        <p v-if="typeIsLocked" class="mt-1 text-xs text-gray-400">Tipe otomatis mengikuti alasan.</p>
                        <InputError :message="form.errors.adjustment_type" class="mt-2" />
                    </div>
                </div>

                <p v-if="['restock', 'initial_stock'].includes(form.reason)" class="mt-3 rounded-lg bg-status-info-soft px-3 py-2 text-xs text-status-info">
                    Dokumen dengan alasan ini langsung disetujui otomatis (tidak perlu approval terpisah).
                </p>

                <div class="mt-4">
                    <InputLabel value="Keterangan (opsional)" />
                    <textarea v-model="form.description" rows="2" :class="selectClass"></textarea>
                    <InputError :message="form.errors.description" class="mt-2" />
                </div>

                <div class="mt-5 border-t border-border pt-5">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-sm font-semibold text-text-primary">Daftar Barang</p>
                        <SecondaryButton type="button" @click="addLine">+ Tambah Barang</SecondaryButton>
                    </div>

                    <InputError :message="form.errors.items" class="mb-2" />

                    <div v-for="(line, idx) in form.items" :key="idx" class="mb-3 grid grid-cols-1 items-start gap-3 rounded-lg border border-border bg-page/60 p-3 sm:grid-cols-12">
                        <div class="sm:col-span-5">
                            <InputLabel :value="`Barang ${idx + 1}`" />
                            <select v-model="line.item_id" :class="selectClass">
                                <option value="">- Pilih Barang -</option>
                                <option v-for="it in items" :key="it.id" :value="it.id">{{ itemLabel(it) }}</option>
                            </select>
                            <InputError :message="form.errors[`items.${idx}.item_id`]" class="mt-1 text-xs" />
                        </div>
                        <div class="sm:col-span-3">
                            <InputLabel value="Jumlah" />
                            <TextInput v-model="line.qty" type="number" min="0" step="0.0001" placeholder="Qty" class="w-full" />
                            <InputError :message="form.errors[`items.${idx}.qty`]" class="mt-1 text-xs" />
                        </div>
                        <div class="sm:col-span-3">
                            <InputLabel value="Harga satuan" />
                            <TextInput
                                v-model="line.unit_cost"
                                type="number" min="0" step="0.01"
                                :placeholder="requireCost ? 'Harga satuan (wajib)' : 'Harga satuan (opsional)'"
                                class="w-full"
                            />
                            <InputError :message="form.errors[`items.${idx}.unit_cost`]" class="mt-1 text-xs" />
                        </div>
                        <div class="flex justify-end sm:col-span-1 sm:pt-6">
                            <button
                                type="button"
                                class="flex h-9 w-9 items-center justify-center rounded-lg text-status-danger hover:bg-status-danger-soft disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="form.items.length === 1"
                                :aria-label="`Hapus barang ${idx + 1}`"
                                title="Hapus baris"
                                @click="removeLine(idx)"
                            >
                                <Trash2 :size="16" />
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showCreateModal = false">Batal</SecondaryButton>
                    <PrimaryButton :disabled="form.processing">Simpan</PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Modal Tolak -->
        <Modal :show="showRejectModal" @close="showRejectModal = false">
            <form @submit.prevent="submitReject" class="p-5 sm:p-6">
                <h3 class="text-lg font-semibold text-text-primary">
                    Tolak Dokumen {{ rejectTarget?.adjustment_no }}
                </h3>
                <p class="mt-1 text-sm text-text-secondary">Tuliskan alasan agar riwayat persetujuan tetap jelas.</p>
                <div class="mt-4">
                    <InputLabel value="Alasan Penolakan" />
                    <textarea v-model="rejectForm.rejected_reason" rows="3" :class="selectClass" class="px-3 py-2"></textarea>
                    <InputError :message="rejectForm.errors.rejected_reason" class="mt-2" />
                </div>
                <div class="mt-6 flex flex-wrap justify-end gap-2 border-t border-border pt-4">
                    <SecondaryButton @click="showRejectModal = false">Batal</SecondaryButton>
                    <DangerButton :disabled="rejectForm.processing">Tolak</DangerButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>