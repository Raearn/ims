<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    data: number[];
    width?: number;
    height?: number;
    stroke?: string;
    strokeWidth?: number;
}>();

const width = props.width || 100;
const height = props.height || 48;
const stroke = props.stroke || 'currentColor';
const strokeWidth = props.strokeWidth || 2;

const pathData = computed(() => {
    if (props.data.length < 2) return '';
    
    const max = Math.max(...props.data);
    const min = Math.min(...props.data);
    const range = max - min || 1;
    
    // Use cubic bezier approach for smooth curve
    let d = '';
    for (let i = 0; i < props.data.length; i++) {
        const value = props.data[i];
        const x = (i / (props.data.length - 1)) * width;
        const y = height - ((value - min) / range) * (height - 4) - 2; // padding
        
        if (i === 0) {
            d += `M ${x} ${y}`;
        } else {
            const prevValue = props.data[i - 1];
            const prevX = ((i - 1) / (props.data.length - 1)) * width;
            const prevY = height - ((prevValue - min) / range) * (height - 4) - 2;
            const cx = (prevX + x) / 2;
            d += ` C ${cx} ${prevY}, ${cx} ${y}, ${x} ${y}`;
        }
    }
    return d;
});

const areaData = computed(() => {
    if (!pathData.value) return '';
    return `${pathData.value} L ${width} ${height} L 0 ${height} Z`;
});
</script>

<template>
    <svg :width="width" :height="height" :viewBox="`0 0 ${width} ${height}`" class="overflow-visible">
        <defs>
            <linearGradient :id="`gradient-${stroke}`" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" :stop-color="stroke" stop-opacity="0.2" />
                <stop offset="100%" :stop-color="stroke" stop-opacity="0" />
            </linearGradient>
        </defs>
        <path :d="areaData" :fill="`url(#gradient-${stroke})`" />
        <path :d="pathData" :stroke="stroke" :stroke-width="strokeWidth" fill="none" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
</template>
