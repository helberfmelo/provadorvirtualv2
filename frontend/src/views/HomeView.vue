<script setup lang="ts">
const widgetPreviewUrl = `${import.meta.env.BASE_URL}images/widget-preview.jpg`
</script>

<template>
  <section class="hero-section">
    <div class="hero-copy">
      <span class="eyebrow">Para e-commerces de moda</span>
      <h1>Provador Virtual</h1>
      <p>
        Recomendacao de tamanho simples de instalar, pensada para lojas brasileiras e
        pronta para evoluir com integracao BigShop.
      </p>
      <div class="hero-actions">
        <RouterLink to="/produto-teste" class="btn btn-primary">
          <i class="fa-solid fa-shirt" aria-hidden="true"></i>
          Testar produto
        </RouterLink>
        <RouterLink to="/login" class="btn btn-secondary">
          <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
          Acessar painel
        </RouterLink>
      </div>
    </div>
    <div class="hero-media">
      <img :src="widgetPreviewUrl" alt="Pessoa analisando recomendacao de tamanho em uma loja virtual" />
    </div>
  </section>

  <section class="band">
    <div class="metric-grid">
      <article class="metric-card">
        <i class="fa-solid fa-ruler-combined" aria-hidden="true"></i>
        <strong>Tabelas reais</strong>
        <span>Produtos, variacoes e medidas normalizadas desde a base.</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-code" aria-hidden="true"></i>
        <strong>Widget universal</strong>
        <span>Snippet simples para qualquer plataforma e caminho nativo para BigShop.</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
        <strong>Dados operacionais</strong>
        <span>Logs e feedback preparados para melhorar recomendacao e conversao.</span>
      </article>
    </div>

    <div class="legal-strip">
      <RouterLink to="/privacidade">
        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
        Privacidade
      </RouterLink>
      <RouterLink to="/termos">
        <i class="fa-solid fa-file-contract" aria-hidden="true"></i>
        Termos
      </RouterLink>
    </div>
  </section>
</template>
