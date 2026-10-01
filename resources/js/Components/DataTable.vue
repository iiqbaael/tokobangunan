<script setup>
defineProps({
    // [{ key: 'name', label: 'Nama', align: 'left' | 'right', numeric: true }]
    columns: {
        type: Array,
        required: true,
    },
    // Array of row objects. Row harus punya `id` unik untuk :key.
    rows: {
        type: Array,
        required: true,
    },
    emptyMessage: {
        type: String,
        default: "Belum ada data.",
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

defineEmits(["row-click"]);
</script>

<template>
    <div class="overflow-x-auto rounded-md border border-border bg-surface">
        <table class="min-w-full divide-y divide-border">
            <thead class="bg-surface-muted">
                <tr>
                    <th
                        v-for="col in columns"
                        :key="col.key"
                        class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-text-secondary"
                        :class="col.numeric ? 'text-right' : 'text-left'"
                    >
                        {{ col.label }}
                    </th>
                </tr>
            </thead>

            <tbody class="divide-y divide-border">
                <!-- Loading skeleton -->
                <template v-if="loading">
                    <tr v-for="n in 5" :key="`skeleton-${n}`">
                        <td v-for="col in columns" :key="col.key" class="px-4 py-3">
                            <div class="h-4 w-full max-w-[140px] animate-pulse rounded bg-surface-muted" />
                        </td>
                    </tr>
                </template>

                <!-- Empty state -->
                <tr v-else-if="rows.length === 0">
                    <td :colspan="columns.length" class="px-4 py-12 text-center">
                        <p class="text-sm text-text-secondary">{{ emptyMessage }}</p>
                        <div v-if="$slots['empty-action']" class="mt-3">
                            <slot name="empty-action" />
                        </div>
                    </td>
                </tr>

                <!-- Data rows -->
                <tr
                    v-else
                    v-for="row in rows"
                    :key="row.id"
                    class="hover:bg-surface-muted"
                    :class="{ 'cursor-pointer': $attrs.onclick || $listeners?.['row-click'] }"
                    @click="$emit('row-click', row)"
                >
                    <td
                        v-for="col in columns"
                        :key="col.key"
                        class="px-4 py-3 text-sm text-text-primary"
                        :class="col.numeric ? 'text-right tabular-nums' : 'text-left'"
                    >
                        <!-- Slot custom per kolom (mis. badge status, tombol aksi):
                             <template #cell(status)="{ row }"><StatusBadge :status="row.status" /></template> -->
                        <slot :name="`cell(${col.key})`" :row="row" :value="row[col.key]">
                            {{ row[col.key] }}
                        </slot>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>