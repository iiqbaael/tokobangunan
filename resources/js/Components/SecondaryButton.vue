<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center justify-center rounded-lg border border-border bg-surface px-4 py-2 text-sm font-semibold text-text-primary shadow-sm transition hover:border-text-disabled hover:bg-surface-muted hover:shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
    >
        <slot />
    </button>
</template>
