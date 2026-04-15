import { AlertCircle, AlertTriangle, ArrowUpCircle, Circle, Code, HardDrive, HelpCircle, Key, Network, Shield } from 'lucide-vue-next';
import type { Component } from 'vue';
import { markRaw, ref, shallowRef } from 'vue';

/**
 * Small set of icons bundled immediately (used before the full Lucide chunk loads).
 */
export const lucideStaticIconMap: Record<string, Component> = {
    Network: markRaw(Network),
    HardDrive: markRaw(HardDrive),
    Code: markRaw(Code),
    Key: markRaw(Key),
    Shield: markRaw(Shield),
    HelpCircle: markRaw(HelpCircle),
    AlertCircle: markRaw(AlertCircle),
    AlertTriangle: markRaw(AlertTriangle),
    ArrowUpCircle: markRaw(ArrowUpCircle),
    Circle: markRaw(Circle),
};

/**
 * Grows to the full Lucide set after {@link ensureLucideIconsLoaded} resolves.
 */
export const lucideAllIconMap = shallowRef<Record<string, Component>>({
    ...lucideStaticIconMap,
});

let lucideIconsLoadPromise: Promise<void> | null = null;

/** True while the dynamic import of `lucide-vue-next` is in flight. */
export const lucideIconsLoading = ref(false);

/**
 * Lazy-loads the entire `lucide-vue-next` package once and fills {@link lucideAllIconMap}.
 * Safe to call from multiple places; shares one in-flight promise.
 */
export function ensureLucideIconsLoaded(): Promise<void> {
    if (lucideIconsLoadPromise) {
        return lucideIconsLoadPromise;
    }

    lucideIconsLoading.value = true;
    lucideIconsLoadPromise = (async () => {
        try {
            const mod = await import('lucide-vue-next');
            lucideAllIconMap.value = Object.fromEntries(
                Object.entries(mod)
                    .filter(([k, v]) => /^[A-Z]/.test(k) && !k.endsWith('Icon') && v != null)
                    .map(([k, v]) => [k, markRaw(v as Component)]),
            );
        } finally {
            lucideIconsLoading.value = false;
        }
    })();

    return lucideIconsLoadPromise;
}

export function resolveLucideIcon(name: string, fallback: Component): Component {
    return (lucideAllIconMap.value[name] ?? fallback) as Component;
}
