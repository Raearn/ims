<script setup lang="ts">
defineProps<{
  title?: string
  data: {
    name: string
    color: string
    value: any
  }[]
}>()
</script>

<template>
  <div class="rounded-xl border border-border/60 bg-background px-3 py-2.5 text-sm shadow-xl min-w-[150px]">
    <p v-if="title" class="mb-2 text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
      {{ title }}
    </p>
    <div class="flex flex-col gap-1.5">
      <div v-for="(item, key) in data" :key="key" class="flex justify-between items-center gap-6">
        <div class="flex items-center gap-2">
          <div class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: item.color }" />
          <span class="text-xs text-muted-foreground">{{ item.name }}</span>
        </div>
        <span class="text-xs font-bold text-foreground tabular-nums">{{ item.value }}</span>
      </div>
    </div>
  </div>
</template>
