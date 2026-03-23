<script setup lang="ts">
import { computed, ref } from 'vue';

const props = defineProps<{
    data: { name: string; count: number; hex: string; color: string }[];
    total: number;
}>();

const hoveredIndex = ref<number | null>(null);

const CX = 100;
const CY = 100;
const OUTER_R = 74;
const INNER_R = 48;
const GAP_DEG = 1.8;
const HOVER_OFFSET = 6;

function polarToXY(r: number, angleDeg: number) {
    const rad = (angleDeg - 90) * (Math.PI / 180);
    return { x: CX + r * Math.cos(rad), y: CY + r * Math.sin(rad) };
}

function buildPath(outerR: number, innerR: number, startAngle: number, endAngle: number) {
    const o1 = polarToXY(outerR, startAngle);
    const o2 = polarToXY(outerR, endAngle);
    const i2 = polarToXY(innerR, endAngle);
    const i1 = polarToXY(innerR, startAngle);
    const large = endAngle - startAngle > 180 ? 1 : 0;
    return [
        `M ${o1.x} ${o1.y}`,
        `A ${outerR} ${outerR} 0 ${large} 1 ${o2.x} ${o2.y}`,
        `L ${i2.x} ${i2.y}`,
        `A ${innerR} ${innerR} 0 ${large} 0 ${i1.x} ${i1.y}`,
        'Z',
    ].join(' ');
}

const segments = computed(() => {
    let cursor = 0;
    return props.data.map((item, i) => {
        const sweep = (item.count / props.total) * 360;
        const start = cursor + GAP_DEG / 2;
        const end = cursor + sweep - GAP_DEG / 2;
        cursor += sweep;

        const midAngle = (start + end) / 2;
        const rad = (midAngle - 90) * (Math.PI / 180);
        const tx = HOVER_OFFSET * Math.cos(rad);
        const ty = HOVER_OFFSET * Math.sin(rad);

        return {
            ...item,
            index: i,
            // Visual path at normal size — animates on hover
            path: buildPath(OUTER_R, INNER_R, start, end),
            // Hit area at full hover size, never moves — drives mouse events
            hitPath: buildPath(OUTER_R + HOVER_OFFSET, INNER_R, start, end),
            transform: `translate(${tx.toFixed(2)}, ${ty.toFixed(2)})`,
            pct: Math.round((item.count / props.total) * 100),
        };
    });
});

const active = computed(() =>
    hoveredIndex.value !== null ? segments.value[hoveredIndex.value] : null
);
</script>

<template>
    <div class="flex flex-col gap-5">
        <!-- SVG Donut -->
        <div class="relative mx-auto w-full max-w-[220px]">
            <svg viewBox="0 0 200 200" class="w-full h-auto overflow-visible">
                <!-- Segments -->
                <g v-for="seg in segments" :key="seg.name">
                    <!-- Visual path: animates, no pointer events -->
                    <path
                        :d="seg.path"
                        :fill="seg.hex"
                        :transform="hoveredIndex === seg.index ? seg.transform : ''"
                        :style="{
                            opacity: hoveredIndex !== null && hoveredIndex !== seg.index ? 0.25 : 1,
                            transition: 'transform 0.2s ease, opacity 0.2s ease',
                            pointerEvents: 'none',
                            filter: hoveredIndex === seg.index ? `drop-shadow(0 0 6px ${seg.hex}88)` : 'none',
                        }"
                    />
                    <!-- Hit area: static at full hover size, invisible, drives events -->
                    <path
                        :d="seg.hitPath"
                        fill="transparent"
                        style="cursor: pointer;"
                        @mouseenter="hoveredIndex = seg.index"
                        @mouseleave="hoveredIndex = null"
                    />
                </g>

                <!-- Center label -->
                <g style="pointer-events: none;">
                    <transition name="fade-label" mode="out-in">
                        <!-- Hovered state -->
                        <g v-if="active" :key="active.name">
                            <circle :cx="CX" :cy="CY" r="44" :fill="active.hex" fill-opacity="0.08" />
                            <text
                                :x="CX" :y="CY - 16"
                                text-anchor="middle"
                                font-size="9.5"
                                font-weight="600"
                                letter-spacing="0.04em"
                                fill="currentColor"
                                opacity="0.5"
                            >{{ active.name.toUpperCase() }}</text>
                            <text
                                :x="CX" :y="CY + 14"
                                text-anchor="middle"
                                font-size="26"
                                font-weight="700"
                                fill="currentColor"
                            >{{ active.count }}</text>
                            <text
                                :x="CX" :y="CY + 28"
                                text-anchor="middle"
                                font-size="10"
                                font-weight="500"
                                fill="currentColor"
                                opacity="0.45"
                            >{{ active.pct }}%</text>
                        </g>

                        <!-- Default state -->
                        <g v-else key="total">
                            <text
                                :x="CX" :y="CY - 12"
                                text-anchor="middle"
                                font-size="9.5"
                                font-weight="600"
                                letter-spacing="0.05em"
                                fill="currentColor"
                                opacity="0.4"
                            >TOTAL</text>
                            <text
                                :x="CX" :y="CY + 18"
                                text-anchor="middle"
                                font-size="28"
                                font-weight="700"
                                fill="currentColor"
                            >{{ total }}</text>
                        </g>
                    </transition>
                </g>
            </svg>
        </div>

        <!-- Legend -->
        <div class="grid grid-cols-2 gap-x-3 gap-y-1.5">
            <button
                v-for="seg in segments"
                :key="seg.name"
                class="flex items-center gap-2 min-w-0 rounded-md px-2 py-1.5 text-left transition-all duration-150"
                :class="hoveredIndex !== null && hoveredIndex !== seg.index
                    ? 'opacity-30'
                    : 'hover:bg-muted/60'"
                @mouseenter="hoveredIndex = seg.index"
                @mouseleave="hoveredIndex = null"
            >
                <div
                    class="w-2.5 h-2.5 rounded-full shrink-0 transition-transform duration-150"
                    :style="{ backgroundColor: seg.hex }"
                    :class="hoveredIndex === seg.index ? 'scale-[1.4]' : ''"
                ></div>
                <span class="text-xs text-muted-foreground truncate flex-1">{{ seg.name }}</span>
                <span
                    class="text-xs font-bold tabular-nums shrink-0 transition-colors duration-150"
                    :style="hoveredIndex === seg.index ? { color: seg.hex } : {}"
                >{{ seg.pct }}%</span>
            </button>
        </div>
    </div>
</template>

<style scoped>
.fade-label-enter-active,
.fade-label-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}
.fade-label-enter-from,
.fade-label-leave-to {
    opacity: 0;
    transform: scale(0.92);
}
</style>
