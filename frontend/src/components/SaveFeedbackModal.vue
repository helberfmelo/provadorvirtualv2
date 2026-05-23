<script setup lang="ts">
import { computed } from 'vue'
import { closeSaveFeedback, saveFeedback } from '../services/saveFeedback'

const iconClass = computed(() => {
  if (saveFeedback.status === 'saving') {
    return 'fa-solid fa-spinner fa-spin'
  }

  if (saveFeedback.status === 'success') {
    return 'fa-solid fa-circle-check'
  }

  return 'fa-solid fa-triangle-exclamation'
})
</script>

<template>
  <div v-if="saveFeedback.open" class="save-modal-layer" role="presentation">
    <section
      class="save-modal"
      :class="`save-modal-${saveFeedback.status}`"
      role="dialog"
      aria-modal="true"
      aria-labelledby="save-modal-title"
    >
      <span class="save-modal-icon">
        <i :class="iconClass" aria-hidden="true"></i>
      </span>
      <div>
        <strong id="save-modal-title">{{ saveFeedback.title }}</strong>
        <p>{{ saveFeedback.message }}</p>
      </div>
      <button
        v-if="saveFeedback.status === 'error'"
        class="btn btn-secondary"
        type="button"
        @click="closeSaveFeedback"
      >
        Fechar
      </button>
    </section>
  </div>
</template>
