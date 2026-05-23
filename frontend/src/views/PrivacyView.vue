<template>
  <section class="legal-page">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Provador Virtual</span>
        <h1>Privacidade</h1>
        <p>
          Esta política resume como tratamos dados no widget, no painel do lojista e nas
          integrações da plataforma.
        </p>
      </div>
      <RouterLink to="/produto-teste" class="btn btn-secondary">
        <i class="fa-solid fa-shirt" aria-hidden="true"></i>
        Produto teste
      </RouterLink>
    </div>

    <div class="legal-grid">
      <article>
        <h2>Dados do consumidor</h2>
        <p>
          O widget pede apenas medidas aproximadas necessárias para recomendar tamanho. Nome,
          telefone e e-mail não fazem parte do fluxo padrão de recomendação.
        </p>
      </article>
      <article>
        <h2>Finalidade</h2>
        <p>
          Usamos as medidas para calcular a recomendação, registrar qualidade do resultado e
          melhorar tabelas de medidas da loja.
        </p>
      </article>
      <article>
        <h2>Retenção</h2>
        <p>
          Dados corporais e identificadores técnicos do widget têm retenção curta e podem ser
          anonimizados por rotina operacional.
        </p>
      </article>
      <article>
        <h2>Integrações</h2>
        <p>
          Tokens de plataformas como BigShop são salvos criptografados e não aparecem em claro
          nas respostas da API ou nos logs.
        </p>
      </article>
      <article>
        <h2>IA assistiva</h2>
        <p>
          Recursos de IA, quando ativos, servem para extrair ou sugerir tabelas. A recomendação
          de tamanho continua baseada em dados revisados pelo lojista.
        </p>
      </article>
      <article>
        <h2>Contato</h2>
        <p>
          Solicitações de privacidade podem ser enviadas para o suporte comercial do Provador
          Virtual ou para o lojista responsável pela loja onde o widget foi usado.
        </p>
      </article>
    </div>
  </section>
</template>
