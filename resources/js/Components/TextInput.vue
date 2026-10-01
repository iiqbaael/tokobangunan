<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="rounded-lg border-border bg-surface text-text-primary shadow-sm placeholder:text-text-disabled focus:border-brand-primary focus:ring-brand-primary disabled:cursor-not-allowed disabled:bg-surface-muted disabled:text-text-disabled"
        v-model="model"
        ref="input"
    />
</template>
