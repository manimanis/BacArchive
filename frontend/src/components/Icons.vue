<template>
  <svg v-if="type === 'pdf'" :width="size" :height="size" viewBox="0 0 24 24" fill="none"
    style="vertical-align:middle;flex-shrink:0">
    <path d="M6 2C4.9 2 4 2.9 4 4v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6H6z" fill="#E53935"/>
    <path d="M14 2l6 6h-6V2z" fill="#B71C1C"/>
    <rect x="7" y="13" width="10" height="1.2" rx=".6" fill="white" opacity=".9"/>
    <rect x="7" y="15.5" width="8" height="1.2" rx=".6" fill="white" opacity=".9"/>
    <rect x="6.5" y="9.5" width="6" height="3" rx=".8" fill="white" opacity=".95"/>
    <text x="9.5" y="12" text-anchor="middle" font-size="2.8" font-weight="bold" fill="#E53935" font-family="Arial,Helvetica,sans-serif">PDF</text>
  </svg>
  <svg v-else :width="size" :height="size" viewBox="0 0 24 24" fill="none"
    style="vertical-align:middle;flex-shrink:0">
    <path d="M6 2C4.9 2 4 2.9 4 4v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6H6z" fill="#1E7E34"/>
    <path d="M14 2l6 6h-6V2z" fill="#145A24"/>
    <rect x="6.5" y="11" width="11" height="7" rx=".6" fill="white" opacity=".15"/>
    <line x1="10" y1="11" x2="10" y2="18" stroke="white" stroke-width=".7" opacity=".7"/>
    <line x1="13.5" y1="11" x2="13.5" y2="18" stroke="white" stroke-width=".7" opacity=".7"/>
    <line x1="6.5" y1="13.5" x2="17.5" y2="13.5" stroke="white" stroke-width=".7" opacity=".7"/>
    <line x1="6.5" y1="16" x2="17.5" y2="16" stroke="white" stroke-width=".7" opacity=".7"/>
    <rect x="6.5" y="7.5" width="6.5" height="3" rx=".8" fill="white" opacity=".95"/>
    <text x="9.7" y="10" text-anchor="middle" font-size="2.5" font-weight="bold" fill="#1E7E34" font-family="Arial,Helvetica,sans-serif">XLS</text>
  </svg>
</template>

<script setup>
defineProps({
  type: { type: String, default: 'pdf' },
  size: { type: Number, default: 18 },
})
</script>