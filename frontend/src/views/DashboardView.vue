<script setup lang="ts">
import { onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()

onMounted(() => {
  auth.loadMe().catch(() => undefined)
})
</script>

<template>
  <section class="dashboard">
    <div>
      <span class="eyebrow">Painel</span>
      <h1>Fundacao pronta para configurar lojas</h1>
      <p>
        Esta primeira tela concentra os atalhos que vao virar produtos, tabelas,
        integracoes e instalacao do widget nas proximas sprints.
      </p>
    </div>

    <div class="metric-grid">
      <article class="metric-card">
        <i class="fa-solid fa-box-open" aria-hidden="true"></i>
        <strong>1 produto demo</strong>
        <span>Base seedada para validar API, frontend e deploy.</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-table-cells" aria-hidden="true"></i>
        <strong>1 tabela de medidas</strong>
        <span>Linhas PP a GG para vestidos de modelagem regular.</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-plug" aria-hidden="true"></i>
        <strong>Widget preparado</strong>
        <span>Instalacao demo com chave publica e tema base.</span>
      </article>
    </div>
  </section>
</template>
