<template>
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="mb-3 flex items-center gap-2 text-gray-400">
            <el-icon :size="20"><component :is="icon" /></el-icon>
            <span class="text-sm font-medium text-gray-500">{{ label }}</span>
        </div>
        <div class="text-2xl font-bold" :class="valueClass">{{ value }}</div>
        <div v-if="hint" class="mt-1 text-xs text-gray-400">{{ hint }}</div>
    </div>
</template>

<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    icon: { type: [String, Object], default: 'DataLine' },
    hint: { type: String, default: '' },
    valueClass: { type: String, default: 'text-gray-800' },
});
</script>