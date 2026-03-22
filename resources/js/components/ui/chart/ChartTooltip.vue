<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { useDark } from '@vueuse/core'

const isDark = useDark()

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
  <div :class="isDark ? 'dark' : ''">
    <Card class="text-sm shadow-lg border-border/50">
      <CardHeader v-if="title" class="p-3 border-b border-border/50">
        <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
          {{ title }}
        </CardTitle>
      </CardHeader>
      <CardContent class="p-3 min-w-[180px] flex flex-col gap-2">
        <div v-for="(item, key) in data" :key="key" class="flex justify-between items-center">
          <div class="flex items-center gap-2">
            <div class="w-2 h-2 rounded-full" :style="{ backgroundColor: item.color }" />
            <span class="text-muted-foreground font-medium">{{ item.name }}</span>
          </div>
          <span class="font-bold text-foreground">{{ item.value }}</span>
        </div>
      </CardContent>
    </Card>
  </div>
</template>
