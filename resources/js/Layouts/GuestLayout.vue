<script setup>
import { Link } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';
</script>

<template>
    <div class="min-h-screen bg-page text-text-primary">
        <div class="grid min-h-screen lg:grid-cols-[minmax(18rem,0.85fr)_minmax(0,1.15fr)]">
            <aside class="hidden min-h-screen flex-col justify-between border-r border-white/10 bg-brand-dark px-10 py-10 text-white lg:flex xl:px-14">
                <Link href="/" class="flex items-center gap-3">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-primary text-xs font-bold text-white shadow-sm">
                        TB
                    </span>
                    <span class="text-sm font-semibold">TB Sumber Baru</span>
                </Link>

                <div>
                    <p class="text-sm text-white/60">Sistem Operasional</p>
                    <p class="mt-2 text-2xl font-semibold">TB Sumber Baru</p>
                </div>

                <p class="text-xs text-white/50">Akses pengguna terdaftar</p>
            </aside>

            <main class="relative flex min-h-screen items-center justify-center px-4 py-8 sm:px-8">
                <div class="absolute right-4 top-4 sm:right-8 sm:top-8">
                    <ThemeToggle />
                </div>
                <div class="w-full max-w-md">
                    <Link href="/" class="mb-6 flex items-center justify-center gap-3 lg:hidden">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-primary text-xs font-bold text-white shadow-sm">
                            TB
                        </span>
                        <span class="text-sm font-semibold text-text-primary">TB Sumber Baru</span>
                    </Link>

                    <section class="rounded-xl border border-border bg-surface px-6 py-7 shadow-xl shadow-brand-dark/5 sm:px-9 sm:py-9">
                        <slot />
                    </section>
                </div>
            </main>
        </div>
    </div>
</template>
