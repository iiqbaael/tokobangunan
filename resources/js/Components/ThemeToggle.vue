<script setup>
import { computed, ref } from 'vue';
import { Moon, Sun } from 'lucide-vue-next';

const theme = ref(document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light');
const isDark = computed(() => theme.value === 'dark');
const label = computed(() => isDark.value ? 'Aktifkan tema terang' : 'Aktifkan tema gelap');

function toggleTheme() {
    theme.value = isDark.value ? 'light' : 'dark';
    document.documentElement.dataset.theme = theme.value;

    try {
        localStorage.setItem('tbs-theme', theme.value);
    } catch {
        // Theme still applies for the current page when storage is unavailable.
    }
}
</script>

<template>
    <button
        type="button"
        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-border bg-surface text-text-secondary transition hover:border-brand-primary/50 hover:bg-surface-muted hover:text-brand-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary"
        :aria-label="label"
        :aria-pressed="isDark"
        :title="label"
        @click="toggleTheme"
    >
        <Sun v-if="isDark" :size="17" />
        <Moon v-else :size="17" />
    </button>
</template>