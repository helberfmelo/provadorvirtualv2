<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, RouterView, useRoute } from 'vue-router'
import { useAuthStore } from './stores/auth'

const route = useRoute()
const auth = useAuthStore()

const isAppRoute = computed(() => route.path.startsWith('/app'))
</script>

<template>
  <div class="shell" :class="{ 'shell-app': isAppRoute }">
    <header class="topbar">
      <RouterLink to="/" class="brand" aria-label="Provador Virtual">
        <span class="brand-mark">PV</span>
        <span>Provador Virtual</span>
      </RouterLink>

      <nav class="nav" aria-label="Principal">
        <RouterLink to="/produto-teste">Produto teste</RouterLink>
        <RouterLink to="/login">Entrar</RouterLink>
        <RouterLink v-if="auth.isAuthenticated" to="/app">Painel</RouterLink>
      </nav>
    </header>

    <main>
      <RouterView />
    </main>
  </div>
</template>
