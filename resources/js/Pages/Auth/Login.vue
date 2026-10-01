<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LogIn } from 'lucide-vue-next';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Masuk" />

        <div v-if="status" class="mb-5 rounded-lg border border-status-success bg-status-success-soft p-3 text-sm font-medium text-status-success">
            {{ status }}
        </div>

        <div class="mb-7">
            <h1 class="text-2xl font-bold text-text-primary">Masuk ke akun Anda</h1>
            <p class="mt-1.5 text-sm text-text-secondary">Gunakan akun terdaftar untuk melanjutkan.</p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <InputLabel for="email" value="Email" />
                <TextInput
                    id="email"
                    type="email"
                    class="mt-1.5 block w-full rounded-lg px-3.5 py-2.5"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" value="Password" />
                <TextInput
                    id="password"
                    type="password"
                    class="mt-1.5 block w-full rounded-lg px-3.5 py-2.5"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <label class="flex cursor-pointer items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-sm text-text-secondary">Ingat saya</span>
                </label>

                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="rounded text-sm font-medium text-brand-primary hover:text-brand-secondary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary focus-visible:ring-offset-2"
                >
                    Lupa kata sandi?
                </Link>
            </div>

            <PrimaryButton :disabled="form.processing" class="w-full gap-2 rounded-lg py-2.5">
                    <LogIn :size="16" />
                    {{ form.processing ? 'Memproses...' : 'Masuk' }}
                </PrimaryButton>
        </form>
    </GuestLayout>
</template>
