<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    customers: Array,
    canManage: Boolean,
});

const page = usePage();

// ---- Modal tambah ----
const showCreateModal = ref(false);
const createForm = useForm({ name: '', phone: '', address: '' });

const openCreate = () => {
    createForm.reset();
    createForm.clearErrors();
    showCreateModal.value = true;
};

const submitCreate = () => {
    createForm.post(route('customers.store'), {
        onSuccess: () => (showCreateModal.value = false),
    });
};

// ---- Modal edit ----
const showEditModal = ref(false);
const editForm = useForm({ id: null, name: '', phone: '', address: '', is_active: true });

const openEdit = (customer) => {
    editForm.reset();
    editForm.clearErrors();
    editForm.id = customer.id;
    editForm.name = customer.name;
    editForm.phone = customer.phone ?? '';
    editForm.address = customer.address ?? '';
    editForm.is_active = customer.is_active;
    showEditModal.value = true;
};

const submitEdit = () => {
    editForm.patch(route('customers.update', editForm.id), {
        onSuccess: () => (showEditModal.value = false),
    });
};

// ---- Hapus ----
const confirmDelete = (customer) => {
    if (! confirm(`Hapus pelanggan "${customer.name}"?`)) {
        return;
    }

    useForm({}).delete(route('customers.destroy', customer.id));
};
</script>

<template>
    <Head title="Pelanggan" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Pelanggan
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
                <div
                    v-if="page.props.flash?.success"
                    class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700"
                >
                    {{ page.props.flash.success }}
                </div>
                <div
                    v-if="page.props.flash?.error"
                    class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700"
                >
                    {{ page.props.flash.error }}
                </div>

                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900">
                            Daftar Pelanggan
                        </h3>
                        <PrimaryButton v-if="canManage" @click="openCreate">
                            + Tambah Pelanggan
                        </PrimaryButton>
                    </div>

                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="py-2 text-left text-sm font-medium text-gray-500">Nama</th>
                                <th class="py-2 text-left text-sm font-medium text-gray-500">Telepon</th>
                                <th class="py-2 text-left text-sm font-medium text-gray-500">Alamat</th>
                                <th class="py-2 text-left text-sm font-medium text-gray-500">Status</th>
                                <th v-if="canManage" class="py-2 text-right text-sm font-medium text-gray-500">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="customer in customers" :key="customer.id">
                                <td class="py-2 text-sm text-gray-900">{{ customer.name }}</td>
                                <td class="py-2 text-sm text-gray-500">{{ customer.phone || '-' }}</td>
                                <td class="py-2 text-sm text-gray-500">{{ customer.address || '-' }}</td>
                                <td class="py-2 text-sm">
                                    <span
                                        :class="customer.is_active
                                            ? 'text-green-700'
                                            : 'text-gray-400'"
                                    >
                                        {{ customer.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td v-if="canManage" class="py-2 text-right text-sm">
                                    <button
                                        class="mr-3 text-indigo-600 hover:text-indigo-900"
                                        @click="openEdit(customer)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        class="text-red-600 hover:text-red-900"
                                        @click="confirmDelete(customer)"
                                    >
                                        Hapus
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="customers.length === 0">
                                <td colspan="5" class="py-4 text-center text-sm text-gray-400">
                                    Belum ada pelanggan.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal Tambah -->
        <Modal :show="showCreateModal" @close="showCreateModal = false">
            <form @submit.prevent="submitCreate" class="p-6">
                <h3 class="text-lg font-medium text-gray-900">Tambah Pelanggan</h3>

                <div class="mt-4">
                    <InputLabel for="create_name" value="Nama" />
                    <TextInput
                        id="create_name"
                        v-model="createForm.name"
                        type="text"
                        class="mt-1 block w-full"
                        autofocus
                    />
                    <InputError :message="createForm.errors.name" class="mt-2" />
                </div>

                <div class="mt-4">
                    <InputLabel for="create_phone" value="Telepon" />
                    <TextInput
                        id="create_phone"
                        v-model="createForm.phone"
                        type="text"
                        class="mt-1 block w-full"
                    />
                    <InputError :message="createForm.errors.phone" class="mt-2" />
                </div>

                <div class="mt-4">
                    <InputLabel for="create_address" value="Alamat" />
                    <textarea
                        id="create_address"
                        v-model="createForm.address"
                        rows="2"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <InputError :message="createForm.errors.address" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showCreateModal = false">Batal</SecondaryButton>
                    <PrimaryButton :disabled="createForm.processing">Simpan</PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Modal Edit -->
        <Modal :show="showEditModal" @close="showEditModal = false">
            <form @submit.prevent="submitEdit" class="p-6">
                <h3 class="text-lg font-medium text-gray-900">Edit Pelanggan</h3>

                <div class="mt-4">
                    <InputLabel for="edit_name" value="Nama" />
                    <TextInput
                        id="edit_name"
                        v-model="editForm.name"
                        type="text"
                        class="mt-1 block w-full"
                    />
                    <InputError :message="editForm.errors.name" class="mt-2" />
                </div>

                <div class="mt-4">
                    <InputLabel for="edit_phone" value="Telepon" />
                    <TextInput
                        id="edit_phone"
                        v-model="editForm.phone"
                        type="text"
                        class="mt-1 block w-full"
                    />
                    <InputError :message="editForm.errors.phone" class="mt-2" />
                </div>

                <div class="mt-4">
                    <InputLabel for="edit_address" value="Alamat" />
                    <textarea
                        id="edit_address"
                        v-model="editForm.address"
                        rows="2"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <InputError :message="editForm.errors.address" class="mt-2" />
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
    </AuthenticatedLayout>
</template>