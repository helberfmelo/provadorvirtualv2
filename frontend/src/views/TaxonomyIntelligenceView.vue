<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { api } from '../services/api'
import type { TaxonomyMappingSuggestion, TaxonomyVersion } from '../services/merchantTypes'
import { showFeedback } from '../services/saveFeedback'

type TaxonomySummary = {
  active_version: string
  taxonomy_categories: number
  normalized_brands: number
  local_categories: number
  local_brands: number
  candidate_mappings: number
  pending_suggestions: number
  high_confidence: number
  medium_confidence: number
  low_confidence: number
  review_required: number
  products_impacted: number
  learning_events: number
  products_with_category: number
  products_with_normalized_category: number
  products_with_brand: number
  products_with_normalized_brand: number
}

type LearningEvent = {
  id: number
  event_type: string
  target_type: string
  original_value?: string | null
  normalized_value?: string | null
  confidence_score?: number | null
  created_at?: string | null
}

const emptySummary = (): TaxonomySummary => ({
  active_version: '',
  taxonomy_categories: 0,
  normalized_brands: 0,
  local_categories: 0,
  local_brands: 0,
  candidate_mappings: 0,
  pending_suggestions: 0,
  high_confidence: 0,
  medium_confidence: 0,
  low_confidence: 0,
  review_required: 0,
  products_impacted: 0,
  learning_events: 0,
  products_with_category: 0,
  products_with_normalized_category: 0,
  products_with_brand: 0,
  products_with_normalized_brand: 0,
})

const summary = reactive<TaxonomySummary>(emptySummary())
const version = ref<TaxonomyVersion | null>(null)
const suggestions = ref<TaxonomyMappingSuggestion[]>([])
const learningEvents = ref<LearningEvent[]>([])
const selectedId = ref<number | null>(null)
const loading = ref(false)
const generating = ref(false)
const reviewingId = ref<number | null>(null)
const error = ref('')
const filter = ref<'pending' | 'category' | 'brand' | 'reviewed'>('pending')

const selectedSuggestion = computed(() => suggestions.value.find((suggestion) => suggestion.id === selectedId.value) || null)
const pendingSuggestions = computed(() => suggestions.value.filter((suggestion) => suggestion.status === 'pending'))
const filteredSuggestions = computed(() => {
  if (filter.value === 'pending') {
    return pendingSuggestions.value
  }

  if (filter.value === 'reviewed') {
    return suggestions.value.filter((suggestion) => suggestion.status !== 'pending')
  }

  return suggestions.value.filter((suggestion) => suggestion.suggestion_type === filter.value)
})
const coverage = computed(() => {
  const categoryTotal = summary.products_with_category || 0
  const brandTotal = summary.products_with_brand || 0

  return {
    categories: categoryTotal ? Math.round((summary.products_with_normalized_category / categoryTotal) * 100) : 0,
    brands: brandTotal ? Math.round((summary.products_with_normalized_brand / brandTotal) * 100) : 0,
  }
})

onMounted(() => {
  loadIntelligence()
})

async function loadIntelligence(preferredId: number | null = selectedId.value) {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get('/taxonomy/intelligence')
    Object.assign(summary, emptySummary(), data.summary || {})
    version.value = data.active_version || null
    suggestions.value = data.suggestions || []
    learningEvents.value = data.learning_events || []
    selectPreferred(preferredId)
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar a taxonomia inteligente.'
  } finally {
    loading.value = false
  }
}

async function generateSuggestions() {
  generating.value = true
  error.value = ''

  try {
    const { data } = await api.post('/taxonomy/intelligence/generate', { type: 'all' })
    suggestions.value = data.suggestions || []
    Object.assign(summary, {
      pending_suggestions: data.summary?.pending ?? summary.pending_suggestions,
      review_required: data.summary?.review_required ?? summary.review_required,
    })
    showFeedback({
      status: 'success',
      title: 'Sugestões atualizadas',
      message: `${data.summary?.created || 0} nova(s), ${data.summary?.updated || 0} revisada(s).`,
    })
    await loadIntelligence(suggestions.value[0]?.id || null)
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível atualizar as sugestões.'
  } finally {
    generating.value = false
  }
}

