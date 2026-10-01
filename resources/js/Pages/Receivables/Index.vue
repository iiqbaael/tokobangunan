<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Check, HandCoins, NotebookPen, Search } from 'lucide-vue-next';

const props = defineProps({
    customers: Array, // [{ id, name, phone, balance }]
    reminders: Array, // [{ id, customer_id, type, body, amount, follow_up_date, is_done, created_by }]
});

const page = usePage();

const selectClass =
    'mt-1 block w-full rounded-lg border-border bg-surface text-sm text-text-primary shadow-sm focus:border-brand-primary focus:ring-brand-primary';

const rupiah = (value) =>
    value === null || value === undefined ? '-' : 'Rp ' + Number(value).toLocaleString('id-ID');

const customerName = (id) => props.customers.find((c) => c.id === id)?.name ?? '-';
const totalReceivable = computed(() => props.customers.reduce((sum, customer) => sum + Math.max(Number(customer.balance) || 0, 0), 0));
const indebtedCustomers = computed(() => props.customers.filter((customer) => Number(customer.balance) > 0).length);

const search = ref('');
const filteredCustomers = computed(() => {
    if (!search.value.trim()) return props.customers;
    const q = search.value.toLowerCase();
    return props.customers.filter(
        (c) => c.name.toLowerCase().includes(q) || (c.phone ?? '').includes(q),
    );
});

const pendingReminders = computed(() => props.reminders.filter((r) => !r.is_done));
const doneReminders = computed(() => props.reminders.filter((r) => r.is_done));

// ---- Modal: Bayar (pelunasan) ----
const showPayModal = ref(false);
const selectedCustomer = ref(null);
const payForm = useForm({ amount: '', cash: true, note: '' });

const openPayModal = (customer) => {
    selectedCustomer.value = customer;
    payForm.reset();
    payForm.clearErrors();
    payForm.cash = true;
    showPayModal.value = true;
};

const submitPay = () => {
    payForm.post(route('receivables.pay', selectedCustomer.value.id), {
        preserveScroll: true,
        onSuccess: () => (showPayModal.value = false),
    });
};

// ---- Modal: Koreksi Pelunasan ----
const showCorrectModal = ref(false);
const correctForm = useForm({ amount: '', was_cash: true, note: '' });

const openCorrectModal = (customer) => {
    selectedCustomer.value = customer;
    correctForm.reset();
    correctForm.clearErrors();
    correctForm.was_cash = true;
    showCorrectModal.value = true;
};

const submitCorrect = () => {
    correctForm.post(route('receivables.correct', selectedCustomer.value.id), {
        preserveScroll: true,
        onSuccess: () => (showCorrectModal.value = false),
    });
};

// ---- Modal: Catatan (titipan / pengingat umum) ----
const showNoteModal = ref(false);
const noteForm = useForm({ type: 'general', body: '', amount: '', follow_up_date: '' });

const openNoteModal = (customer) => {
    selectedCustomer.value = customer;
    noteForm.reset();
    noteForm.clearErrors();
    noteForm.type = 'general';
    showNoteModal.value = true;
};

const submitNote = () => {
    noteForm.post(route('receivables.notes.store', selectedCustomer.value.id), {
        preserveScroll: true,
        onSuccess: () => (showNoteModal.value = false),
    });
};

