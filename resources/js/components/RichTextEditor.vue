<script setup lang="ts">
import Placeholder from '@tiptap/extension-placeholder';
import TextAlign from '@tiptap/extension-text-align';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import {
    AlignCenter,
    AlignLeft,
    AlignRight,
    Bold,
    Heading2,
    Italic,
    List,
    ListOrdered,
    Minus,
    Redo,
    Underline as UnderlineIcon,
    Undo,
} from 'lucide-vue-next';
import { watch } from 'vue';

const props = defineProps<{ modelValue: string; placeholder?: string }>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit,
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        Placeholder.configure({ placeholder: props.placeholder ?? 'Write a description...' }),
    ],
    editorProps: {
        attributes: {
            class: 'prose prose-sm dark:prose-invert max-w-none min-h-[120px] px-3 py-2.5 text-sm focus:outline-none',
        },
    },
    onUpdate({ editor }) {
        emit('update:modelValue', editor.getHTML());
    },
});

watch(
    () => props.modelValue,
    (val) => {
        if (editor.value && editor.value.getHTML() !== val) {
            editor.value.commands.setContent(val, false);
        }
    },
);

type Level = 1 | 2 | 3;
</script>

<template>
    <div
        class="overflow-hidden rounded-md border border-muted-foreground/20 bg-transparent shadow-sm transition-all focus-within:border-primary/40 focus-within:ring-1 focus-within:ring-primary/50"
    >
        <!-- Toolbar -->
        <div class="flex flex-wrap items-center gap-0.5 border-b border-muted-foreground/15 bg-muted/40 px-2 py-1.5">
            <!-- History -->
            <div class="flex items-center">
                <button
                    type="button"
                    @click="editor?.chain().focus().undo().run()"
                    :disabled="!editor?.can().undo()"
                    class="toolbar-btn"
                    title="Undo"
                >
                    <Undo class="h-3.5 w-3.5" />
                </button>
                <button
                    type="button"
                    @click="editor?.chain().focus().redo().run()"
                    :disabled="!editor?.can().redo()"
                    class="toolbar-btn"
                    title="Redo"
                >
                    <Redo class="h-3.5 w-3.5" />
                </button>
            </div>

            <div class="mx-1 h-4 w-px bg-border/60" />

            <!-- Heading -->
            <button
                type="button"
                @click="
                    editor
                        ?.chain()
                        .focus()
                        .toggleHeading({ level: 2 as Level })
                        .run()
                "
                :class="['toolbar-btn', editor?.isActive('heading', { level: 2 }) && 'toolbar-btn-active']"
                title="Heading"
            >
                <Heading2 class="h-3.5 w-3.5" />
            </button>

            <div class="mx-1 h-4 w-px bg-border/60" />

            <!-- Formatting -->
            <button
                type="button"
                @click="editor?.chain().focus().toggleBold().run()"
                :class="['toolbar-btn', editor?.isActive('bold') && 'toolbar-btn-active']"
                title="Bold"
            >
                <Bold class="h-3.5 w-3.5" />
            </button>
            <button
                type="button"
                @click="editor?.chain().focus().toggleItalic().run()"
                :class="['toolbar-btn', editor?.isActive('italic') && 'toolbar-btn-active']"
                title="Italic"
            >
                <Italic class="h-3.5 w-3.5" />
            </button>
            <button
                type="button"
                @click="editor?.chain().focus().toggleUnderline().run()"
                :class="['toolbar-btn', editor?.isActive('underline') && 'toolbar-btn-active']"
                title="Underline"
            >
                <UnderlineIcon class="h-3.5 w-3.5" />
            </button>

            <div class="mx-1 h-4 w-px bg-border/60" />

            <!-- Lists -->
            <button
                type="button"
                @click="editor?.chain().focus().toggleBulletList().run()"
                :class="['toolbar-btn', editor?.isActive('bulletList') && 'toolbar-btn-active']"
                title="Bullet List"
            >
                <List class="h-3.5 w-3.5" />
            </button>
            <button
                type="button"
                @click="editor?.chain().focus().toggleOrderedList().run()"
                :class="['toolbar-btn', editor?.isActive('orderedList') && 'toolbar-btn-active']"
                title="Ordered List"
            >
                <ListOrdered class="h-3.5 w-3.5" />
            </button>

            <div class="mx-1 h-4 w-px bg-border/60" />

            <!-- Alignment -->
            <button
                type="button"
                @click="editor?.chain().focus().setTextAlign('left').run()"
                :class="['toolbar-btn', editor?.isActive({ textAlign: 'left' }) && 'toolbar-btn-active']"
                title="Align Left"
            >
                <AlignLeft class="h-3.5 w-3.5" />
            </button>
            <button
                type="button"
                @click="editor?.chain().focus().setTextAlign('center').run()"
                :class="['toolbar-btn', editor?.isActive({ textAlign: 'center' }) && 'toolbar-btn-active']"
                title="Align Center"
            >
                <AlignCenter class="h-3.5 w-3.5" />
            </button>
            <button
                type="button"
                @click="editor?.chain().focus().setTextAlign('right').run()"
                :class="['toolbar-btn', editor?.isActive({ textAlign: 'right' }) && 'toolbar-btn-active']"
                title="Align Right"
            >
                <AlignRight class="h-3.5 w-3.5" />
            </button>

            <div class="mx-1 h-4 w-px bg-border/60" />

            <!-- Divider -->
            <button type="button" @click="editor?.chain().focus().setHorizontalRule().run()" class="toolbar-btn" title="Divider">
                <Minus class="h-3.5 w-3.5" />
            </button>
        </div>

        <!-- Editor content -->
        <EditorContent :editor="editor" />
    </div>
</template>

<style scoped>
.toolbar-btn {
    @apply flex h-7 w-7 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:cursor-not-allowed disabled:opacity-30;
}
.toolbar-btn-active {
    @apply bg-primary/10 text-primary;
}

:deep(.tiptap p.is-editor-empty:first-child::before) {
    content: attr(data-placeholder);
    @apply pointer-events-none float-left h-0 text-muted-foreground/50;
}

:deep(.tiptap) {
    @apply text-foreground;
}

:deep(.tiptap h2) {
    @apply mb-1 mt-2 text-base font-bold;
}

:deep(.tiptap ul) {
    @apply list-disc space-y-0.5 pl-5;
}

:deep(.tiptap ol) {
    @apply list-decimal space-y-0.5 pl-5;
}

:deep(.tiptap hr) {
    @apply my-2 border-border;
}

:deep(.tiptap strong) {
    @apply font-bold;
}

:deep(.tiptap em) {
    @apply italic;
}
</style>
