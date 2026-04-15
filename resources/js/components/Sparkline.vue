<script setup lang="ts">
import { computed, ref, useId } from 'vue';

const props = withDefaults(
    defineProps<{
        data: number[];
        labels?: string[];
        valueSuffix?: string;
        width?: number;
        height?: number;
        stroke?: string;
        strokeWidth?: number;
    }>(),
    {
        valueSuffix: '',
        width: 100,
        height: 48,
        stroke: 'currentColor',
        strokeWidth: 2,
    },
);

const width = props.width;
const height = props.height;
const stroke = props.stroke;
const strokeWidth = props.strokeWidth;
const valueSuffix = props.valueSuffix;

const gradientId = `spark-grad-${useId().replace(/\W/g, '')}`;

const hoverIndex = ref<number | null>(null);
const tipX = ref(0);
const tipY = ref(0);

const minV = computed(() => (props.data.length ? Math.min(...props.data) : 0));
const maxV = computed(() => (props.data.length ? Math.max(...props.data) : 0));
const range = computed(() => maxV.value - minV.value || 1);

function xAt(i: number): number {
    const n = props.data.length;
    if (n < 2) {
        return 0;
    }
    return (i / (n - 1)) * width;
}

function yAt(i: number): number {
    return height - ((props.data[i] - minV.value) / range.value) * (height - 4) - 2;
}

const pathData = computed(() => {
    if (props.data.length < 2) {
        return '';
    }

    let d = '';
    const MAX_POINTS = 30; // Max sparkline length for dashboard to ensure consistent path commands for CSS morphing
    const n = props.data.length;

    for (let i = 0; i < MAX_POINTS; i++) {
        const idx = Math.min(i, n - 1);
        const x = xAt(idx);
        const y = yAt(idx);

        if (i === 0) {
            d += `M ${x} ${y}`;
        } else {
            const prevIdx = Math.min(i - 1, n - 1);
            const prevX = xAt(prevIdx);
            const prevY = yAt(prevIdx);
            const cx = (prevX + x) / 2;
            d += ` C ${cx} ${prevY}, ${cx} ${y}, ${x} ${y}`;
        }
    }
    return d;
});

const areaData = computed(() => {
    if (!pathData.value) {
        return '';
    }
    return `${pathData.value} L ${width} ${height} L 0 ${height} Z`;
});

function formatValue(v: number): string {
    if (valueSuffix === 'h') {
        return `${v}${valueSuffix}`;
    }
    return String(Math.round(v));
}

function pointerToIndex(clientX: number, rect: DOMRect): number {
    const n = props.data.length;
    if (n < 2) {
        return 0;
    }
    const x = clientX - rect.left;
    const w = rect.width || 1;
    const t = Math.max(0, Math.min(1, x / w));
    return Math.round(t * (n - 1));
}

function onSvgMove(e: MouseEvent): void {
    if (props.data.length < 2) {
        return;
    }
    const svg = e.currentTarget as SVGSVGElement;
    const rect = svg.getBoundingClientRect();
    hoverIndex.value = pointerToIndex(e.clientX, rect);
    tipX.value = e.clientX;
    tipY.value = e.clientY;
}

function onSvgLeave(): void {
    hoverIndex.value = null;
}

const hoverLabel = computed(() => {
    if (hoverIndex.value === null) {
        return '';
    }
    const i = hoverIndex.value;
    return props.labels?.[i] ?? `Day ${i + 1}`;
});

const hoverValue = computed(() => {
    if (hoverIndex.value === null) {
        return '';
    }
    return formatValue(props.data[hoverIndex.value]);
});

const crosshairX = computed(() => (hoverIndex.value === null ? 0 : xAt(hoverIndex.value)));
const crosshairY = computed(() => (hoverIndex.value === null ? 0 : yAt(hoverIndex.value)));
</script>

<template>
    <div class="relative inline-flex max-w-full touch-none">
        <svg
            :width="width"
            :height="height"
            :viewBox="`0 0 ${width} ${height}`"
            class="max-w-full cursor-crosshair overflow-visible"
            role="img"
            :aria-label="'Trend chart'"
            @mousemove="onSvgMove"
            @mouseleave="onSvgLeave"
        >
            <defs>
                <linearGradient :id="gradientId" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" :stop-color="stroke" stop-opacity="0.2" />
                    <stop offset="100%" :stop-color="stroke" stop-opacity="0" />
                </linearGradient>
            </defs>
            <path :d="areaData" :fill="`url(#${gradientId})`" class="transition-all duration-500 ease-out" />
            <path
                :d="pathData"
                :stroke="stroke"
                :stroke-width="strokeWidth"
                fill="none"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="transition-all duration-500 ease-out"
            />
            <rect :width="width" :height="height" fill="transparent" class="outline-none" />
            <line
                v-if="hoverIndex !== null && data.length >= 2"
                :x1="crosshairX"
                y1="0"
                :x2="crosshairX"
                :y2="height"
                class="text-muted-foreground/45"
                stroke="currentColor"
                stroke-width="1"
                stroke-dasharray="3 3"
                pointer-events="none"
            />
            <circle
                v-if="hoverIndex !== null && data.length >= 2"
                :cx="crosshairX"
                :cy="crosshairY"
                r="4"
                class="fill-background"
                :stroke="stroke"
                stroke-width="2"
                pointer-events="none"
            />
        </svg>

        <Teleport to="body">
            <div
                v-if="hoverIndex !== null && data.length >= 2"
                class="pointer-events-none fixed z-[200] min-w-[4.5rem] rounded-lg border border-border/60 bg-popover px-2.5 py-1.5 text-popover-foreground shadow-lg"
                :style="{
                    left: `${tipX}px`,
                    top: `${tipY}px`,
                    transform: 'translate(-50%, calc(-100% - 10px))',
                }"
            >
                <p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                    {{ hoverLabel }}
                </p>
                <p class="text-sm font-bold tabular-nums tracking-tight">{{ hoverValue }}</p>
            </div>
        </Teleport>
    </div>
</template>
