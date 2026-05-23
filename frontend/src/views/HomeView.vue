<script setup lang="ts">
const widgetPreviewUrl = `${import.meta.env.BASE_URL}images/widget-preview.jpg`
const productImageUrl = `${import.meta.env.BASE_URL}images/demo-product.jpg`
const whatsappUrl = 'https://wa.me/5531993157573'
const checkoutDefault = { path: '/checkout', query: { platform: 'custom' } }
const checkoutBigShop = { path: '/checkout', query: { platform: 'bigshop' } }
const currentYear = new Date().getFullYear()
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
        <RouterLink :to="checkoutDefault" class="btn btn-secondary">
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
        <RouterLink :to="checkoutDefault" class="btn btn-primary">
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
        <p>Para lojas em Shopify, WooCommerce, Nuvemshop, VTEX, Tray ou instalacao personalizada.</p>
        <small>Total anual em ate 12x no cartao. Pix a vista com 5% de desconto.</small>
        <RouterLink :to="checkoutDefault" class="btn btn-secondary">
          <i class="fa-solid fa-cart-shopping" aria-hidden="true"></i>
          Contratar este plano
        </RouterLink>
      </article>
      <article class="highlight">
        <span>BigShop</span>
        <strong>R$ 129,90/mes</strong>
        <p>Preco especial exclusivo para lojas BigShop, com instalacao guiada apenas para BigShop.</p>
        <small>Integracao mais simples do ecossistema, sem liberar outras plataformas no painel.</small>
        <RouterLink :to="checkoutBigShop" class="btn btn-primary">
          <i class="fa-solid fa-bolt" aria-hidden="true"></i>
          Contratar BigShop
        </RouterLink>
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

  <section class="landing-section bigshop-store-band">
    <div class="landing-heading">
      <span class="eyebrow">BigShop</span>
      <h2>Ainda nao tem uma loja online?</h2>
      <p>Crie agora sua loja na BigShop e ja comece com o caminho mais simples para usar o Provador Virtual.</p>
      <a href="https://bigshop.com.br" target="_blank" rel="noopener" class="btn btn-primary">
        <i class="fa-solid fa-store" aria-hidden="true"></i>
        Crie agora sua loja na BigShop
      </a>
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
        <RouterLink :to="checkoutDefault" class="btn btn-primary">
          <i class="fa-solid fa-lock" aria-hidden="true"></i>
          Contratar agora
        </RouterLink>
        <a :href="whatsappUrl" target="_blank" rel="noopener" class="btn btn-secondary">
          <i class="fa-solid fa-headset" aria-hidden="true"></i>
          Falar com especialista
        </a>
      </div>
    </div>
  </section>

  <section class="landing-footer">
    <div class="public-band footer-grid">
      <div class="footer-brand">
        <span class="brand-mark">PV</span>
        <div>
          <strong>Provador Virtual</strong>
          <small>Recomendacao de tamanho simples para lojas de moda.</small>
        </div>
      </div>

      <nav aria-label="Rodape">
        <RouterLink to="/produto-teste">Loja teste</RouterLink>
        <RouterLink :to="checkoutDefault">Contratar</RouterLink>
        <RouterLink to="/privacidade">Privacidade</RouterLink>
        <RouterLink to="/termos">Termos</RouterLink>
      </nav>

      <div class="footer-contact">
        <a :href="whatsappUrl" target="_blank" rel="noopener">
          <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
          Falar com especialista
        </a>
        <a href="https://bigshop.com.br" target="_blank" rel="noopener">
          <i class="fa-solid fa-store" aria-hidden="true"></i>
          BigShop
        </a>
      </div>
    </div>
    <div class="public-band footer-bottom">
      <span><b>{{ currentYear }} ©</b> | desenvolvido por <a href="https://opents.com.br" target="_blank" rel="noopener">OTS - Open Tecnologia e Serviços</a></span>
    </div>
  </section>
</template>
