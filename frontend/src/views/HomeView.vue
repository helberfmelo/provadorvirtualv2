<script setup lang="ts">
const widgetPreviewUrl = `${import.meta.env.BASE_URL}images/widget-preview.jpg`
</script>

<template>
  <section class="public-hero" :style="{ backgroundImage: `linear-gradient(90deg, rgba(15, 23, 42, 0.82), rgba(15, 23, 42, 0.28)), url(${widgetPreviewUrl})` }">
    <div class="public-hero-inner">
      <span class="eyebrow">Para e-commerces de moda</span>
      <h1>Provador Virtual</h1>
      <p>
        Recomendacao de tamanho, tabela de medidas e widget de loja em um fluxo simples para o lojista e rapido para o comprador.
      </p>
      <div class="hero-actions">
        <RouterLink to="/checkout" class="btn btn-primary">
          <i class="fa-solid fa-bolt" aria-hidden="true"></i>
          Contratar
        </RouterLink>
        <RouterLink to="/produto-teste" class="btn btn-secondary">
          <i class="fa-solid fa-shirt" aria-hidden="true"></i>
          Testar agora
        </RouterLink>
        <a href="mailto:helber@bigshop.com.br?subject=Provador%20Virtual" class="btn btn-secondary">
          <i class="fa-solid fa-envelope" aria-hidden="true"></i>
          Contato
        </a>
      </div>
    </div>
  </section>

  <section class="public-band">
    <div class="metric-grid">
      <article class="metric-card">
        <i class="fa-solid fa-ruler-combined" aria-hidden="true"></i>
        <strong>Medidas inteligentes</strong>
        <span>Tabelas salvas, sugestoes por IA e aprendizado com feedbacks reais.</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-code" aria-hidden="true"></i>
        <strong>Instalacao padrao</strong>
        <span>BigShop, Shopify, WooCommerce, Nuvemshop, VTEX, Tray e script universal.</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
        <strong>Visual do lojista</strong>
        <span>Cores, fonte e botoes ajustados pelo painel com visualizador em tempo real.</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
        <strong>Dados para evoluir</strong>
        <span>Logs, outliers e analytics preparados para melhorar a precisao com seguranca.</span>
      </article>
    </div>

    <div class="public-flow">
      <article>
        <span>1</span>
        <strong>Conecte a loja</strong>
        <small>Escolha a plataforma e siga o passo a passo.</small>
      </article>
      <article>
        <span>2</span>
        <strong>Revise as tabelas</strong>
        <small>Use base padrao, importacao ou sugestao por IA.</small>
      </article>
      <article>
        <span>3</span>
        <strong>Publique o widget</strong>
        <small>O comprador ve recomendacao e tabela de medidas no produto.</small>
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