async function approveSuggestion(suggestion: TaxonomyMappingSuggestion) {
  const lowConfidenceConfirmed = suggestion.confidence_level !== 'low'
    || window.confirm('Esta sugestão tem baixa confiança. Confirmar aplicação mesmo assim?')

  if (!lowConfidenceConfirmed) {
    return
  }

  reviewingId.value = suggestion.id
  error.value = ''

  try {
    const { data } = await api.post(`/taxonomy/suggestions/${suggestion.id}/approve`, {
      apply_to_products: true,
      confirm_low_confidence: suggestion.confidence_level === 'low',
    })
    showFeedback({
      status: 'success',
      title: 'Mapeamento aprovado',
      message: `${data.summary?.products_updated || 0} produto(s) atualizados.`,
    })
    await loadIntelligence(suggestion.id)
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível aprovar a sugestão.'
  } finally {
    reviewingId.value = null
  }
}

async function rejectSuggestion(suggestion: TaxonomyMappingSuggestion) {
  reviewingId.value = suggestion.id
  error.value = ''

  try {
    await api.post(`/taxonomy/suggestions/${suggestion.id}/reject`, {
      reason: 'Rejeitada na revisão da taxonomia inteligente.',
    })
    showFeedback({
      status: 'success',
      title: 'Sugestão rejeitada',
      message: 'O aprendizado foi registrado sem alterar produtos.',
    })
    await loadIntelligence()
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível rejeitar a sugestão.'
  } finally {
    reviewingId.value = null
  }
}

function selectPreferred(preferredId: number | null) {
  const preferred = suggestions.value.find((suggestion) => suggestion.id === preferredId)
    || pendingSuggestions.value[0]
    || suggestions.value[0]
    || null

  selectedId.value = preferred?.id || null
}

function confidenceLabel(value: string) {
  return {
    high: 'Alta',
    medium: 'Média',
    low: 'Baixa',
  }[value] || value
}

function confidenceTone(value: string) {
  return {
    high: 'ok',
    medium: 'warning',
    low: 'danger',
  }[value] || 'neutral'
}

function typeLabel(value: string) {
  return value === 'brand' ? 'Marca' : 'Categoria'
}

function statusLabel(value: string) {
  return {
    pending: 'Pendente',
    approved: 'Aprovada',
    applied: 'Aplicada',
    rejected: 'Rejeitada',
  }[value] || value
}

function statusTone(value: string) {
  return {
    pending: 'warning',
    approved: 'ok',
    applied: 'ok',
    rejected: 'neutral',
  }[value] || 'neutral'
}

function targetName(suggestion: TaxonomyMappingSuggestion | null) {
  if (!suggestion) {
    return '-'
  }

  return suggestion.taxonomy_category?.name
    || suggestion.normalized_brand?.name
    || suggestion.suggested_name
    || '-'
}

function signalLabel(value?: string | null) {
  if (!value) {
    return '-'
  }

  return {
    female: 'Feminino',
    male: 'Masculino',
    unisex: 'Unissex',
    kids: 'Infantil',
    adult: 'Adulto',
    baby: 'Bebê',
    br_alpha: 'BR alfabético',
    br_numeric: 'BR numérico',
    mixed: 'Misto',
    custom: 'Personalizado',
    unknown: 'Sem grade',
  }[value] || value
}

function percent(value: number) {
  return `${Math.max(0, Math.min(100, value))}%`
}
</script>

