<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import {
  articleGroupLabel,
  buildSupportUrl,
  filterHelpArticles,
  findHelpArticle,
  helpArticles,
  helpGroupLabels,
  relatedHelpArticles,
} from '../content/helpCenter'
import { useAuthStore } from '../stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const search = ref(typeof route.query.busca === 'string' ? route.query.busca : '')

watch(() => route.query.busca, (value) => {
  const nextValue = typeof value === 'string' ? value : ''

  if (nextValue !== search.value) {
    search.value = nextValue
  }
})

const filteredArticles = computed(() => filterHelpArticles(search.value))

const selectedArticle = computed(() => {
  if (!filteredArticles.value.length) {
    return null
  }

  const requested = typeof route.query.topico === 'string' ? route.query.topico : ''
  const requestedArticle = requested ? findHelpArticle(requested) : null

  if (requestedArticle && filteredArticles.value.some((article) => article.key === requestedArticle.key)) {
    return requestedArticle
  }

  return filteredArticles.value[0] || helpArticles[0] || null
})

const groupedArticles = computed(() => {
  const order = Object.keys(helpGroupLabels) as Array<keyof typeof helpGroupLabels>

  return order
    .map((group) => ({
      key: group,
      label: helpGroupLabels[group],
      articles: filteredArticles.value.filter((article) => article.group === group),
    }))
    .filter((group) => group.articles.length > 0)
})

const selectedRelatedArticles = computed(() => (
  selectedArticle.value ? relatedHelpArticles(selectedArticle.value) : []
))

const supportUrl = computed(() => buildSupportUrl({
  article: selectedArticle.value,
  routePath: selectedArticle.value?.primaryTo,
  companyName: auth.activeCompany?.name || null,
  companyCode: auth.activeCompany?.access_code || null,
  merchantName: auth.activeMerchant?.name || null,
  platform: auth.activeCompany?.platform || null,
  userName: auth.user?.name || null,
  userEmail: auth.user?.email || null,
}))

const supportSummary = computed(() => [
  auth.activeCompany?.name || 'Sem empresa ativa',
  auth.activeCompany?.access_code || 'Sem código',
  auth.activeCompany?.platform || 'Sem plataforma',
].join(' · '))

async function openArticle(key: string) {
  await router.replace({
    path: '/app/ajuda',
    query: {
      topico: key,
      busca: search.value || undefined,
    },
  })
}

async function syncSearch() {
  await router.replace({
    path: '/app/ajuda',
    query: {
      topico: selectedArticle.value?.key || undefined,
      busca: search.value || undefined,
    },
  })
}

async function clearSearch() {
  search.value = ''
  await syncSearch()
}
</script>

<template>
  <section class="dashboard app-workspace help-page">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Ajuda</span>
        <h1>Base de conhecimento</h1>
        <p class="page-heading-help">
          Guias curtos por tela, assunto e plataforma para a loja avançar sem depender de explicação manual a cada passo.
        </p>
      </div>
      <a class="btn btn-secondary" :href="supportUrl" target="_blank" rel="noopener noreferrer">
        <i class="fa-solid fa-headset" aria-hidden="true"></i>
        Suporte com contexto
      </a>
    </div>

    <section class="panel-main help-search-panel">
      <div>
        <strong>Buscar artigo</strong>
        <small>{{ filteredArticles.length }} resultado{{ filteredArticles.length === 1 ? '' : 's' }} disponível{{ filteredArticles.length === 1 ? '' : 'eis' }}</small>
      </div>
      <div class="help-search-row">
        <input
          v-model="search"
          type="search"
          placeholder="Busque por plataforma, tela, assunto ou etapa"
          @input="syncSearch"
        />
        <button v-if="search" class="btn btn-secondary" type="button" @click="clearSearch">
          Limpar
        </button>
      </div>
    </section>

    <div class="help-grid">
      <aside class="panel-list help-topic-list" aria-label="Artigos da base de conhecimento">
        <div v-if="!filteredArticles.length" class="empty-state">
          Nenhum artigo encontrado para essa busca.
        </div>

        <section v-for="group in groupedArticles" :key="group.key" class="help-topic-group">
          <h2 class="work-nav-section-title">{{ group.label }}</h2>
          <button
            v-for="article in group.articles"
            :key="article.key"
            type="button"
            :class="['list-row', { active: selectedArticle.key === article.key }]"
            @click="openArticle(article.key)"
          >
            <strong>{{ article.title }}</strong>
            <span>{{ article.summary }}</span>
          </button>
        </section>
      </aside>

      <section class="panel-main help-topic-detail">
        <div v-if="selectedArticle" class="subsection-heading">
          <div>
            <h2>{{ selectedArticle.title }}</h2>
            <span>{{ articleGroupLabel(selectedArticle) }}</span>
          </div>
          <RouterLink class="btn btn-secondary" :to="selectedArticle.primaryTo">
            {{ selectedArticle.primaryLabel }}
          </RouterLink>
        </div>

        <div v-else class="empty-state">
          Ajuste a busca para encontrar um artigo relacionado à sua dúvida.
        </div>

        <template v-if="selectedArticle">
          <p>{{ selectedArticle.summary }}</p>

          <div class="help-meta-grid">
            <article>
              <strong>Quando usar</strong>
              <small>{{ selectedArticle.whenToUse }}</small>
            </article>
            <article>
              <strong>Próximo passo</strong>
              <small>{{ selectedArticle.nextLabel || selectedArticle.primaryLabel }}</small>
            </article>
            <article>
              <strong>Contexto atual</strong>
              <small>{{ supportSummary }}</small>
            </article>
          </div>

          <div v-if="selectedArticle.tags?.length" class="help-tag-list" aria-label="Assuntos relacionados">
            <span v-for="tag in selectedArticle.tags" :key="tag">{{ tag }}</span>
          </div>

          <ol class="help-step-list">
            <li v-for="step in selectedArticle.steps" :key="step">{{ step }}</li>
          </ol>

          <div v-if="selectedRelatedArticles.length" class="help-related-block">
            <div class="subsection-heading">
              <h3>Artigos relacionados</h3>
              <span>{{ selectedRelatedArticles.length }} link{{ selectedRelatedArticles.length === 1 ? '' : 's' }}</span>
            </div>
            <div class="help-related-links">
              <button
                v-for="article in selectedRelatedArticles"
                :key="article.key"
                type="button"
                class="help-related-button"
                @click="openArticle(article.key)"
              >
                {{ article.title }}
              </button>
            </div>
          </div>

          <section class="help-support-panel">
            <div>
              <strong>Falar com suporte já com contexto</strong>
              <small>
                O link já leva assunto, rota sugerida, empresa, código, plataforma e usuário para reduzir a explicação manual.
              </small>
            </div>
            <div class="action-row compact">
              <a class="btn btn-primary" :href="supportUrl" target="_blank" rel="noopener noreferrer">
                <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
                Abrir suporte contextual
              </a>
              <RouterLink v-if="selectedArticle.nextTo" class="btn btn-secondary" :to="selectedArticle.nextTo">
                {{ selectedArticle.nextLabel || 'Próximo passo' }}
              </RouterLink>
            </div>
          </section>
        </template>
      </section>
    </div>
  </section>
</template>
