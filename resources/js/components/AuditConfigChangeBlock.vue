<script setup lang="ts">
import { Circle } from 'lucide-vue-next';
import { computed, onMounted } from 'vue';
import { ensureLucideIconsLoaded, resolveLucideIcon } from '@/composables/useLucideIconRegistry';

const props = withDefaults(
    defineProps<{
        text: string;
        tone: 'previous' | 'updated';
    }>(),
    { tone: 'updated' },
);

type ParsedVariant = 'status' | 'priority' | 'category';

type ParsedRow =
    | {
          kind: 'parsed';
          variant: ParsedVariant;
          name: string;
          iconName: string;
          colorHex: string | null;
          handlerLabel: string;
      }
    | { kind: 'raw'; line: string };

const COMPACT_SEP = ' · ';

function normalizeIcon(raw: string): string {
    return raw === '—' || raw === '-' ? 'Circle' : raw;
}

function parseColorToken(raw: string): string | null {
    if (raw === '—' || raw === '-') {
        return null;
    }

    return /^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$/.test(raw) ? raw : null;
}

function parseCompactLine(line: string): ParsedRow | null {
    const trimmed = line.trim();
    if (trimmed.length === 0) {
        return null;
    }

    const withoutBullet = trimmed.replace(/^[\u2022•]\s*/, '');
    const parts = withoutBullet.split(COMPACT_SEP).map((p) => p.trim());

    if (parts.length === 4) {
        const [name, iconRaw, colorRaw, handlerLabel] = parts;
        if (name.length === 0) {
            return null;
        }

        return {
            kind: 'parsed',
            variant: 'status',
            name,
            iconName: normalizeIcon(iconRaw),
            colorHex: parseColorToken(colorRaw),
            handlerLabel,
        };
    }

    if (parts.length === 3) {
        const [name, iconRaw, colorRaw] = parts;
        if (name.length === 0) {
            return null;
        }

        return {
            kind: 'parsed',
            variant: 'priority',
            name,
            iconName: normalizeIcon(iconRaw),
            colorHex: parseColorToken(colorRaw),
            handlerLabel: '',
        };
    }

    if (parts.length === 2) {
        const [name, iconRaw] = parts;
        if (name.length === 0) {
            return null;
        }

        return {
            kind: 'parsed',
            variant: 'category',
            name,
            iconName: normalizeIcon(iconRaw),
            colorHex: null,
            handlerLabel: '',
        };
    }

    return null;
}

/** Legacy status one-liner. */
function parseLegacyStatusLine(line: string): ParsedRow | null {
    const trimmed = line.trim();
    const m = trimmed.match(
        /^(.+?)\s*[—–]\s*icon:\s*([^,]+),\s*color:\s*([^,]+),\s*handler_requirement:\s*(.+)$/i,
    );
    if (!m) {
        return null;
    }

    const name = m[1].trim();
    const iconNameRaw = m[2].trim();
    const colorRaw = m[3].trim();
    const handlerRaw = m[4].trim().toLowerCase();

    const iconName = iconNameRaw.length > 0 ? iconNameRaw : 'Circle';
    const colorHex = parseColorToken(colorRaw);
    const handlerLabel =
        handlerRaw === 'none' ? '—' : handlerRaw === 'optional' ? 'Optional' : handlerRaw === 'required' ? 'Required' : m[4].trim();

    return {
        kind: 'parsed',
        variant: 'status',
        name,
        iconName,
        colorHex,
        handlerLabel,
    };
}

/** Legacy priority one-liner. */
function parseLegacyPriorityLine(line: string): ParsedRow | null {
    const trimmed = line.trim();
    const m = trimmed.match(/^(.+?)\s*[—–]\s*icon:\s*([^,]+),\s*color:\s*(.+)$/i);
    if (!m) {
        return null;
    }

    const name = m[1].trim();
    const iconNameRaw = m[2].trim();
    const colorRaw = m[3].trim();

    return {
        kind: 'parsed',
        variant: 'priority',
        name,
        iconName: iconNameRaw.length > 0 ? iconNameRaw : 'Circle',
        colorHex: parseColorToken(colorRaw.trim()),
        handlerLabel: '',
    };
}

