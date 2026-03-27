<script setup lang="ts">
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import Image from '@tiptap/extension-image';
import { ref, watch } from 'vue';
import {
    Bold, Italic, Underline as UnderlineIcon,
    List, ListOrdered, ImageIcon, Undo, Redo,
} from 'lucide-vue-next';
import { compressImage } from '@/lib/utils';
import { laravelFetch } from '@/lib/laravelFetch';

const props = defineProps<{ modelValue: string; placeholder?: string }>();
const emit = defineEmits<{ 'update:modelValue': [value: string]; 'focus': [] }>();

const imageInputRef = ref<HTMLInputElement | null>(null);
const isUploading = ref(false);
const compressionInfo = ref<{ before: number; after: number } | null>(null);
let compressionInfoTimer: ReturnType<typeof setTimeout> | null = null;

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit,
        Placeholder.configure({ placeholder: props.placeholder ?? 'Write a comment...' }),
        Image.configure({ inline: false, allowBase64: false }),
    ],
    editorProps: {
        attributes: {
            class: 'prose prose-sm dark:prose-invert max-w-none min-h-[80px] px-3 py-2.5 text-sm focus:outline-none',
        },
    },
    onUpdate({ editor }) {
        emit('update:modelValue', editor.getHTML());
    },
    onFocus() {
        emit('focus');
    },
});

watch(() => props.modelValue, (val) => {
    if (editor.value && editor.value.getHTML() !== val) {
        editor.value.commands.setContent(val, false);
    }
});

async function handleImageUpload(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file || !editor.value) return;

    isUploading.value = true;
    try {
        const compressed = await compressImage(file);

        if (compressed !== file) {
            compressionInfo.value = { before: file.size, after: compressed.size };
            if (compressionInfoTimer) clearTimeout(compressionInfoTimer);
            compressionInfoTimer = setTimeout(() => { compressionInfo.value = null; }, 4000);
        }

        const form = new FormData();
        form.append('image', compressed);

        const response = await laravelFetch(route('comments.images.store'), {
            method: 'POST',
            body: form,
        });

        if (response.ok) {
            const { url } = await response.json();
            editor.value.chain().focus().setImage({ src: url }).run();
        }
    } finally {
        isUploading.value = false;
        input.value = '';
    }
}
</script>

<template>
    <div class="rounded-md border border-muted-foreground/20 bg-transparent shadow-sm focus-within:ring-1 focus-within:ring-primary/50 focus-within:border-primary/40 transition-all overflow-hidden">

        <!-- Toolbar -->
        <div class="flex flex-wrap items-center gap-0.5 border-b border-muted-foreground/15 bg-muted/40 px-2 py-1.5">

            <!-- History -->
            <div class="flex items-center">
                <button type="button" @click="editor?.chain().focus().undo().run()" :disabled="!editor?.can().undo()"
                    class="toolbar-btn" title="Undo">
                    <Undo class="h-3.5 w-3.5" />
                </button>
                <button type="button" @click="editor?.chain().focus().redo().run()" :disabled="!editor?.can().redo()"
                    class="toolbar-btn" title="Redo">
                    <Redo class="h-3.5 w-3.5" />
                </button>
            </div>

            <div class="w-px h-4 bg-border/60 mx-1" />

            <!-- Formatting -->
            <button type="button" @click="editor?.chain().focus().toggleBold().run()"
                :class="['toolbar-btn', editor?.isActive('bold') && 'toolbar-btn-active']" title="Bold">
                <Bold class="h-3.5 w-3.5" />
            </button>
            <button type="button" @click="editor?.chain().focus().toggleItalic().run()"
                :class="['toolbar-btn', editor?.isActive('italic') && 'toolbar-btn-active']" title="Italic">
                <Italic class="h-3.5 w-3.5" />
            </button>
            <button type="button" @click="editor?.chain().focus().toggleUnderline().run()"
                :class="['toolbar-btn', editor?.isActive('underline') && 'toolbar-btn-active']" title="Underline">
                <UnderlineIcon class="h-3.5 w-3.5" />
            </button>

            <div class="w-px h-4 bg-border/60 mx-1" />

            <!-- Lists -->
            <button type="button" @click="editor?.chain().focus().toggleBulletList().run()"
                :class="['toolbar-btn', editor?.isActive('bulletList') && 'toolbar-btn-active']" title="Bullet List">
                <List class="h-3.5 w-3.5" />
            </button>
            <button type="button" @click="editor?.chain().focus().toggleOrderedList().run()"
                :class="['toolbar-btn', editor?.isActive('orderedList') && 'toolbar-btn-active']" title="Ordered List">
                <ListOrdered class="h-3.5 w-3.5" />
            </button>

            <div class="w-px h-4 bg-border/60 mx-1" />

            <!-- Image upload -->
            <button type="button" @click="imageInputRef?.click()" :disabled="isUploading"
                class="toolbar-btn" title="Insert Image">
                <span v-if="isUploading" class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent" />
                <ImageIcon v-else class="h-3.5 w-3.5" />
            </button>
            <!-- Compression result badge (fades after 4s) -->
            <Transition
                enter-from-class="opacity-0 scale-90"
                enter-active-class="transition-all duration-200"
                leave-to-class="opacity-0 scale-90"
                leave-active-class="transition-all duration-150"
            >
                <div
                    v-if="compressionInfo"
                    class="flex items-center gap-1 rounded-full border border-emerald-500/30 bg-emerald-500/8 px-2 py-0.5 text-[10px]"
                >
                    <span class="text-muted-foreground line-through">{{ (compressionInfo.before / 1024).toFixed(0) }}KB</span>
                    <span class="text-muted-foreground/50">→</span>
                    <span class="font-semibold text-emerald-500">{{ (compressionInfo.after / 1024).toFixed(0) }}KB</span>
                    <span class="font-semibold text-emerald-600">-{{ Math.round((1 - compressionInfo.after / compressionInfo.before) * 100) }}%</span>
                </div>
            </Transition>
            <input
                ref="imageInputRef"
                type="file"
                accept="image/jpeg,image/png,image/gif,image/webp"
                class="hidden"
                @change="handleImageUpload"
            />
        </div>

        <!-- Editor content -->
        <EditorContent :editor="editor" />
    </div>
</template>

<style scoped>
.toolbar-btn {
    @apply flex h-7 w-7 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:opacity-30 disabled:cursor-not-allowed;
}
.toolbar-btn-active {
    @apply bg-primary/10 text-primary;
}

:deep(.tiptap p.is-editor-empty:first-child::before) {
    content: attr(data-placeholder);
    @apply text-muted-foreground/50 pointer-events-none float-left h-0;
}

:deep(.tiptap) {
    @apply text-foreground;
}

:deep(.tiptap ul) {
    @apply list-disc pl-5 space-y-0.5;
}

:deep(.tiptap ol) {
    @apply list-decimal pl-5 space-y-0.5;
}

:deep(.tiptap strong) {
    @apply font-bold;
}

:deep(.tiptap em) {
    @apply italic;
}

:deep(.tiptap img) {
    @apply max-w-full rounded-md my-2 border border-border/40;
}
</style>
