<template>
    <div>
        <div class="flex items-end gap-1" style="height: 200px">
            <div
                v-for="(item, index) in items"
                :key="item.date"
                class="group relative flex flex-1 flex-col items-center justify-end transition"
                :title="`${item.label}: ${displayValue(item.value)}`"
            >
                <div class="mb-1 hidden rounded bg-gray-800 px-1.5 py-0.5 text-[10px] font-medium text-white group-hover:block">
                    {{ displayValue(item.value) }}
                </div>
                <div
                    class="w-full max-w-[26px] rounded-t"
                    :style="{
                        height: `${heightFor(item.value)}px`,
                        backgroundColor: color,
                        opacity: isMax(item.value) ? 1 : Math.max(0.35, item.value > 0 ? 0.55 : 0.15),
                    }"
                ></div>
                <div
                    v-if="index % 2 === 0"
                    class="mt-1.5 -rotate-45 text-[9px] text-gray-400"
                    :style="{ transform: 'rotate(-45deg)' }"
                >
                    {{ item.label }}
                </div>
            </div>
        </div>
        <div class="mt-1 flex items-end justify-between px-2 text-[9px] text-gray-400">
            <span>{{ items[0]?.label }}</span>
            <span>{{ items[items.length - 1]?.label }}</span>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { formatCurrency } from '../utils/helpers';

const props = defineProps({
    items: { type: Array, default: () => [] },
    color: { type: String, default: '#0ea5e9' },
    suffix: { type: String, default: '' },
    format: { type: String, default: 'number' },
});

const maxValue = computed(() => Math.max(1, ...props.items.map((i) => i.value)));

function heightFor(value) {
    return Math.max(2, (value / maxValue.value) * 170);
}

function isMax(value) {
    return value === maxValue.value && value > 0;
}

function displayValue(value) {
    if (props.format === 'currency') {
        return formatCurrency(value);
    }
    return `${value}${props.suffix}`;
}
</script>