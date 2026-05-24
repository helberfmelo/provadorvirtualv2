<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { closeSaveFeedback, saveFeedback } from '../services/saveFeedback'

const iconClass = computed(() => {
  if (saveFeedback.status === 'saving') {
    return 'fa-solid fa-spinner fa-spin'
  }

  if (saveFeedback.status === 'success') {
    return 'fa-solid fa-circle-check'
  }

  if (saveFeedback.status === 'info') {
    return 'fa-solid fa-circle-info'
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
      <button
        class="save-modal-close"
        type="button"
        aria-label="Fechar modal"
        title="Fechar"
        @click="closeSaveFeedback"
      >
        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
      </button>

      <span class="save-modal-icon">
        <i :class="iconClass" aria-hidden="true"></i>
      </span>
      <div>
        <strong id="save-modal-title">{{ saveFeedback.title }}</strong>
        <p>{{ saveFeedback.message }}</p>
      </div>
      <div v-if="saveFeedback.status === 'error' || saveFeedback.actionTo" class="save-modal-actions">
        <RouterLink
          v-if="saveFeedback.actionTo"
          class="btn btn-primary"
          :to="saveFeedback.actionTo"
          @click="closeSaveFeedback"
        >
          {{ saveFeedback.actionLabel || 'Abrir' }}
        </RouterLink>
        <button
          v-if="saveFeedback.status === 'error' || saveFeedback.actionTo"
          class="btn btn-secondary"
          type="button"
          @click="closeSaveFeedback"
        >
          Fechar
        </button>
      </div>
    </section>
  </div>
</template>
