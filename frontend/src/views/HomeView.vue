<script setup lang="ts">
const widgetPreviewUrl = `${import.meta.env.BASE_URL}images/widget-preview.jpg`
const productImageUrl = `${import.meta.env.BASE_URL}images/demo-product.jpg`
const brandLogoUrl = `${import.meta.env.BASE_URL}images/brand/logo_provador_virtual.png`
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
      <h1>Transforme a experiência de compra na sua loja de moda</h1>
      <p>
        Com o Provador Virtual, seus clientes descobrem o tamanho ideal em poucos passos. A IA usa tabelas da loja,
        base brasileira de medidas, perfil corporal e histórico autorizado para reduzir dúvida, troca e abandono.
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
        <img :src="productImageUrl" alt="Produto de moda em página de loja online" />
      </div>
      <div class="landing-copy">
        <span class="eyebrow">
          <i class="fa-solid fa-box-open" aria-hidden="true"></i>
          Menos devoluções
        </span>
        <h2>Sua loja ainda perde vendas por dúvida de tamanho?</h2>
        <p>
          O comprador não quer decorar tabela, medir tudo de novo ou trocar peça depois. Ele quer uma resposta clara:
          qual tamanho tem mais chance de servir bem naquele produto.
        </p>
        <ul class="landing-checks">
          <li><i class="fa-solid fa-check" aria-hidden="true"></i><strong>Menos custo:</strong> reduza trocas por tamanho errado.</li>
          <li><i class="fa-solid fa-check" aria-hidden="true"></i><strong>Mais conversão:</strong> tire a dúvida antes do carrinho.</li>
          <li><i class="fa-solid fa-check" aria-hidden="true"></i><strong>Mais dados:</strong> aprenda com recomendações, feedbacks e outliers.</li>
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
        <img :src="widgetPreviewUrl" alt="Widget do Provador Virtual em uma experiência de compra" />
      </div>
      <div class="landing-copy">
        <span class="eyebrow">
          <i class="fa-solid fa-brain" aria-hidden="true"></i>
          Inteligência simples de usar
        </span>
        <h2>IA e regras de medidas trabalhando sem complicar a compra</h2>
        <p>
          O lojista parte de modelos prontos do mercado brasileiro, pode extrair tabela por texto, CSV ou imagem e
          revisa tudo antes de publicar. Na loja, o motor combina esses dados com sinais reais de uso para evoluir com segurança.
        </p>
        <div class="landing-steps">
          <article>
            <span>1</span>
            <strong>Medidas e preferências</strong>
            <small>O cliente informa o básico, refina se quiser e pode reaproveitar dados salvos com consentimento.</small>
          </article>
          <article>
            <span>2</span>
            <strong>IA + base brasileira</strong>
            <small>O motor cruza categoria, modelagem, altura, peso, idade, medidas e outliers bloqueados.</small>
          </article>
          <article>
            <span>3</span>
            <strong>Tamanho recomendado</strong>
            <small>A loja mostra a indicação, a tabela de medidas do produto e aprende com feedbacks confiáveis.</small>
          </article>
        </div>
      </div>
    </div>
  </section>

  <section id="planos" class="landing-section">
    <div class="landing-heading">
      <span class="eyebrow">Plano único</span>
      <h2>Preço direto, com desconto para clientes BigShop</h2>
      <p>Assinatura anual com pagamento em até 12x no cartão ou Pix à vista com 5% de desconto.</p>
    </div>

    <div class="pricing-strip">
      <article>
        <span>Demais plataformas</span>
        <strong>R$ 189,90/mês</strong>
        <p>Para lojas em Shopify, WooCommerce, Nuvemshop, VTEX, Tray, Loja Integrada, Magento, OpenCart ou instalação personalizada.</p>
        <small>Total anual em até 12x no cartão. Pix à vista com 5% de desconto.</small>
        <RouterLink :to="checkoutDefault" class="btn btn-secondary">
          <i class="fa-solid fa-cart-shopping" aria-hidden="true"></i>
          Contratar este plano
        </RouterLink>
      </article>
      <article class="highlight">
        <span>BigShop</span>
        <strong>R$ 129,90/mês</strong>
        <p>Preço especial exclusivo para lojas BigShop, com instalação guiada apenas para BigShop.</p>
        <small>Integração mais simples do ecossistema, sem liberar outras plataformas no painel.</small>
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
      <h2>O que o lojista e o comprador sentem na prática</h2>
    </div>

    <div class="metric-grid landing-benefits">
      <article class="metric-card">
        <i class="fa-solid fa-bullseye" aria-hidden="true"></i>
        <strong>Recomendação precisa</strong>
        <span>Tamanho ideal por produto, categoria, tabela, medidas e preferência de caimento.</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-table-list" aria-hidden="true"></i>
        <strong>Tabelas com IA</strong>
        <span>Modelos prontos por categoria, sugestão por texto/CSV/imagem e revisão humana antes de publicar.</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-plug" aria-hidden="true"></i>
        <strong>Integração padrão</strong>
        <span>BigShop, Shopify, WooCommerce, Nuvemshop, VTEX, Tray, Loja Integrada, Magento, OpenCart e script universal.</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-palette" aria-hidden="true"></i>
        <strong>Visual configurável</strong>
        <span>O lojista ajusta cores, fonte e botões do widget com preview antes de publicar.</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
        <strong>Aprendizado seguro</strong>
        <span>Dados de uso, recomendações, produtos sem tabela e descarte de sinais fora da média.</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
        <strong>LGPD desde a base</strong>
        <span>Histórico de medidas com cuidado, consentimento e descarte de sinais fora da média.</span>
      </article>
    </div>
  </section>

  <section class="landing-section bigshop-store-band">
    <div class="landing-heading">
      <span class="eyebrow">BigShop</span>
      <h2>Ainda não tem uma loja online?</h2>
      <p>Crie agora sua loja na BigShop e já comece com o caminho mais simples para usar o Provador Virtual.</p>
      <a href="https://bigshop.com.br" target="_blank" rel="noopener" class="btn btn-primary">
        <i class="fa-solid fa-store" aria-hidden="true"></i>
        Crie agora sua loja na BigShop
      </a>
    </div>
  </section>

  <section class="landing-cta">
    <div>
      <span class="eyebrow">Provador Virtual</span>
      <h2>Pronto para vender moda com menos dúvida de tamanho?</h2>
      <p>Teste a experiência em uma loja fictícia realista ou contrate o plano anual para ativar sua empresa.</p>
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
        <img class="footer-logo" :src="brandLogoUrl" alt="Provador Virtual" />
        <div>
          <strong>Provador Virtual</strong>
          <small>Recomendação de tamanho simples para lojas de moda.</small>
        </div>
      </div>

      <nav aria-label="Rodapé">
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
