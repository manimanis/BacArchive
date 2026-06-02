<template>
  <div class="toast-stack" v-if="toasts.length > 0">
    <div v-for="t in toasts" :key="t.id" :class="'toast toast-' + t.type">
      <span class="toast-icon">{{ t.type === 'ok' ? '✓' : t.type === 'err' ? '✕' : 'ℹ' }}</span>
      <span class="toast-text">{{ t.text }}</span>
      <button class="toast-close" @click="removeToast(t.id)">×</button>
    </div>
  </div>
</template>

<script setup>
import { useToasts } from '../composables/useToast.js'
const { toasts, removeToast } = useToasts()
</script>