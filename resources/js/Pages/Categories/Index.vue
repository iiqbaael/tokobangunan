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
import { Head, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    categories: Array,
    canManage: Boolean,
});

const page = usePage();

// ---- Modal tambah ----
const showCreateModal = ref(false);
const createForm = useForm({ name: '' });

const openCreate = () => {
    createForm.reset();
    createForm.clearErrors();
    showCreateModal.value = true;
};

const submitCreate = () => {
    createForm.post(route('categories.store'), {
        onSuccess: () => (showCreateModal.value = false),
    });
};

// ---- Modal edit ----
const showEditModal = ref(false);
const editForm = useForm({ id: null, name: '', is_active: true });

const openEdit = (category) => {
    editForm.reset();
    editForm.clearErrors();
    editForm.id = category.id;
    editForm.name = category.name;
    editForm.is_active = category.is_active;
    showEditModal.value = true;
};

const submitEdit = () => {
    editForm.patch(route('categories.update', editForm.id), {
        onSuccess: () => (showEditModal.value = false),
    });
};

// ---- Hapus ----
const confirmDelete = (category) => {
    if (! confirm(`Hapus kategori "${category.name}"?`)) {
        return;
    }

    useForm({}).delete(route('categories.destroy', category.id));
};
</script>

<template>
    <Head title="Kategori" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Kategori
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
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
                            Daftar Kategori
                        </h3>
                        <PrimaryButton v-if="canManage" @click="openCreate">
                            + Tambah Kategori
                        </PrimaryButton>
                    </div>

                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="py-2 text-left text-sm font-medium text-gray-500">Nama</th>
                                <th class="py-2 text-left text-sm font-medium text-gray-500">Status</th>
                                <th v-if="canManage" class="py-2 text-right text-sm font-medium text-gray-500">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="category in categories" :key="category.id">
                                <td class="py-2 text-sm text-gray-900">{{ category.name }}</td>
                                <td class="py-2 text-sm">
                                    <span
                                        :class="category.is_active
                                            ? 'text-green-700'
                                            : 'text-gray-400'"
                                    >
                                        {{ category.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td v-if="canManage" class="py-2 text-right text-sm">
                                    <button
                                        class="mr-3 text-indigo-600 hover:text-indigo-900"
                                        @click="openEdit(category)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        class="text-red-600 hover:text-red-900"
                                        @click="confirmDelete(category)"
                                    >
                                        Hapus
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="categories.length === 0">
                                <td colspan="3" class="py-4 text-center text-sm text-gray-400">
                                    Belum ada kategori.
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
                <h3 class="text-lg font-medium text-gray-900">Tambah Kategori</h3>

                <div class="mt-4">
                    <InputLabel for="create_name" value="Nama Kategori" />
                    <TextInput
                        id="create_name"
                        v-model="createForm.name"
                        type="text"
                        class="mt-1 block w-full"
                        autofocus
                    />
                    <InputError :message="createForm.errors.name" class="mt-2" />
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
                <h3 class="text-lg font-medium text-gray-900">Edit Kategori</h3>

                <div class="mt-4">
                    <InputLabel for="edit_name" value="Nama Kategori" />
                    <TextInput
                        id="edit_name"
                        v-model="editForm.name"
                        type="text"
                        class="mt-1 block w-full"
                    />
                    <InputError :message="editForm.errors.name" class="mt-2" />
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