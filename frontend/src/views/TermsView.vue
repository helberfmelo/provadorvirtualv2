<template>
  <section class="legal-page">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Provador Virtual</span>
        <h1>Termos de uso</h1>
        <p>
          Estes termos orientam o uso inicial do Provador Virtual por lojistas, equipes de suporte
          e consumidores finais.
        </p>
      </div>
      <RouterLink to="/login" class="btn btn-secondary">
        <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
        Acessar painel
      </RouterLink>
    </div>

    <div class="legal-grid">
      <article>
        <h2>Uso do serviço</h2>
        <p>
          O lojista é responsável por cadastrar produtos, grades e tabelas corretas. O sistema
          usa essas informações para gerar recomendações.
        </p>
      </article>
      <article>
        <h2>Recomendação</h2>
        <p>
          A recomendação é uma orientação de tamanho, não uma garantia absoluta de caimento,
          estoque, preço, prazo ou política de troca.
        </p>
      </article>
      <article>
        <h2>Integração</h2>
        <p>
          O provador deve ser instalado nos domínios autorizados da loja. Requisições fora desses
          domínios podem ser bloqueadas automaticamente.
        </p>
      </article>
      <article>
        <h2>Segurança</h2>
        <p>
          Credenciais de plataformas externas devem ser mantidas em segredo. Tokens recebidos
          pelo painel são tratados como dados sensíveis.
        </p>
      </article>
      <article>
        <h2>Disponibilidade</h2>
        <p>
          O serviço possui monitoramento básico e smokes de deploy, mas manutenções e falhas de
          terceiros podem afetar recursos temporariamente.
        </p>
      </article>
      <article>
        <h2>Responsabilidade</h2>
        <p>
          O Provador Virtual não substitui revisão humana de tabelas de medidas, principalmente
          em produtos novos, modelagens especiais ou campanhas comerciais.
        </p>
      </article>
    </div>
  </section>
</template>