<template>
  <section class="dashboard app-workspace taxonomy-page">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Taxonomia IA</span>
        <h1>Taxonomia inteligente</h1>
        <p>Revise sugestões que conectam categoria, marca, gênero, faixa etária, modelagem e grade de tamanhos ao mesmo vocabulário operacional.</p>
      </div>
      <div class="action-row compact">
        <button class="btn btn-secondary" type="button" :disabled="loading" @click="loadIntelligence()">
          <i class="fa-solid fa-rotate" aria-hidden="true"></i>
          Atualizar
        </button>
        <button class="btn btn-primary" type="button" :disabled="generating" @click="generateSuggestions">
          <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
          {{ generating ? 'Analisando...' : 'Gerar sugestões' }}
        </button>
      </div>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>

    <section class="fit-diagnostic-panel taxonomy-version-panel">
      <div class="subsection-heading">
        <div>
          <h2>{{ version?.label || 'Taxonomia ativa' }}</h2>
          <span>{{ version?.version || summary.active_version }} · {{ summary.learning_events }} aprendizado(s) registrado(s)</span>
        </div>
        <span class="status-pill" :class="{ ok: summary.review_required === 0, warning: summary.review_required > 0 }">
          {{ summary.review_required }} revisão obrigatória
        </span>
      </div>

      <div class="fit-diagnostic-metrics taxonomy-metrics">
        <article>
          <span>Pendentes</span>
          <strong>{{ summary.pending_suggestions }}</strong>
        </article>
        <article>
          <span>Alta confiança</span>
          <strong>{{ summary.high_confidence }}</strong>
        </article>
        <article>
          <span>Produtos afetados</span>
          <strong>{{ summary.products_impacted }}</strong>
        </article>
        <article>
          <span>Candidatas</span>
          <strong>{{ summary.candidate_mappings }}</strong>
        </article>
      </div>

      <div class="taxonomy-coverage-grid">
        <article>
          <div>
            <strong>Categorias</strong>
            <span>{{ summary.products_with_normalized_category }} / {{ summary.products_with_category }} produto(s)</span>
          </div>
          <div class="taxonomy-progress" aria-hidden="true">
            <i :style="{ width: percent(coverage.categories) }"></i>
          </div>
        </article>
        <article>
          <div>
            <strong>Marcas</strong>
            <span>{{ summary.products_with_normalized_brand }} / {{ summary.products_with_brand }} produto(s)</span>
          </div>
          <div class="taxonomy-progress" aria-hidden="true">
            <i :style="{ width: percent(coverage.brands) }"></i>
          </div>
        </article>
      </div>
    </section>

    <div class="taxonomy-filter-row" role="tablist" aria-label="Filtro de sugestões">
      <button class="btn btn-secondary btn-compact" :class="{ active: filter === 'pending' }" type="button" @click="filter = 'pending'">
        Pendentes
      </button>
      <button class="btn btn-secondary btn-compact" :class="{ active: filter === 'category' }" type="button" @click="filter = 'category'">
        Categorias
      </button>
      <button class="btn btn-secondary btn-compact" :class="{ active: filter === 'brand' }" type="button" @click="filter = 'brand'">
        Marcas
      </button>
      <button class="btn btn-secondary btn-compact" :class="{ active: filter === 'reviewed' }" type="button" @click="filter = 'reviewed'">
        Revisadas
      </button>
    </div>

    <div class="app-grid taxonomy-workbench">
      <aside class="panel-list brand-list-panel taxonomy-list-panel">
        <button
          v-for="suggestion in filteredSuggestions"
          :key="suggestion.id"
          class="list-row brand-list-row taxonomy-suggestion-row"
          :class="{ active: selectedId === suggestion.id }"
          type="button"
          @click="selectedId = suggestion.id"
        >
          <span class="status-pill" :class="confidenceTone(suggestion.confidence_level)">
            {{ confidenceLabel(suggestion.confidence_level) }}
          </span>
          <strong>{{ suggestion.original_value }}</strong>
          <span>{{ typeLabel(suggestion.suggestion_type) }} · {{ suggestion.impact.products_count || 0 }} produto(s)</span>
          <small>{{ targetName(suggestion) }}</small>
        </button>
        <p v-if="!filteredSuggestions.length && !loading" class="empty-state">Nenhuma sugestão neste filtro.</p>
      </aside>

      <section class="panel-main taxonomy-detail-panel">
        <div v-if="selectedSuggestion" class="taxonomy-detail-grid">
          <div class="subsection-heading">
            <div>
              <h2>{{ selectedSuggestion.original_value }}</h2>
              <span>{{ typeLabel(selectedSuggestion.suggestion_type) }} para {{ targetName(selectedSuggestion) }}</span>
            </div>
            <span class="status-pill" :class="statusTone(selectedSuggestion.status)">
              {{ statusLabel(selectedSuggestion.status) }}
            </span>
          </div>

          <div class="taxonomy-confidence-panel" :class="confidenceTone(selectedSuggestion.confidence_level)">
            <strong>{{ confidenceLabel(selectedSuggestion.confidence_level) }} confiança</strong>
            <span>{{ Math.round(selectedSuggestion.confidence_score * 100) }}% · {{ selectedSuggestion.review_required ? 'confirmação obrigatória' : 'pronta para revisão' }}</span>
          </div>

          <div class="taxonomy-reason-list">
            <strong>Motivos</strong>
            <ul>
              <li v-for="reason in selectedSuggestion.reasons" :key="reason">{{ reason }}</li>
            </ul>
          </div>

          <div class="taxonomy-signal-grid">
            <article>
              <span>Gênero</span>
              <strong>{{ signalLabel(selectedSuggestion.context.signals?.gender) }}</strong>
            </article>
            <article>
              <span>Faixa etária</span>
              <strong>{{ signalLabel(selectedSuggestion.context.signals?.age_group) }}</strong>
            </article>
            <article>
              <span>Modelagem</span>
              <strong>{{ signalLabel(selectedSuggestion.context.signals?.fit_profile) }}</strong>
            </article>
            <article>
              <span>Grade</span>
              <strong>{{ signalLabel(selectedSuggestion.context.signals?.size_system) }}</strong>
            </article>
          </div>

          <div class="taxonomy-impact-list">
            <article>
              <span>Impacto</span>
              <strong>{{ selectedSuggestion.impact.products_count || 0 }} produto(s)</strong>
            </article>
            <article>
              <span>Uso</span>
              <strong>{{ selectedSuggestion.impact.uses?.join(' · ') || '-' }}</strong>
            </article>
          </div>

          <div v-if="selectedSuggestion.status === 'pending'" class="action-row compact">
            <button class="btn btn-primary" type="button" :disabled="Boolean(reviewingId)" @click="approveSuggestion(selectedSuggestion)">
              <i class="fa-solid fa-check" aria-hidden="true"></i>
              {{ reviewingId === selectedSuggestion.id ? 'Aplicando...' : 'Aprovar e aplicar' }}
            </button>
            <button class="btn btn-secondary" type="button" :disabled="Boolean(reviewingId)" @click="rejectSuggestion(selectedSuggestion)">
              <i class="fa-solid fa-xmark" aria-hidden="true"></i>
              Rejeitar
            </button>
          </div>
        </div>
        <p v-else class="empty-state">Gere sugestões para revisar a taxonomia do catálogo.</p>
      </section>
    </div>

    <section class="panel-main subsection taxonomy-learning-panel">
      <div class="subsection-heading">
        <div>
          <h2>Aprendizados recentes</h2>
          <span>{{ summary.learning_events }} evento(s) na base local</span>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Evento</th>
              <th>Tipo</th>
              <th>Original</th>
              <th>Normalizado</th>
              <th>Confiança</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="event in learningEvents" :key="event.id">
              <td>{{ event.event_type }}</td>
              <td>{{ typeLabel(event.target_type) }}</td>
              <td>{{ event.original_value || '-' }}</td>
              <td>{{ event.normalized_value || '-' }}</td>
              <td>{{ event.confidence_score ? `${Math.round(event.confidence_score * 100)}%` : '-' }}</td>
            </tr>
            <tr v-if="!learningEvents.length">
              <td colspan="5">Nenhum aprendizado registrado.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </section>
</template>