// ---- Toggle status catatan/pengingat ----
const toggleNote = (note) => {
    useForm({}).patch(route('receivables.notes.toggle', note.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Piutang Pelanggan" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Piutang Pelanggan</h2>
        </template>

        <div class="mx-auto max-w-7xl space-y-5 px-4 py-6 lg:px-6">
                <div
                    v-if="page.props.flash?.success"
                    class="rounded-lg border border-status-success bg-status-success-soft p-4 text-sm text-status-success"
                >
                    {{ page.props.flash.success }}
                </div>
                <div
                    v-if="page.props.flash?.error"
                    class="rounded-lg border border-status-danger bg-status-danger-soft p-4 text-sm text-status-danger"
                >
                    {{ page.props.flash.error }}
                </div>

                <!-- Saldo Piutang per Pelanggan -->
                <section class="rounded-xl border border-border bg-surface p-4 shadow-sm sm:p-6">
                    <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-accent-pink-soft text-accent-pink">
                                <HandCoins :size="18" />
                            </span>
                            <div>
                                <h3 class="text-lg font-semibold text-text-primary">Saldo Piutang</h3>
                                <p class="mt-0.5 text-sm text-text-secondary">{{ indebtedCustomers }} pelanggan memiliki saldo berjalan.</p>
                                <p class="mt-2 text-xl font-bold tabular-nums text-text-primary">{{ rupiah(totalReceivable) }}</p>
                            </div>
                        </div>
                        <label class="flex w-full items-center gap-2 rounded-lg border border-border bg-page px-3 py-2 transition focus-within:border-brand-primary focus-within:ring-2 focus-within:ring-brand-primary-soft sm:max-w-xs">
                            <Search :size="16" class="shrink-0 text-text-disabled" />
                            <input v-model="search" type="search" placeholder="Cari nama atau telepon..." class="w-full border-0 bg-transparent p-0 text-sm text-text-primary placeholder:text-text-disabled focus:outline-none focus:ring-0" />
                        </label>
                    </div>

                    <div class="space-y-3 md:hidden">
                        <article v-for="c in filteredCustomers" :key="c.id" class="rounded-lg border border-border bg-page/50 p-4 transition hover:border-brand-primary/40 hover:shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-text-primary">{{ c.name }}</p>
                                    <p class="mt-1 text-xs text-text-secondary">{{ c.phone ?? 'Telepon belum dicatat' }}</p>
                                </div>
                                <span class="shrink-0 text-right text-sm font-bold tabular-nums" :class="Number(c.balance) > 0 ? 'text-status-danger' : 'text-text-secondary'">{{ rupiah(c.balance) }}</span>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2 border-t border-border pt-3">
                                <button class="rounded-lg bg-brand-primary px-3 py-1.5 text-xs font-semibold text-white hover:brightness-95" @click="openPayModal(c)">Bayar</button>
                                <button class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-text-secondary hover:bg-surface-muted" @click="openCorrectModal(c)">Koreksi</button>
                                <button class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-brand-primary hover:bg-brand-primary-soft" @click="openNoteModal(c)">+ Catatan</button>
                            </div>
                        </article>
                    </div>

                    <div class="hidden overflow-x-auto md:block">
                        <table class="min-w-180 w-full divide-y divide-border text-sm">
                            <thead>
                                <tr class="text-left text-xs font-semibold uppercase text-text-secondary">
                                    <th class="py-3 pr-3">Pelanggan</th>
                                    <th class="py-3 pr-3">Telepon</th>
                                    <th class="py-3 pr-3 text-right">Saldo piutang</th>
                                    <th class="py-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="c in filteredCustomers" :key="c.id">
                                    <td class="py-3 pr-3 font-semibold text-text-primary">{{ c.name }}</td>
                                    <td class="py-3 pr-3 text-text-secondary">{{ c.phone ?? '-' }}</td>
                                    <td class="py-3 pr-3 text-right font-semibold tabular-nums" :class="Number(c.balance) > 0 ? 'text-status-danger' : 'text-text-secondary'">{{ rupiah(c.balance) }}</td>
                                    <td class="py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <button class="rounded-lg bg-brand-primary px-2.5 py-1.5 text-xs font-semibold text-white hover:brightness-95" @click="openPayModal(c)">Bayar</button>
                                            <button class="rounded-lg border border-border px-2.5 py-1.5 text-xs font-semibold text-text-secondary hover:bg-surface-muted" @click="openCorrectModal(c)">Koreksi</button>
                                            <button class="rounded-lg border border-border px-2.5 py-1.5 text-xs font-semibold text-brand-primary hover:bg-brand-primary-soft" @click="openNoteModal(c)">+ Catatan</button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p v-if="filteredCustomers.length === 0" class="rounded-lg border border-dashed border-border py-10 text-center text-sm text-text-secondary">
                        Tidak ada pelanggan yang cocok.
                    </p>
                </section>

                <!-- Pengingat / Titipan Belum Selesai -->
                <section class="rounded-xl border border-border bg-surface p-4 shadow-sm sm:p-6">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-accent-amber-soft text-accent-amber"><NotebookPen :size="18" /></span>
                        <div>
                            <h3 class="text-lg font-semibold text-text-primary">Pengingat &amp; Titipan Aktif</h3>
                            <p class="mt-0.5 text-sm text-text-secondary">{{ pendingReminders.length }} catatan perlu ditindaklanjuti.</p>
                        </div>
                    </div>

                    <div class="mt-4 space-y-3 md:hidden">
                        <article v-for="r in pendingReminders" :key="r.id" class="rounded-lg border border-border bg-page/50 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-text-primary">{{ customerName(r.customer_id) }}</p>
                                    <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="r.type === 'titipan' ? 'bg-status-info-soft text-status-info' : 'bg-status-warning-soft text-status-warning'">{{ r.type === 'titipan' ? 'Titipan' : 'Pengingat' }}</span>
                                </div>
                                <span v-if="r.amount" class="shrink-0 text-sm font-semibold tabular-nums text-text-primary">{{ rupiah(r.amount) }}</span>
                            </div>
                            <p class="mt-3 text-sm text-text-secondary">{{ r.body }}</p>
                            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-border pt-3 text-xs text-text-secondary">
                                <span>Tindak lanjut: {{ r.follow_up_date ?? 'Belum dijadwalkan' }}</span>
                                <button class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 font-semibold text-status-success hover:bg-status-success-soft" @click="toggleNote(r)"><Check :size="14" /> Selesai</button>
                            </div>
                        </article>
                    </div>

                    <div class="mt-4 hidden overflow-x-auto md:block">
                    <table class="min-w-220 w-full divide-y divide-border text-sm">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase text-text-secondary">
                                <th class="py-3 pr-3">Pelanggan</th>
                                <th class="py-3 pr-3">Jenis</th>
                                <th class="py-3 pr-3">Catatan</th>
                                <th class="py-3 pr-3 text-right">Jumlah</th>
                                <th class="py-3 pr-3">Tindak lanjut</th>
                                <th class="py-3 pr-3">Dibuat oleh</th>
                                <th class="py-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="r in pendingReminders" :key="r.id">
                                <td class="py-3 pr-3 font-medium text-text-primary">{{ customerName(r.customer_id) }}</td>
                                <td class="py-3 pr-3"><span class="rounded-full px-2 py-1 text-[11px] font-semibold" :class="r.type === 'titipan' ? 'bg-status-info-soft text-status-info' : 'bg-status-warning-soft text-status-warning'">{{ r.type === 'titipan' ? 'Titipan' : 'Pengingat' }}</span></td>
                                <td class="max-w-sm py-3 pr-3 text-text-secondary">{{ r.body }}</td>
                                <td class="py-3 pr-3 text-right tabular-nums text-text-primary">{{ rupiah(r.amount) }}</td>
                                <td class="py-3 pr-3 text-text-secondary">{{ r.follow_up_date ?? '-' }}</td>
                                <td class="py-3 pr-3 text-text-secondary">{{ r.created_by }}</td>
                                <td class="py-3">
                                    <button class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-status-success hover:bg-status-success-soft" @click="toggleNote(r)"><Check :size="14" /> Selesai</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    </div>

                    <p v-if="pendingReminders.length === 0" class="mt-4 rounded-lg border border-dashed border-border py-10 text-center text-sm text-text-secondary">
                        Tidak ada pengingat/titipan aktif.
                    </p>
                </section>

                <!-- Riwayat Selesai (opsional, ringkas) -->
                <section v-if="doneReminders.length > 0" class="rounded-xl border border-border bg-surface p-4 shadow-sm sm:p-6">
                    <h3 class="text-base font-semibold text-text-primary">Riwayat Selesai</h3>
                    <ul class="mt-3 divide-y divide-border">
                        <li v-for="r in doneReminders" :key="r.id" class="flex flex-wrap items-start justify-between gap-2 py-3 text-sm">
                            <span class="min-w-0 text-text-secondary"><strong class="font-semibold text-text-primary">{{ customerName(r.customer_id) }}</strong> · {{ r.body }}</span>
                            <button class="shrink-0 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-brand-primary hover:bg-brand-primary-soft" @click="toggleNote(r)">Buka lagi</button>
                        </li>
                    </ul>
                </section>
        </div>

        <!-- Modal: Bayar -->
        <Modal :show="showPayModal" @close="showPayModal = false">
            <form @submit.prevent="submitPay" class="p-5 sm:p-6">
                <h3 class="text-lg font-semibold text-text-primary">
                    Pelunasan Piutang — {{ selectedCustomer?.name }}
                </h3>
                <p class="mt-1 text-sm text-text-secondary">
                    Saldo saat ini: {{ rupiah(selectedCustomer?.balance) }}
                </p>

                <div class="mt-4">
                    <InputLabel value="Jumlah Bayar" />
                    <TextInput
                        v-model="payForm.amount"
                        type="number" min="0.01" step="0.01"
                        class="mt-1 block w-full"
                    />
                    <InputError :message="payForm.errors.amount" class="mt-2" />
                </div>

                <div class="mt-4 flex items-start gap-2 rounded-lg bg-surface-muted p-3">
                    <input id="pay-cash" type="checkbox" v-model="payForm.cash" class="mt-0.5 rounded border-border text-brand-primary focus:ring-brand-primary" />
                    <label for="pay-cash" class="text-sm text-text-secondary">
                        Tunai (masuk ke kas shift open cabang aktif)
                    </label>
                </div>
                <InputError :message="payForm.errors.cash" class="mt-1" />

                <div class="mt-4">
                    <InputLabel value="Catatan (opsional)" />
                    <textarea v-model="payForm.note" rows="2" :class="selectClass"></textarea>
                    <InputError :message="payForm.errors.note" class="mt-2" />
                </div>

                <div class="mt-6 flex flex-wrap justify-end gap-2 border-t border-border pt-4">
                    <SecondaryButton @click="showPayModal = false">Batal</SecondaryButton>
                    <PrimaryButton :disabled="payForm.processing">Simpan Pelunasan</PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Modal: Koreksi -->
        <Modal :show="showCorrectModal" @close="showCorrectModal = false">
            <form @submit.prevent="submitCorrect" class="p-5 sm:p-6">
                <h3 class="text-lg font-semibold text-text-primary">
                    Koreksi Pelunasan — {{ selectedCustomer?.name }}
                </h3>
                <p class="mt-1 text-sm text-text-secondary">
                    Membatalkan sebagian/seluruh pelunasan sebelumnya (baris pembalik, §2 no. 20).
                </p>

                <div class="mt-4">
                    <InputLabel value="Jumlah yang Dibatalkan" />
                    <TextInput
                        v-model="correctForm.amount"
                        type="number" min="0.01" step="0.01"
                        class="mt-1 block w-full"
                    />
                    <InputError :message="correctForm.errors.amount" class="mt-2" />
                </div>

                <div class="mt-4 flex items-start gap-2 rounded-lg bg-surface-muted p-3">
                    <input id="correct-cash" type="checkbox" v-model="correctForm.was_cash" class="mt-0.5 rounded border-border text-brand-primary focus:ring-brand-primary" />
                    <label for="correct-cash" class="text-sm text-text-secondary">
                        Pelunasan yang dikoreksi dulunya tunai (kas keluar dari shift open)
                    </label>
                </div>
                <InputError :message="correctForm.errors.was_cash" class="mt-1" />

                <div class="mt-4">
                    <InputLabel value="Catatan (opsional)" />
                    <textarea v-model="correctForm.note" rows="2" :class="selectClass"></textarea>
                    <InputError :message="correctForm.errors.note" class="mt-2" />
                </div>

                <div class="mt-6 flex flex-wrap justify-end gap-2 border-t border-border pt-4">
                    <SecondaryButton @click="showCorrectModal = false">Batal</SecondaryButton>
                    <PrimaryButton :disabled="correctForm.processing">Simpan Koreksi</PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Modal: Catatan (Titipan/Pengingat) -->
        <Modal :show="showNoteModal" @close="showNoteModal = false">
            <form @submit.prevent="submitNote" class="p-5 sm:p-6">
                <h3 class="text-lg font-semibold text-text-primary">
                    Catatan Baru — {{ selectedCustomer?.name }}
                </h3>

                <div class="mt-4">
                    <InputLabel value="Jenis" />
                    <select v-model="noteForm.type" :class="selectClass">
                        <option value="general">Pengingat Umum</option>
                        <option value="titipan">Titipan Uang</option>
                    </select>
                    <InputError :message="noteForm.errors.type" class="mt-2" />
                </div>

                <div class="mt-4">
                    <InputLabel value="Isi Catatan" />
                    <textarea v-model="noteForm.body" rows="3" :class="selectClass"></textarea>
                    <InputError :message="noteForm.errors.body" class="mt-2" />
                </div>

                <div v-if="noteForm.type === 'titipan'" class="mt-4">
                    <InputLabel value="Jumlah Titipan" />
                    <TextInput
                        v-model="noteForm.amount"
                        type="number" min="0.01" step="0.01"
                        class="mt-1 block w-full"
                    />
                    <InputError :message="noteForm.errors.amount" class="mt-2" />
                </div>

                <div class="mt-4">
                    <InputLabel value="Tanggal Tindak Lanjut (opsional)" />
                    <TextInput
                        v-model="noteForm.follow_up_date"
                        type="date"
                        class="mt-1 block w-full"
                    />
                    <InputError :message="noteForm.errors.follow_up_date" class="mt-2" />
                </div>

                <div class="mt-6 flex flex-wrap justify-end gap-2 border-t border-border pt-4">
                    <SecondaryButton @click="showNoteModal = false">Batal</SecondaryButton>
                    <PrimaryButton :disabled="noteForm.processing">Simpan Catatan</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>