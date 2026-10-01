<script setup>
import { onBeforeUnmount, onMounted, ref } from "vue";
import { router } from "@inertiajs/vue3";

const visible = ref(false);
const DELAY_MS = 150; // navigasi cepat tidak memunculkan loader (anti-kedip)

let timer = null;
let removeStart = null;
let removeFinish = null;

function show() {
    clearTimeout(timer);
    timer = setTimeout(() => (visible.value = true), DELAY_MS);
}

function hide() {
    clearTimeout(timer);
    visible.value = false;
}

onMounted(() => {
    removeStart = router.on("start", (event) => {
        const visit = event.detail.visit;
        // Lewati prefetch & request latar belakang
        if (visit.prefetch || visit.async) return;
        show();
    });
    removeFinish = router.on("finish", hide);
});

onBeforeUnmount(() => {
    hide();
    removeStart?.();
    removeFinish?.();
});
</script>

<template>
    <Transition name="loader">
        <div
            v-if="visible"
            class="fixed inset-0 z-100 flex items-center justify-center bg-surface/75 backdrop-blur-sm"
            role="status"
            aria-live="polite"
        >
            <div class="flex flex-col items-center gap-4">
                <div class="relative h-16 w-16">
                    <span
                        class="absolute inset-0 rounded-full border-4 border-brand-primary/15"
                    ></span>
                    <span
                        class="loader-ring absolute inset-0 rounded-full border-4 border-transparent border-t-brand-primary"
                    ></span>
                    <span
                        class="absolute inset-3 flex items-center justify-center rounded-xl bg-brand-primary text-sm font-bold text-white shadow-md shadow-brand-primary/30 loader-pulse"
                    >
                        SB
                        <!-- <img src="/logo.png" alt="" class="h-6 w-6 object-contain" /> -->
                    </span>
                </div>
                <div
                    class="flex items-center gap-1 text-sm font-medium text-text-secondary"
                >
                    <span>Memuat</span>
                    <span class="loader-dot">.</span>
                    <span class="loader-dot" style="animation-delay: 0.15s"
                        >.</span
                    >
                    <span class="loader-dot" style="animation-delay: 0.3s"
                        >.</span
                    >
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.loader-enter-active,
.loader-leave-active {
    transition: opacity 0.2s ease;
}
.loader-enter-from,
.loader-leave-to {
    opacity: 0;
}

.loader-ring {
    animation: loader-spin 0.9s linear infinite;
}
.loader-pulse {
    animation: loader-pulse 1.2s ease-in-out infinite;
}
.loader-dot {
    display: inline-block;
    animation: loader-bounce 1s ease-in-out infinite;
}

@keyframes loader-spin {
    to {
        transform: rotate(360deg);
    }
}
@keyframes loader-pulse {
    0%,
    100% {
        transform: scale(1);
    }
    50% {
        transform: scale(0.88);
    }
}
@keyframes loader-bounce {
    0%,
    60%,
    100% {
        transform: translateY(0);
        opacity: 0.4;
    }
    30% {
        transform: translateY(-3px);
        opacity: 1;
    }
}
</style>
