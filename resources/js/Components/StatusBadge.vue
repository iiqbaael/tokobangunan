<script setup>
import { computed } from "vue";

const props = defineProps({
    // Nilai status mentah dari backend, mis. 'open', 'pending', 'void', 1, 0
    status: {
        type: [String, Number, Boolean],
        required: true,
    },
    // Label yang ditampilkan. Kalau tidak diisi, dicoba tebak dari `status`.
    label: {
        type: String,
        default: null,
    },
});

// Mapping status -> varian warna (Design System §2.3, "acuan wajib, jangan
// diinterpretasi ulang per halaman"). Tambahkan key baru di sini kalau ada
// status baru, jangan bikin warna custom langsung di halaman.
const STATUS_MAP = {
    // Shift Kasir
    open: { variant: "success", label: "Open" },
    closed: { variant: "neutral", label: "Closed" },
    // Adjustment
    pending: { variant: "warning", label: "Pending" },
    approved: { variant: "success", label: "Approved" },
    rejected: { variant: "danger", label: "Rejected" },
    // Transfer
    draft: { variant: "neutral", label: "Draft" },
    sent: { variant: "info", label: "Sent" },
    partial: { variant: "warning", label: "Partial" },
    received: { variant: "success", label: "Received" },
    cancelled: { variant: "danger", label: "Cancelled" },
    // Penjualan
    completed: { variant: "success", label: "Completed" },
    void: { variant: "danger", label: "Void" },
    // Cash movement
    in: { variant: "success", label: "Masuk" },
    out: { variant: "neutral", label: "Keluar" },
    // Aktif / nonaktif (is_active, boolean, atau 1/0)
    true: { variant: "success", label: "Aktif" },
    1: { variant: "success", label: "Aktif" },
    false: { variant: "neutral", label: "Nonaktif" },
    0: { variant: "neutral", label: "Nonaktif" },
};

const variantClasses = {
    success: "bg-status-success-soft text-status-success",
    warning: "bg-status-warning-soft text-status-warning",
    danger: "bg-status-danger-soft text-status-danger",
    neutral: "bg-status-neutral-soft text-status-neutral",
    info: "bg-status-info-soft text-status-info",
};

const resolved = computed(() => {
    const key = typeof props.status === "string" ? props.status.toLowerCase() : props.status;
    const found = STATUS_MAP[key];
    return {
        variant: found?.variant ?? "neutral",
        label: props.label ?? found?.label ?? String(props.status),
    };
});
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold whitespace-nowrap"
        :class="variantClasses[resolved.variant]"
    >
        {{ resolved.label }}
    </span>
</template>