/** Legacy category one-liner. */
function parseLegacyCategoryLine(line: string): ParsedRow | null {
    const trimmed = line.trim();
    const m = trimmed.match(/^(.+?)\s*[—–]\s*icon:\s*(.+)$/i);
    if (!m) {
        return null;
    }

    const name = m[1].trim();
    const iconNameRaw = m[2].trim();

    return {
        kind: 'parsed',
        variant: 'category',
        name,
        iconName: iconNameRaw.length > 0 ? iconNameRaw : 'Circle',
        colorHex: null,
        handlerLabel: '',
    };
}

function parseLine(line: string): ParsedRow {
    return (
        parseCompactLine(line)
        ?? parseLegacyStatusLine(line)
        ?? parseLegacyPriorityLine(line)
        ?? parseLegacyCategoryLine(line)
        ?? { kind: 'raw', line }
    );
}

const rows = computed((): ParsedRow[] => {
    return props.text.split('\n').map((line) => parseLine(line)).filter((row) => {
        if (row.kind === 'raw') {
            return row.line.trim().length > 0;
        }

        return true;
    });
});

onMounted(() => {
    void ensureLucideIconsLoaded();
});

const isPrevious = computed(() => props.tone === 'previous');

const nameClass = computed(() =>
    isPrevious.value
        ? 'min-w-0 font-semibold text-destructive/90 line-through'
        : 'min-w-0 font-semibold text-emerald-800 dark:text-emerald-200',
);

const handlerClass = computed(() =>
    isPrevious.value
        ? 'text-destructive/75 line-through'
        : 'text-emerald-700/90 dark:text-emerald-300/90',
);

const rawLineClass = computed(() =>
    isPrevious.value
        ? 'whitespace-pre-wrap break-words font-mono text-[10px] leading-relaxed text-destructive/90 line-through'
        : 'whitespace-pre-wrap break-words font-mono text-[10px] leading-relaxed text-emerald-800 dark:text-emerald-200',
);

function iconUsesPriorityColor(item: ParsedRow & { kind: 'parsed' }): boolean {
    return item.variant !== 'category' && !!item.colorHex;
}
</script>

<template>
    <div class="space-y-2">
        <template v-for="(item, i) in rows" :key="i">
            <div
                v-if="item.kind === 'parsed'"
                class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[10px] leading-tight"
            >
                <span :class="nameClass">{{ item.name }}</span>
                <span
                    class="inline-flex items-center gap-1.5 rounded-md border border-border/50 bg-background/60 px-1.5 py-0.5"
                >
                    <component
                        :is="resolveLucideIcon(item.iconName, Circle)"
                        class="h-3.5 w-3.5 shrink-0"
                        :style="iconUsesPriorityColor(item) ? { color: item.colorHex! } : undefined"
                        :class="!iconUsesPriorityColor(item) ? 'text-muted-foreground' : ''"
                    />
                    <template v-if="item.variant === 'priority' || item.variant === 'status'">
                        <span
                            v-if="item.colorHex"
                            class="h-3.5 w-6 shrink-0 rounded border border-border/60 shadow-sm"
                            :style="{ backgroundColor: item.colorHex }"
                            :title="item.colorHex"
                        />
                        <span v-else class="text-[9px] text-muted-foreground">—</span>
                    </template>
                </span>
                <span
                    v-if="item.variant === 'status' && item.handlerLabel !== ''"
                    :class="handlerClass"
                >{{ item.handlerLabel }}</span>
            </div>
            <div v-else :class="rawLineClass">{{ item.line }}</div>
        </template>
    </div>
</template>
