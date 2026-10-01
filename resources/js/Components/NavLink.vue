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
        :class="[
            'inline-flex items-center border-b-2 px-1 pt-1 text-sm font-semibold leading-5 transition duration-150 ease-in-out focus:outline-none',
            active
                ? 'border-brand-primary text-text-primary'
                : 'border-transparent text-text-secondary hover:border-border hover:text-text-primary focus:border-border focus:text-text-primary',
        ]"
    >
        <slot />
    </Link>
</template>