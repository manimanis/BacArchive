<template>
  <div class="stats-bar" :style="style" v-if="items.length > 0">
    <div v-for="(item, i) in items" :key="i" class="stats-capsule"
      :style="{ background: getColor(item.color).bg, borderColor: getColor(item.color).border }">
      <span class="stats-capsule-lbl">{{ item.label }}</span>
      <span class="stats-capsule-val" :style="{ color: getColor(item.color).text }">{{ item.value }}</span>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  items: { type: Array, default: () => [] },
  style: { type: Object, default: () => ({}) },
})

const COLOR_MAP = {
  ok:      { text: '#166534', bg: '#f0fdf4', border: '#bbf7d0' },
  warn:    { text: '#92400e', bg: '#fffbeb', border: '#fcd34d' },
  danger:  { text: '#991b1b', bg: '#fff1f1', border: '#fca5a5' },
  neutral: { text: '#1a3a5c', bg: '#f0f4f8', border: '#d0dcea' },
  muted:   { text: '#64748b', bg: '#f8fafc', border: '#e2e8f0' },
}

const getColor = (color) => COLOR_MAP[color] || COLOR_MAP.neutral
</script>