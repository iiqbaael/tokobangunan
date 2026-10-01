<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    branchId: Number,
    currentShift: Object, // null kalau tidak ada shift open
    canOpen: Boolean,
    canForceClose: Boolean,
    canRecordCash: Boolean,
    history: Object, // paginated, null kalau bukan admin/owner
});

const page = usePage();

const selectClass =
    'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

const rupiah = (value) => (value === null || value === undefined ? '-' : 'Rp ' + Number(value).toLocaleString('id-ID'));

const pageLabel = (label) => {
    const l = label.toLowerCase();
    if (l.includes('previous')) return '« Sebelumnya';
    if (l.includes('next')) return 'Selanjutnya »';
    return label;
};

// ---- Buka shift ----
const openForm = useForm({ opening_balance: '', note: '' });
const submitOpen = () => {
    openForm.post(route('cashier-shift.open'), { preserveScroll: true });
};

// ---- Tutup shift ----
const showCloseModal = ref(false);
const closeForm = useForm({ closing_balance: '', note: '' });

const canClose = computed(
    () => props.currentShift && (props.currentShift.is_opener || props.canForceClose),
);

const openCloseModal = () => {
    closeForm.reset();
    closeForm.clearErrors();
    showCloseModal.value = true;
};

const submitClose = () => {
    closeForm.patch(route('cashier-shift.close', props.currentShift.id), {
        preserveScroll: true,
        onSuccess: () => (showCloseModal.value = false),
    });
};

// ---- Catat kas masuk/keluar ----
const cashForm = useForm({ type: 'in', amount: '', reason: '', note: '' });

const submitCash = () => {
    cashForm.post(route('cash-movements.store'), {
        preserveScroll: true,
        onSuccess: () => cashForm.reset('amount', 'reason', 'note'),
    });
};
</script>

