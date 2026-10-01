<script setup>
import { Link } from "@inertiajs/vue3";

defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <Link
        :href="href"
        class="flex w-full items-start border-l-4 py-2 pe-4 ps-3 text-start text-base font-medium transition duration-150 ease-in-out focus:outline-none"
        :class="
            active
                ? 'border-brand-primary bg-brand-primary-soft text-brand-secondary focus:border-brand-primary-hover focus:bg-brand-primary-soft focus:text-brand-secondary'
                : 'border-transparent text-text-secondary hover:border-border hover:bg-surface-muted hover:text-text-primary focus:border-border focus:bg-surface-muted focus:text-text-primary'
        "
    >
        <slot />
    </Link>
</template>