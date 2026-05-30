<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

type StateTone = 'neutral' | 'loading' | 'error' | 'empty' | 'permission' | 'success' | 'warning'

const props = withDefaults(defineProps<{
  tone?: StateTone
  eyebrow?: string
  title: string
  description?: string
  actionLabel?: string
  actionTo?: string
  compact?: boolean
  buttonDisabled?: boolean
}>(), {
  tone: 'neutral',
  eyebrow: '',
  description: '',
  actionLabel: '',
  actionTo: '',
  compact: false,
  buttonDisabled: false,
})

const emit = defineEmits<{
  (event: 'action'): void
}>()

const iconClass = computed(() => ({
  neutral: 'fa-circle-info',
  loading: 'fa-spinner',
  error: 'fa-circle-xmark',
  empty: 'fa-inbox',
  permission: 'fa-lock',
  success: 'fa-circle-check',
  warning: 'fa-triangle-exclamation',
})[props.tone])

function handleAction() {
  if (!props.actionTo) {
    emit('action')
  }
}
</script>

<template>
  <section class="state-card" :class="[`state-card-${tone}`, { compact }]">
    <div class="state-card-icon">
      <i class="fa-solid" :class="iconClass" aria-hidden="true"></i>
    </div>
    <div class="state-card-copy">
      <small v-if="eyebrow" class="state-card-eyebrow">{{ eyebrow }}</small>
      <strong>{{ title }}</strong>
      <p v-if="description">{{ description }}</p>
    </div>
    <div v-if="actionLabel" class="state-card-actions">
      <RouterLink
        v-if="actionTo"
        class="btn btn-secondary btn-compact"
        :to="actionTo"
      >
        {{ actionLabel }}
      </RouterLink>
      <button
        v-else
        class="btn btn-secondary btn-compact"
        type="button"
        :disabled="buttonDisabled"
        @click="handleAction"
      >
        {{ actionLabel }}
      </button>
    </div>
  </section>
</template>
