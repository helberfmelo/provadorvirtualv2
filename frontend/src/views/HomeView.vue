<script setup lang="ts">
const widgetPreviewUrl = `${import.meta.env.BASE_URL}images/widget-preview.jpg`
const productImageUrl = `${import.meta.env.BASE_URL}images/demo-product.jpg`
</script>

<template>
  <section
    class="public-hero landing-hero"
    :style="{ backgroundImage: `linear-gradient(90deg, rgba(15, 23, 42, 0.80), rgba(15, 23, 42, 0.34)), url(${widgetPreviewUrl})` }"
  >
    <div class="public-hero-inner landing-hero-copy">
      <span class="eyebrow">
        <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
        Para e-commerces de moda
      </span>
      <h1>Transforme a experiencia de compra na sua loja de moda</h1>
      <p>
        Com o Provador Virtual, seus clientes descobrem o tamanho ideal de cada roupa em poucos passos.
        Menos trocas, mais confianca na compra e uma integracao simples com sua plataforma.
      </p>
      <div class="hero-actions">
        <RouterLink to="/produto-teste" class="btn btn-primary">
          <i class="fa-solid fa-play" aria-hidden="true"></i>
          Testar agora
        </RouterLink>
        <RouterLink to="/checkout" class="btn btn-secondary">
          <i class="fa-solid fa-cart-shopping" aria-hidden="true"></i>
          Contratar
        </RouterLink>
      </div>
    </div>
  </section>

  <section class="landing-section">
    <div class="landing-split">
      <div class="landing-media">
        <img :src="productImageUrl" alt="Produto de moda em pagina de loja online" />
      </div>
      <div class="landing-copy">
        <span class="eyebrow">
          <i class="fa-solid fa-box-open" aria-hidden="true"></i>
          Menos devolucoes
        </span>
        <h2>Sua loja ainda perde vendas por duvida de tamanho?</h2>
        <p>
          O comprador nao quer decorar tabela, medir tudo de novo ou trocar peca depois. Ele quer uma resposta clara:
          qual tamanho tem mais chance de servir bem naquele produto.
        </p>
        <ul class="landing-checks">
          <li><i class="fa-solid fa-check" aria-hidden="true"></i><strong>Menos custo:</strong> reduza trocas por tamanho errado.</li>
          <li><i class="fa-solid fa-check" aria-hidden="true"></i><strong>Mais conversao:</strong> tire a duvida antes do carrinho.</li>
          <li><i class="fa-solid fa-check" aria-hidden="true"></i><strong>Mais dados:</strong> aprenda com recomendacoes, feedbacks e outliers.</li>
        </ul>
        <RouterLink to="/checkout" class="btn btn-primary">
          <i class="fa-solid fa-bolt" aria-hidden="true"></i>
          Ativar na minha loja
        </RouterLink>
      </div>
    </div>
  </section>

  <section class="landing-section muted-band">
    <div class="landing-split reverse">
      <div class="landing-media">
        <img :src="widgetPreviewUrl" alt="Widget do Provador Virtual em uma experiencia de compra" />
      </div>
      <div class="landing-copy">
        <span class="eyebrow">
          <i class="fa-solid fa-brain" aria-hidden="true"></i>
          Inteligencia simples de usar
        </span>
        <h2>IA e regras de medidas trabalhando sem complicar a compra</h2>
        <p>
          O fluxo combina tabela da loja, perfil corporal, medidas opcionais e historico autorizado do comprador
          para entregar uma recomendacao cada vez mais precisa.
        </p>
        <div class="landing-steps">
          <article>
            <span>1</span>
            <strong>Medidas e preferencias</strong>
            <small>O cliente informa o basico e refina apenas se quiser mais precisao.</small>
          </article>
          <article>
            <span>2</span>
            <strong>Analise inteligente</strong>
            <small>O motor cruza medidas, tabela, categoria, modelagem e aprendizado seguro.</small>
          </article>
          <article>
            <span>3</span>
            <strong>Tamanho recomendado</strong>
            <small>A loja mostra a indicacao e tambem a tabela de medidas do produto.</small>
          </article>
        </div>
      </div>
    </div>
  </section>

  <section id="planos" class="landing-section">
    <div class="landing-heading">
      <span class="eyebrow">Plano unico</span>
      <h2>Preco direto, com desconto para clientes BigShop</h2>
      <p>Assinatura anual com pagamento em ate 12x no cartao ou Pix a vista com 5% de desconto.</p>
    </div>

    <div class="pricing-strip">
      <article>
        <span>Demais plataformas</span>
        <strong>R$ 189,90/mes</strong>
        <small>Total anual em ate 12x no cartao. Pix a vista com 5% de desconto.</small>
      </article>
      <article class="highlight">
        <span>BigShop</span>
        <strong>R$ 129,90/mes</strong>
        <small>Preco especial para lojas BigShop, com a integracao mais simples do ecossistema.</small>
      </article>
    </div>
  </section>

  <section class="landing-section muted-band">
    <div class="landing-heading">
      <span class="eyebrow">Vantagens</span>
      <h2>O que o lojista e o comprador sentem na pratica</h2>
    </div>

    <div class="metric-grid landing-benefits">
      <article class="metric-card">
        <i class="fa-solid fa-bullseye" aria-hidden="true"></i>
        <strong>Recomendacao precisa</strong>
        <span>Tamanho ideal por produto, categoria, tabela, medidas e preferencia de caimento.</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-table-list" aria-hidden="true"></i>
        <strong>Tabela de medidas</strong>
        <span>Botao dedicado para o comprador consultar a tabela como nas melhores lojas do mercado.</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-plug" aria-hidden="true"></i>
        <strong>Integracao padrao</strong>
        <span>BigShop, Shopify, WooCommerce, Nuvemshop, VTEX, Tray e script universal.</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-palette" aria-hidden="true"></i>
        <strong>Visual configuravel</strong>
        <span>O lojista ajusta cores, fonte e botoes do widget com preview antes de publicar.</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
        <strong>Analytics</strong>
        <span>Dados de uso, recomendacoes, produtos sem tabela e oportunidades de melhoria.</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
        <strong>LGPD desde a base</strong>
        <span>Historico de medidas com cuidado, consentimento e descarte de sinais fora da media.</span>
      </article>
    </div>
  </section>

  <section class="landing-cta">
    <div>
      <span class="eyebrow">Provador Virtual</span>
      <h2>Pronto para vender moda com menos duvida de tamanho?</h2>
      <p>Teste a experiencia em uma loja ficticia realista ou contrate o plano anual para ativar sua empresa.</p>
      <div class="hero-actions">
        <RouterLink to="/produto-teste" class="btn btn-secondary">
          <i class="fa-solid fa-shirt" aria-hidden="true"></i>
          Ver demo
        </RouterLink>
        <RouterLink to="/checkout" class="btn btn-primary">
          <i class="fa-solid fa-lock" aria-hidden="true"></i>
          Contratar agora
        </RouterLink>
        <a href="mailto:helber@bigshop.com.br?subject=Provador%20Virtual" class="btn btn-secondary">
          <i class="fa-solid fa-headset" aria-hidden="true"></i>
          Falar com especialista
        </a>
      </div>
    </div>
  </section>

  <section class="public-band landing-footer">
    <RouterLink to="/privacidade">
      <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
      Privacidade
    </RouterLink>
    <RouterLink to="/termos">
      <i class="fa-solid fa-file-contract" aria-hidden="true"></i>
      Termos
    </RouterLink>
    <span>Desenvolvido por provadorvirtual.online</span>
  </section>
</template>