<template>
    <Head title="Shift Kasir" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Shift Kasir</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                <div
                    v-if="page.props.flash?.success"
                    class="rounded-md bg-green-50 p-4 text-sm text-green-700"
                >
                    {{ page.props.flash.success }}
                </div>
                <div
                    v-if="page.props.flash?.error"
                    class="rounded-md bg-red-50 p-4 text-sm text-red-700"
                >
                    {{ page.props.flash.error }}
                </div>

                <!-- Tidak ada shift open -->
                <div v-if="!currentShift" class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900">Tidak Ada Shift Berjalan</h3>

                    <form v-if="canOpen" @submit.prevent="submitOpen" class="mt-4 grid grid-cols-3 gap-4">
                        <div>
                            <InputLabel value="Modal Awal" />
                            <TextInput
                                v-model="openForm.opening_balance"
                                type="number" min="0" step="0.01"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="openForm.errors.opening_balance" class="mt-2" />
                        </div>
                        <div class="col-span-2">
                            <InputLabel value="Catatan (opsional)" />
                            <TextInput v-model="openForm.note" type="text" class="mt-1 block w-full" />
                            <InputError :message="openForm.errors.note" class="mt-2" />
                        </div>
                        <div class="col-span-3">
                            <PrimaryButton :disabled="openForm.processing">Buka Shift</PrimaryButton>
                        </div>
                    </form>
                    <p v-else class="mt-2 text-sm text-gray-400">
                        Anda tidak berhak membuka shift di cabang ini.
                    </p>
                </div>

                <!-- Shift sedang berjalan -->
                <div v-else class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Shift Berjalan</h3>
                            <p class="text-sm text-gray-500">
                                Dibuka oleh {{ currentShift.opener_name }} • {{ currentShift.opened_at }}
                            </p>
                            <p class="mt-1 text-sm text-gray-700">
                                Modal awal: {{ rupiah(currentShift.opening_balance) }}
                            </p>
                        </div>
                        <SecondaryButton v-if="canClose" @click="openCloseModal">Tutup Shift</SecondaryButton>
                    </div>

                    <!-- Form kas masuk/keluar -->
                    <div v-if="canRecordCash" class="mt-6 border-t pt-4">
                        <p class="mb-2 text-sm font-medium text-gray-700">Catat Kas Masuk/Keluar</p>
                        <form @submit.prevent="submitCash" class="grid grid-cols-12 items-start gap-2">
                            <div class="col-span-2">
                                <select v-model="cashForm.type" :class="selectClass" class="mt-0!">
                                    <option value="in">Masuk</option>
                                    <option value="out">Keluar</option>
                                </select>
                            </div>
                            <div class="col-span-2">
                                <TextInput
                                    v-model="cashForm.amount"
                                    type="number" min="0.01" step="0.01"
                                    placeholder="Jumlah" class="w-full"
                                />
                                <InputError :message="cashForm.errors.amount" class="mt-1 text-xs" />
                            </div>
                            <div class="col-span-4">
                                <TextInput
                                    v-model="cashForm.reason"
                                    type="text"
                                    placeholder="Alasan (mis. setor_brankas)"
                                    class="w-full"
                                />
                                <InputError :message="cashForm.errors.reason" class="mt-1 text-xs" />
                            </div>
                            <div class="col-span-3">
                                <TextInput
                                    v-model="cashForm.note"
                                    type="text"
                                    placeholder="Catatan (opsional)"
                                    class="w-full"
                                />
                            </div>
                            <div class="col-span-1">
                                <PrimaryButton :disabled="cashForm.processing" class="w-full justify-center">
                                    +
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>

                    <!-- Riwayat kas shift ini -->
                    <div class="mt-6 border-t pt-4">
                        <p class="mb-2 text-sm font-medium text-gray-700">Riwayat Kas Shift Ini</p>
                        <table class="min-w-full text-sm">
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="m in currentShift.cash_movements" :key="m.id">
                                    <td class="py-1 text-gray-400">{{ m.created_at }}</td>
                                    <td class="py-1" :class="m.type === 'in' ? 'text-green-700' : 'text-red-600'">
                                        {{ m.type === 'in' ? 'Masuk' : 'Keluar' }}
                                    </td>
                                    <td class="py-1 text-right">{{ rupiah(m.amount) }}</td>
                                    <td class="py-1 text-gray-700">{{ m.reason }}</td>
                                    <td class="py-1 text-gray-400">{{ m.creator_name }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-if="currentShift.cash_movements.length === 0" class="py-4 text-center text-sm text-gray-400">
                            Belum ada catatan kas.
                        </p>
                    </div>
                </div>

                <!-- Riwayat shift ditutup (Admin/Owner) -->
                <section v-if="history" class="rounded-xl border border-border bg-surface p-4 shadow-sm sm:p-6">
                    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-text-primary">Riwayat Shift Ditutup</h3>
                            <p class="mt-0.5 text-sm text-text-secondary">Rekonsiliasi kas per shift.</p>
                        </div>
                        <span class="rounded-full bg-surface-muted px-2.5 py-1 text-xs font-medium text-text-secondary">
                            {{ history.total }} shift
                        </span>
                    </div>

                    <div class="space-y-3 md:hidden">
                        <article v-for="s in history.data" :key="s.id" class="rounded-lg border border-border bg-page/50 p-4 transition hover:border-brand-primary/40 hover:shadow-sm">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-semibold text-text-primary">{{ s.opener_name }}</p>
                                    <p class="mt-1 text-xs text-text-secondary">Dibuka {{ s.opened_at }}</p>
                                    <p class="text-xs text-text-secondary">Ditutup {{ s.closed_at }}</p>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="Number(s.difference) === 0 ? 'bg-status-success-soft text-status-success' : 'bg-status-danger-soft text-status-danger'">
                                    {{ Number(s.difference) === 0 ? 'Sesuai' : 'Selisih' }}
                                </span>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-3 border-t border-border pt-3">
                                <div>
                                    <p class="text-xs text-text-secondary">Modal awal</p>
                                    <p class="mt-1 text-sm font-medium tabular-nums text-text-primary">{{ rupiah(s.opening_balance) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-text-secondary">Kas akhir</p>
                                    <p class="mt-1 text-sm font-medium tabular-nums text-text-primary">{{ rupiah(s.closing_balance) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-text-secondary">Ekspektasi</p>
                                    <p class="mt-1 text-sm font-medium tabular-nums text-text-primary">{{ rupiah(s.expected_balance) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-text-secondary">Selisih</p>
                                    <p class="mt-1 text-sm font-semibold tabular-nums" :class="Number(s.difference) === 0 ? 'text-status-success' : 'text-status-danger'">{{ rupiah(s.difference) }}</p>
                                </div>
                            </div>
                            <p class="mt-3 border-t border-border pt-3 text-xs text-text-secondary">Ditutup oleh {{ s.closer_name ?? '-' }}</p>
                        </article>
                    </div>

                    <div class="hidden overflow-x-auto md:block">
                        <table class="min-w-220 w-full divide-y divide-border text-sm">
                            <thead>
                                <tr class="text-left text-xs font-semibold uppercase text-text-secondary">
                                    <th class="py-3 pr-3">Dibuka</th>
                                    <th class="py-3 pr-3">Ditutup</th>
                                    <th class="py-3 pr-3 text-right">Modal awal</th>
                                    <th class="py-3 pr-3 text-right">Kas akhir</th>
                                    <th class="py-3 pr-3 text-right">Ekspektasi</th>
                                    <th class="py-3 pr-3 text-right">Selisih</th>
                                    <th class="py-3 pr-3">Kasir</th>
                                    <th class="py-3">Ditutup oleh</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="s in history.data" :key="s.id">
                                    <td class="py-3 pr-3 text-text-secondary">{{ s.opened_at }}</td>
                                    <td class="py-3 pr-3 text-text-secondary">{{ s.closed_at }}</td>
                                    <td class="py-3 pr-3 text-right tabular-nums text-text-primary">{{ rupiah(s.opening_balance) }}</td>
                                    <td class="py-3 pr-3 text-right tabular-nums text-text-primary">{{ rupiah(s.closing_balance) }}</td>
                                    <td class="py-3 pr-3 text-right tabular-nums text-text-primary">{{ rupiah(s.expected_balance) }}</td>
                                    <td class="py-3 pr-3 text-right font-semibold tabular-nums" :class="Number(s.difference) === 0 ? 'text-status-success' : 'text-status-danger'">{{ rupiah(s.difference) }}</td>
                                    <td class="py-3 pr-3 font-medium text-text-primary">{{ s.opener_name }}</td>
                                    <td class="py-3 text-text-secondary">{{ s.closer_name ?? '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p v-if="history.data.length === 0" class="rounded-lg border border-dashed border-border py-10 text-center text-sm text-text-secondary">
                        Belum ada riwayat shift.
                    </p>

                    <div v-if="history.last_page > 1" class="mt-5 flex flex-wrap gap-1 border-t border-border pt-4">
                        <template v-for="link in history.links" :key="link.label">
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
        </div>

        <!-- Modal Tutup Shift -->
        <Modal :show="showCloseModal" @close="showCloseModal = false">
            <form @submit.prevent="submitClose" class="p-6">
                <h3 class="text-lg font-medium text-gray-900">
                    Tutup Shift {{ currentShift?.is_opener ? '' : '(Paksa)' }}
                </h3>

                <div class="mt-4">
                    <InputLabel value="Kas Fisik Akhir" />
                    <TextInput
                        v-model="closeForm.closing_balance"
                        type="number" min="0" step="0.01"
                        class="mt-1 block w-full"
                    />
                    <InputError :message="closeForm.errors.closing_balance" class="mt-2" />
                </div>

                <div class="mt-4">
                    <InputLabel value="Catatan (opsional)" />
                    <textarea v-model="closeForm.note" rows="2" :class="selectClass"></textarea>
                    <InputError :message="closeForm.errors.note" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showCloseModal = false">Batal</SecondaryButton>
                    <PrimaryButton :disabled="closeForm.processing">Tutup Shift</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>