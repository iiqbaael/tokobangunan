<script setup>
import { Link } from "@inertiajs/vue3";

const props = defineProps({
    // Bentuk standar Laravel paginator: { data: [...], links: [...], meta: {...} }
    // Cukup lempar object hasil ->paginate() langsung dari controller.
    paginator: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div
        v-if="paginator.last_page > 1"
        class="flex flex-col items-center justify-between gap-3 border-t border-border px-4 py-3 sm:flex-row"
    >
        <p class="text-sm text-text-secondary">
            Menampilkan <span class="font-semibold text-text-primary">{{ paginator.from ?? 0 }}</span>
            –
            <span class="font-semibold text-text-primary">{{ paginator.to ?? 0 }}</span>
            dari
            <span class="font-semibold text-text-primary">{{ paginator.total }}</span>
            data
        </p>

        <nav class="flex flex-wrap items-center gap-1">
            <template v-for="(link, i) in paginator.links" :key="i">
                <span
                    v-if="link.url === null"
                    class="rounded-md px-3 py-1.5 text-sm text-text-disabled"
                    v-html="link.label"
                />
                <Link
                    v-else
                    :href="link.url"
                    preserve-scroll
                    class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                    :class="
                        link.active
                            ? 'bg-brand-primary text-brand-dark'
                            : 'text-text-secondary hover:bg-surface-muted hover:text-text-primary'
                    "
                    v-html="link.label"
                />
            </template>
        </nav>
    </div>
</template>