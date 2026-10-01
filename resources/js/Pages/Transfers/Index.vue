<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowDownLeft, ArrowUpRight, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    transfers: Object,
    filters: Object,
    branches: Array,
    items: Array,
    canCreate: Boolean,
});

const page = usePage();

const selectClass =
    'mt-1 block w-full rounded-lg border-border bg-surface text-sm text-text-primary shadow-sm focus:border-brand-primary focus:ring-brand-primary';

const statusLabel = {
    draft: 'Draft',
    sent: 'Terkirim',
    partial: 'Sebagian (ada selisih)',
    received: 'Selesai',
    cancelled: 'Dibatalkan',
};
const statusClass = {
    draft: 'bg-surface-muted text-text-secondary',
    sent: 'bg-status-info-soft text-status-info',
    partial: 'bg-status-warning-soft text-status-warning',
    received: 'bg-status-success-soft text-status-success',
    cancelled: 'bg-status-danger-soft text-status-danger',
};

// ---- Filter status ----
const statusFilter = ref(props.filters?.status ?? '');

const applyFilter = () => {
    router.get(
        route('transfers.index'),
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

// ---- Hak akses per baris (kasar, pasti tetap dicek server lewat Gate) ----
const currentUser = page.props.auth.user;
const isOwner = currentUser.role === 'owner';
const isAdmin = currentUser.role === 'admin';

const canSendOrCancel = (t) => props.canCreate && t.status === 'draft' && (isOwner || currentUser.branch_id === t.from_branch_id);
const canReceive = (t) => isOwner || (isAdmin && currentUser.branch_id === t.to_branch_id);
const canResolve = (t) =>
    isOwner ||
    (isAdmin && (currentUser.branch_id === t.from_branch_id || currentUser.branch_id === t.to_branch_id));

// ---- Modal Buat Dokumen ----
const showCreateModal = ref(false);

const blankLine = () => ({ item_id: '', qty_sent: '', note: '' });

const form = useForm({
    from_branch_id: props.branches.length === 1 ? props.branches[0].id : '',
    to_branch_id: '',
    transfer_date: new Date().toISOString().slice(0, 10),
    note: '',
    items: [blankLine()],
});

const openCreate = () => {
    form.reset();
    form.clearErrors();
    form.from_branch_id = props.branches.length === 1 ? props.branches[0].id : '';
    form.transfer_date = new Date().toISOString().slice(0, 10);
    form.items = [blankLine()];
    showCreateModal.value = true;
};

const addLine = () => form.items.push(blankLine());
const removeLine = (idx) => {
    if (form.items.length > 1) form.items.splice(idx, 1);
};

const itemLabel = (item) => `${item.code} — ${item.name} (${item.unit})`;

const submitCreate = () => {
    form.post(route('transfers.store'), {
        onSuccess: () => (showCreateModal.value = false),
    });
};

// ---- Kirim / Batalkan ----
const send = (t) => {
    if (! confirm(`Kirim transfer ${t.transfer_no}? Stok cabang asal akan langsung berkurang.`)) return;
    useForm({}).patch(route('transfers.send', t.id), { preserveScroll: true });
};

const cancel = (t) => {
    if (! confirm(`Batalkan transfer ${t.transfer_no}?`)) return;
    useForm({}).patch(route('transfers.cancel', t.id), { preserveScroll: true });
};

// ---- Modal Terima ----
const showReceiveModal = ref(false);
const receiveTarget = ref(null);
const receiveForm = useForm({ items: {} });

const openReceive = (t) => {
    receiveTarget.value = t;
    const items = {};
    t.items.forEach((line) => {
        items[line.id] = line.qty_sent;
    });
    receiveForm.reset();
    receiveForm.clearErrors();
    receiveForm.items = items;
    showReceiveModal.value = true;
};

const submitReceive = () => {
    receiveForm.patch(route('transfers.receive', receiveTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => (showReceiveModal.value = false),
    });
};

// ---- Selesaikan selisih ----
const resolveDifference = (line, resolution) => {
    const label = resolution === 'returned' ? 'dikembalikan ke cabang asal' : 'dianggap kerugian (write off)';
    if (! confirm(`Tandai selisih barang ini ${label}?`)) return;
    useForm({ difference_resolution: resolution }).patch(route('transfer-details.resolve', line.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Transfer Antar Cabang" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Transfer Antar Cabang</h2>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-6 lg:px-6">
                <div v-if="page.props.flash?.success" class="mb-4 rounded-lg border border-status-success bg-status-success-soft p-4 text-sm text-status-success">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="mb-4 rounded-lg border border-status-danger bg-status-danger-soft p-4 text-sm text-status-danger">
                    {{ page.props.flash.error }}
                </div>

                <section class="rounded-xl border border-border/50 bg-surface/45 p-4 shadow-sm shadow-brand-dark/5 backdrop-blur-xl sm:p-6">
                    <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-text-primary">Daftar Transfer</h3>
                            <p class="mt-0.5 text-sm text-text-secondary">Perpindahan stok dan penerimaan antarcabang.</p>
                        </div>

                        <div class="flex w-full flex-wrap items-end gap-3 sm:w-auto">
                            <div class="min-w-44 flex-1 sm:flex-none">
                                <InputLabel for="transfer_status" value="Status" />
                                <select id="transfer_status" v-model="statusFilter" @change="applyFilter" :class="selectClass">
                                <option value="">Semua Status</option>
                                <option value="draft">Draft</option>
                                <option value="sent">Terkirim</option>
                                <option value="partial">Sebagian (ada selisih)</option>
                                <option value="received">Selesai</option>
                                <option value="cancelled">Dibatalkan</option>
                                </select>
                            </div>
                            <PrimaryButton v-if="canCreate" @click="openCreate">Buat Transfer</PrimaryButton>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <article v-for="t in transfers.data" :key="t.id" class="rounded-xl border border-border/70 bg-surface/70 p-4 shadow-sm shadow-brand-dark/5 backdrop-blur-lg transition duration-200 hover:border-brand-primary/40 hover:shadow-md sm:p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-text-primary">{{ t.transfer_no }}</p>
                                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                        <span class="rounded-md bg-surface-muted px-2.5 py-1 font-medium text-text-primary">{{ t.from_branch_name }}</span>
                                        <ArrowUpRight :size="14" class="text-text-disabled" aria-hidden="true" />
                                        <span class="rounded-md bg-brand-primary-soft px-2.5 py-1 font-medium text-brand-primary">{{ t.to_branch_name }}</span>
                                        <span class="text-text-secondary">{{ t.transfer_date }}</span>
                                    </div>
                                    <p v-if="t.note" class="mt-2 text-sm text-text-secondary">{{ t.note }}</p>
                                </div>
                                <div class="text-left sm:text-right">
                                    <span :class="statusClass[t.status]" class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold">
                                        {{ statusLabel[t.status] }}
                                    </span>
                                    <p class="mt-1 text-xs text-text-secondary">
                                        oleh {{ t.creator_name }}
                                        <span v-if="t.sender_name"> · dikirim {{ t.sender_name }}</span>
                                        <span v-if="t.receiver_name"> · diterima {{ t.receiver_name }}</span>
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4 space-y-2 border-t border-border pt-4">
                                <div v-for="line in t.items" :key="line.id" class="grid grid-cols-1 gap-2 rounded-lg border border-border/40 bg-surface/30 p-3 backdrop-blur-sm sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-center">
                                    <p class="text-sm font-semibold text-text-primary">{{ line.item_name }}</p>
                                    <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-text-secondary">
                                        <span>Dikirim <strong class="font-semibold text-text-primary">{{ line.qty_sent }} {{ line.unit }}</strong></span>
                                        <span v-if="t.status !== 'draft'">Diterima <strong class="font-semibold text-text-primary">{{ line.qty_received }} {{ line.unit }}</strong></span>
                                    </div>
                                    <div class="flex flex-wrap items-center justify-start gap-2 sm:justify-end">
                                        <span v-if="line.has_difference && line.difference_resolution === 'none'" class="rounded-md bg-status-warning-soft px-2 py-1 text-xs font-medium text-status-warning">
                                            Selisih {{ (line.qty_sent - line.qty_received).toFixed(4) }} · perlu resolusi
                                        </span>
                                        <span v-else-if="line.difference_resolution === 'returned'" class="rounded-md bg-surface-muted px-2 py-1 text-xs text-text-secondary">
                                            Selisih dikembalikan
                                        </span>
                                        <span v-else-if="line.difference_resolution === 'written_off'" class="rounded-md bg-status-danger-soft px-2 py-1 text-xs text-status-danger">
                                            Selisih write off
                                        </span>
                                        <template v-if="t.status === 'partial' && line.has_difference && line.difference_resolution === 'none' && canResolve(t)">
                                            <button class="rounded-md px-2 py-1 text-xs font-semibold text-brand-primary hover:bg-brand-primary-soft" @click="resolveDifference(line, 'returned')">Kembalikan</button>
                                            <button class="rounded-md px-2 py-1 text-xs font-semibold text-status-danger hover:bg-status-danger-soft" @click="resolveDifference(line, 'written_off')">Write off</button>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <div v-if="canSendOrCancel(t) || (t.status === 'sent' && canReceive(t))" class="mt-4 flex flex-wrap justify-end gap-2 border-t border-border pt-3">
                                <template v-if="t.status === 'draft' && canSendOrCancel(t)">
                                    <SecondaryButton @click="cancel(t)">Batalkan</SecondaryButton>
                                    <PrimaryButton @click="send(t)">Kirim</PrimaryButton>
                                </template>
                                <template v-if="t.status === 'sent' && canReceive(t)">
                                    <PrimaryButton @click="openReceive(t)">Terima</PrimaryButton>
                                </template>
                            </div>
                        </article>

                        <p v-if="transfers.data.length === 0" class="rounded-lg border border-dashed border-border py-12 text-center text-sm text-text-secondary">
                            Tidak ada transfer untuk status ini.
                        </p>
                    </div>

                    <!-- Paginasi -->
                    <div v-if="transfers.last_page > 1" class="mt-5 flex flex-wrap gap-1 border-t border-border pt-4">
                        <template v-for="link in transfers.links" :key="link.label">
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

        <!-- Modal Buat Transfer -->
        <Modal :show="showCreateModal" max-width="3xl" @close="showCreateModal = false">
            <form @submit.prevent="submitCreate" class="p-5 sm:p-6">
                <h3 class="text-lg font-semibold text-text-primary">Buat Transfer Antar Cabang</h3>
                <p class="mt-1 text-sm text-text-secondary">Pilih cabang asal, tujuan, dan jumlah barang yang dikirim.</p>

                <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <InputLabel value="Cabang Asal" />
                        <select v-model="form.from_branch_id" :class="selectClass">
                            <option value="">- Pilih -</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                        <InputError :message="form.errors.from_branch_id" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel value="Cabang Tujuan" />
                        <select v-model="form.to_branch_id" :class="selectClass">
                            <option value="">- Pilih -</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                        <InputError :message="form.errors.to_branch_id" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel value="Tanggal" />
                        <TextInput v-model="form.transfer_date" type="date" class="mt-1 block w-full" />
                        <InputError :message="form.errors.transfer_date" class="mt-2" />
                    </div>
                </div>

                <div class="mt-4">
                    <InputLabel value="Catatan (opsional)" />
                    <textarea v-model="form.note" rows="2" :class="selectClass"></textarea>
                    <InputError :message="form.errors.note" class="mt-2" />
                </div>

                <div class="mt-5 border-t border-border pt-5">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-sm font-semibold text-text-primary">Daftar Barang</p>
                        <SecondaryButton type="button" @click="addLine">+ Tambah Barang</SecondaryButton>
                    </div>

                    <InputError :message="form.errors.items" class="mb-2" />

                    <div v-for="(line, idx) in form.items" :key="idx" class="mb-3 grid grid-cols-1 items-start gap-3 rounded-lg border border-border/70 bg-surface/45 p-3 backdrop-blur-md sm:grid-cols-12">
                        <div class="sm:col-span-7">
                            <InputLabel :value="`Barang ${idx + 1}`" />
                            <select v-model="line.item_id" :class="selectClass">
                                <option value="">- Pilih Barang -</option>
                                <option v-for="it in items" :key="it.id" :value="it.id">{{ itemLabel(it) }}</option>
                            </select>
                            <InputError :message="form.errors[`items.${idx}.item_id`]" class="mt-1 text-xs" />
                        </div>
                        <div class="sm:col-span-4">
                            <InputLabel value="Jumlah dikirim" />
                            <TextInput v-model="line.qty_sent" type="number" min="0" step="0.0001" placeholder="Qty dikirim" class="w-full" />
                            <InputError :message="form.errors[`items.${idx}.qty_sent`]" class="mt-1 text-xs" />
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

        <!-- Modal Terima -->
        <Modal :show="showReceiveModal" max-width="2xl" @close="showReceiveModal = false">
            <form @submit.prevent="submitReceive" class="p-5 sm:p-6" v-if="receiveTarget">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-accent-emerald-soft text-accent-emerald">
                        <ArrowDownLeft :size="18" />
                    </span>
                    <div>
                        <h3 class="text-lg font-semibold text-text-primary">Terima Transfer</h3>
                        <p class="mt-0.5 text-xs text-text-secondary">{{ receiveTarget.transfer_no }}</p>
                    </div>
                </div>
                <p class="mt-3 text-sm text-text-secondary">Masukkan jumlah aktual yang diterima untuk tiap barang.</p>

                <div class="mt-4 max-h-[55vh] space-y-2 overflow-y-auto overscroll-contain pr-1">
                    <div v-for="line in receiveTarget.items" :key="line.id" class="grid grid-cols-1 gap-3 rounded-lg border border-border/70 bg-surface/45 p-3 backdrop-blur-md sm:grid-cols-[minmax(0,1fr)_auto_8rem] sm:items-center">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-text-primary">{{ line.item_name }}</p>
                            <p class="mt-0.5 text-xs text-text-secondary">Maksimum {{ line.qty_sent }} {{ line.unit }} dikirim</p>
                        </div>
                        <div class="text-xs text-text-secondary sm:text-right">Dikirim {{ line.qty_sent }} {{ line.unit }}</div>
                        <div>
                            <InputLabel :for="`receive-${line.id}`" value="Diterima" />
                            <TextInput
                                :id="`receive-${line.id}`"
                                v-model="receiveForm.items[line.id]"
                                type="number" min="0" :max="line.qty_sent" step="0.0001"
                                class="w-full"
                            />
                            <InputError :message="receiveForm.errors[`items.${line.id}`]" class="mt-1 text-xs" />
                        </div>
                    </div>
                </div>

                <p class="mt-3 rounded-lg bg-status-warning-soft px-3 py-2 text-xs text-status-warning">
                    Qty kurang dari yang dikirim akan tercatat sebagai selisih dan diselesaikan (kembalikan/write off) setelahnya.
                </p>

                <div class="mt-6 flex flex-wrap justify-end gap-2 border-t border-border pt-4">
                    <SecondaryButton @click="showReceiveModal = false">Batal</SecondaryButton>
                    <PrimaryButton :disabled="receiveForm.processing">Terima</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>