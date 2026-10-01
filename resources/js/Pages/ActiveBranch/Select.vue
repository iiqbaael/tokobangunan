<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    branches: Array,
    activeBranchId: Number,
});

const form = useForm({
    branch_id: props.activeBranchId,
});

const submit = () => {
    form.patch(route('active-branch.update'));
};
</script>

<template>
    <Head title="Pilih Cabang Aktif" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Pilih Cabang Aktif
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <p class="mb-4 text-sm text-gray-600">
                        Semua operasi POS, shift, dan kas akan memakai cabang yang dipilih di sini
                        selama sesi login ini berlangsung.
                    </p>

                    <form @submit.prevent="submit" class="space-y-4">
                        <div
                            v-for="branch in branches"
                            :key="branch.id"
                            class="flex items-center"
                        >
                            <input
                                :id="`branch-${branch.id}`"
                                v-model="form.branch_id"
                                type="radio"
                                name="branch_id"
                                :value="branch.id"
                                class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <label
                                :for="`branch-${branch.id}`"
                                class="ml-3 block text-sm text-gray-900"
                            >
                                {{ branch.name }}
                                <span class="text-gray-400">({{ branch.code }})</span>
                            </label>
                        </div>

                        <InputError :message="form.errors.branch_id" />

                        <div class="flex items-center gap-4 pt-2">
                            <PrimaryButton :disabled="form.processing || !form.branch_id">
                                Simpan Cabang Aktif
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>