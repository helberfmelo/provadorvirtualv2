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
        <h2>Uso do servico</h2>
        <p>
          O lojista e responsavel por cadastrar produtos, grades e tabelas corretas. O sistema
          usa essas informacoes para gerar recomendacoes.
        </p>
      </article>
      <article>
        <h2>Recomendacao</h2>
        <p>
          A recomendacao e uma orientacao de tamanho, nao uma garantia absoluta de caimento,
          estoque, preco, prazo ou politica de troca.
        </p>
      </article>
      <article>
        <h2>Integracao</h2>
        <p>
          O widget deve ser instalado nos dominios autorizados da loja. Requisicoes fora desses
          dominios podem ser bloqueadas automaticamente.
        </p>
      </article>
      <article>
        <h2>Seguranca</h2>
        <p>
          Credenciais de plataformas externas devem ser mantidas em segredo. Tokens recebidos
          pelo painel sao tratados como dados sensiveis.
        </p>
      </article>
      <article>
        <h2>Disponibilidade</h2>
        <p>
          O servico possui monitoramento basico e smokes de deploy, mas manutencoes e falhas de
          terceiros podem afetar recursos temporariamente.
        </p>
      </article>
      <article>
        <h2>Responsabilidade</h2>
        <p>
          O Provador Virtual nao substitui revisao humana de tabelas de medidas, principalmente
          em produtos novos, modelagens especiais ou campanhas comerciais.
        </p>
      </article>
    </div>
  </section>
</template>
