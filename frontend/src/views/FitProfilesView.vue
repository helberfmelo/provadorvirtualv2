<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { api } from '../services/api'
import type { FitProfile, FitProfileDiagnosticGroup, FitProfileDiagnostics, FitProfileSuggestion } from '../services/merchantTypes'
import { showFeedback } from '../services/saveFeedback'

const profiles = ref<FitProfile[]>([])
const diagnostics = ref<FitProfileDiagnostics | null>(null)
const selectedId = ref<number | null>(null)
const loading = ref(false)
const diagnosticsLoading = ref(false)
const saving = ref(false)
const applyingGroupKey = ref('')
const error = ref('')

const form = reactive({
  name: '',
  code: '',
  product_type: '',
  gender: 'unisex',
  fit_intensity: 'regular',
  stretch_level: 'medium',
  status: 'active',
  description: '',
})

const selectedProfile = computed(() => profiles.value.find((profile) => profile.id === selectedId.value) || null)
const summary = computed(() => ({
  total: profiles.value.length,
  active: profiles.value.filter((profile) => profile.status === 'active').length,
  used: profiles.value.filter((profile) => usageTotal(profile) > 0).length,
}))
const diagnosticSummary = computed(() => diagnostics.value?.summary || {
  products_analyzed: 0,
  issues: 0,
  without_modeling: 0,
  modeling_not_found: 0,
  modeling_inactive: 0,
  modeling_incompatible: 0,
  groups: 0,
})
const topDiagnosticGroups = computed(() => diagnostics.value?.groups.slice(0, 6) || [])
const selectedImpact = computed(() => selectedProfile.value?.guidance?.recommendation_impact || null)

const genderLabels: Record<string, string> = {
  female: 'Feminino',
  male: 'Masculino',
  unisex: 'Unissex',
  kids: 'Infantil',
}

const intensityLabels: Record<string, string> = {
  very_slim: 'Muito ajustada',
  slim: 'Slim',
  regular: 'Regular',
  relaxed: 'Relaxada',
  oversized: 'Ampla',
  custom: 'Personalizada',
}

const stretchLabels: Record<string, string> = {
  none: 'Sem elasticidade',
  low: 'Baixa',
  medium: 'Média',
  high: 'Alta',
}

onMounted(() => {
  loadProfiles()
  loadDiagnostics()
})

async function loadProfiles(preferredId: number | null = selectedId.value) {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get('/fit-profiles')
    profiles.value = data.data

    const target = profiles.value.find((profile) => profile.id === preferredId) || profiles.value[0] || null
    if (target) {
      selectProfile(target)
    } else {
      newProfile()
    }
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar as modelagens.'
  } finally {
    loading.value = false
  }
}

async function loadDiagnostics() {
  diagnosticsLoading.value = true

  try {
    const { data } = await api.get('/fit-profiles/diagnostics')
    diagnostics.value = data
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar o diagnóstico de modelagens.'
  } finally {
    diagnosticsLoading.value = false
  }
}

function selectProfile(profile: FitProfile) {
  selectedId.value = profile.id
  form.name = profile.name
  form.code = profile.code
  form.product_type = profile.product_type ?? ''
  form.gender = profile.gender ?? 'unisex'
  form.fit_intensity = profile.fit_intensity ?? 'regular'
  form.stretch_level = profile.stretch_level ?? 'medium'
  form.status = profile.status
  form.description = profile.description ?? ''
}

function newProfile() {
  selectedId.value = null
  form.name = ''
  form.code = ''
  form.product_type = ''
  form.gender = 'unisex'
  form.fit_intensity = 'regular'
  form.stretch_level = 'medium'
  form.status = 'active'
  form.description = ''
}

function usageTotal(profile: FitProfile) {
  return (profile.products_count || 0) + (profile.measurement_tables_count || 0)
}

function statusLabel(status: string) {
  if (status === 'active') {
    return 'Ativa'
  }

  if (status === 'draft') {
    return 'Rascunho'
  }

  return 'Inativa'
}

function confidenceLabel(value?: string) {
  if (value === 'high') {
    return 'alta'
  }

  if (value === 'medium') {
    return 'média'
  }

  return 'baixa'
}

function suggestionModeLabel(suggestion: FitProfileSuggestion) {
  return suggestion.mode === 'existing' ? 'Aplicar existente' : 'Criar e aplicar'
}

function suggestionPayload(suggestion: FitProfileSuggestion) {
  return suggestion.profile || {
    name: suggestion.name,
    code: suggestion.code,
    product_type: suggestion.product_type || null,
    gender: suggestion.gender || 'unisex',
    fit_intensity: suggestion.fit_intensity || 'regular',
    stretch_level: suggestion.stretch_level || 'medium',
    description: 'Criada pelo diagnóstico guiado de modelagens.',
  }
}

function groupProductLine(group: FitProfileDiagnosticGroup) {
  return [group.category, group.brand, group.gender, group.age_group].filter(Boolean).join(' · ') || 'Grupo operacional'
}

async function applyDiagnosticGroup(group: FitProfileDiagnosticGroup) {
  if (!group.product_ids.length) {
    return
  }

  applyingGroupKey.value = group.key
  error.value = ''

  const suggestion = group.suggested_profile
  const payload = suggestion.mode === 'existing' && suggestion.id
    ? { product_ids: group.product_ids, profile_id: suggestion.id }
    : { product_ids: group.product_ids, profile: suggestionPayload(suggestion) }

  try {
    const { data } = await api.post('/fit-profiles/diagnostics/apply', payload)
    showFeedback({
      status: 'success',
      title: suggestion.mode === 'existing' ? 'Modelagem aplicada' : 'Modelagem criada',
      message: `${data.summary?.updated || 0} produto(s) atualizados no diagnóstico.`,
    })
    await loadProfiles(data.profile?.id || selectedId.value)
    await loadDiagnostics()
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível aplicar a correção de modelagem.'
  } finally {
    applyingGroupKey.value = ''
  }
}

async function saveProfile() {
  saving.value = true
  error.value = ''

  const payload = {
    name: form.name,
    code: form.code || null,
    product_type: form.product_type || null,
    gender: form.gender || null,
    fit_intensity: form.fit_intensity,
    stretch_level: form.stretch_level,
    status: form.status,
    description: form.description || null,
  }

  try {
    const { data } = selectedId.value
      ? await api.patch(`/fit-profiles/${selectedId.value}`, payload)
      : await api.post('/fit-profiles', payload)

    showFeedback({
      status: 'success',
      title: 'Modelagem salva',
      message: 'O cadastro de modelagem foi atualizado.',
    })
    await loadProfiles(data.data.id)
    await loadDiagnostics()
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível salvar a modelagem.'
  } finally {
    saving.value = false
  }
}

async function removeProfile() {
  if (!selectedProfile.value) {
    return
  }

  await api.delete(`/fit-profiles/${selectedProfile.value.id}`)
  showFeedback({
    status: 'success',
    title: 'Modelagem removida',
    message: 'A modelagem foi removida do cadastro.',
  })
  await loadProfiles(null)
  await loadDiagnostics()
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Modelagens</span>
        <h1>Modelagens</h1>
        <p>Cadastre caimentos para usar em produtos, tabelas e regras de importação.</p>
      </div>
      <div class="action-row compact">
        <button class="btn btn-secondary" type="button" :disabled="loading" @click="loadProfiles()">
          <i class="fa-solid fa-rotate" aria-hidden="true"></i>
          Atualizar
        </button>
        <button class="btn btn-secondary" type="button" :disabled="diagnosticsLoading" @click="loadDiagnostics">
          <i class="fa-solid fa-stethoscope" aria-hidden="true"></i>
          Diagnosticar
        </button>
        <button class="btn btn-primary" type="button" @click="newProfile">
          <i class="fa-solid fa-plus" aria-hidden="true"></i>
          Nova modelagem
        </button>
      </div>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>

    <section class="fit-diagnostic-panel">
      <div class="subsection-heading">
        <div>
          <h2>Diagnóstico de modelagens</h2>
          <span>{{ diagnosticSummary.products_analyzed }} produtos analisados · {{ diagnosticSummary.issues }} pendências</span>
        </div>
        <span class="status-pill" :class="{ ok: diagnosticSummary.issues === 0, warning: diagnosticSummary.issues > 0 }">
          {{ diagnosticSummary.issues === 0 ? 'Sem pendências' : `${diagnosticSummary.groups} grupos` }}
        </span>
      </div>

      <div class="fit-diagnostic-metrics">
        <article>
          <span>Sem modelagem</span>
          <strong>{{ diagnosticSummary.without_modeling }}</strong>
        </article>
        <article>
          <span>Não encontrada</span>
          <strong>{{ diagnosticSummary.modeling_not_found }}</strong>
        </article>
        <article>
          <span>Inativa</span>
          <strong>{{ diagnosticSummary.modeling_inactive }}</strong>
        </article>
        <article>
          <span>Incompatível</span>
          <strong>{{ diagnosticSummary.modeling_incompatible }}</strong>
        </article>
      </div>

      <div v-if="topDiagnosticGroups.length" class="fit-diagnostic-groups">
        <article v-for="group in topDiagnosticGroups" :key="group.key" class="fit-diagnostic-group">
          <div class="fit-diagnostic-group-main">
            <span class="status-pill" :class="{ danger: group.severity === 'danger', warning: group.severity !== 'danger' }">
              {{ group.title }}
            </span>
            <h3>{{ group.suggested_profile.name }}</h3>
            <p>{{ group.cause }}</p>
            <small>{{ groupProductLine(group) }} · {{ group.products_count }} produto(s)</small>
            <div class="variation-preview-tags">
              <span v-for="product in group.sample_products.slice(0, 4)" :key="product.id">
                {{ product.name }}
              </span>
            </div>
          </div>
          <div class="fit-diagnostic-action">
            <strong>{{ suggestionModeLabel(group.suggested_profile) }}</strong>
            <span>Confiança {{ confidenceLabel(group.suggested_profile.confidence) }}</span>
            <small>{{ group.suggested_profile.reasons?.join(' · ') }}</small>
            <button
              class="btn btn-primary btn-compact"
              type="button"
              :disabled="Boolean(applyingGroupKey)"
              @click="applyDiagnosticGroup(group)"
            >
              <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
              {{ applyingGroupKey === group.key ? 'Aplicando...' : 'Aplicar grupo' }}
            </button>
          </div>
        </article>
      </div>
      <p v-else-if="!diagnosticsLoading" class="empty-state">Nenhuma correção de modelagem pendente.</p>
    </section>

    <div class="app-grid">
      <aside class="panel-list">
        <button
          v-for="profile in profiles"
          :key="profile.id"
          class="list-row"
          :class="{ active: selectedId === profile.id }"
          type="button"
          @click="selectProfile(profile)"
        >
          <strong>{{ profile.name }}</strong>
          <span>{{ intensityLabels[profile.fit_intensity] || profile.fit_intensity }} · {{ profile.code }}</span>
          <span>{{ usageTotal(profile) }} vínculos</span>
        </button>
        <p v-if="!profiles.length && !loading" class="empty-state">Nenhuma modelagem cadastrada.</p>
      </aside>

      <form class="panel-main admin-form" @submit.prevent="saveProfile">
        <div class="subsection-heading">
          <div>
            <h2>{{ selectedProfile ? 'Editar modelagem' : 'Nova modelagem' }}</h2>
            <span>{{ summary.total }} cadastradas · {{ summary.active }} ativas · {{ summary.used }} em uso</span>
          </div>
          <span v-if="selectedProfile" class="status-pill" :class="{ ok: selectedProfile.status === 'active', warning: selectedProfile.status !== 'active' }">
            {{ statusLabel(selectedProfile.status) }}
          </span>
        </div>

        <div class="form-grid">
          <label>
            Nome
            <input v-model.trim="form.name" required maxlength="120" />
          </label>
          <label>
            Código
            <input v-model.trim="form.code" maxlength="80" />
          </label>
          <label>
            Tipo
            <input v-model.trim="form.product_type" maxlength="80" placeholder="Ex.: dress, pants, shirt" />
          </label>
          <label>
            Gênero
            <select v-model="form.gender">
              <option value="female">Feminino</option>
              <option value="male">Masculino</option>
              <option value="unisex">Unissex</option>
              <option value="kids">Infantil</option>
            </select>
          </label>
          <label>
            Intensidade
            <select v-model="form.fit_intensity">
              <option value="very_slim">Muito ajustada</option>
              <option value="slim">Slim</option>
              <option value="regular">Regular</option>
              <option value="relaxed">Relaxada</option>
              <option value="oversized">Ampla</option>
              <option value="custom">Personalizada</option>
            </select>
          </label>
          <label>
            Elasticidade
            <select v-model="form.stretch_level">
              <option value="none">Sem elasticidade</option>
              <option value="low">Baixa</option>
              <option value="medium">Média</option>
              <option value="high">Alta</option>
            </select>
          </label>
          <label>
            Status
            <select v-model="form.status">
              <option value="active">Ativa</option>
              <option value="draft">Rascunho</option>
              <option value="inactive">Inativa</option>
            </select>
          </label>
          <label>
            Uso
            <input
              disabled
              :value="selectedProfile ? `${selectedProfile.products_count} produtos · ${selectedProfile.measurement_tables_count} tabelas` : 'Sem vínculos'"
            />
          </label>
          <label class="full-row">
            Descrição
            <textarea v-model.trim="form.description" rows="3" maxlength="600"></textarea>
          </label>
        </div>

        <div class="detail-strip">
          <span><strong>{{ intensityLabels[form.fit_intensity] }}</strong> intensidade</span>
          <span><strong>{{ stretchLabels[form.stretch_level] }}</strong> elasticidade</span>
          <span><strong>{{ genderLabels[form.gender] }}</strong> gênero</span>
        </div>

        <div class="fit-impact-panel">
          <div>
            <strong>Impacto na recomendação</strong>
            <span>{{ selectedImpact?.summary || 'Caimento regular: base neutra para revisar recomendações e feedback.' }}</span>
          </div>
          <small>{{ selectedImpact?.confidence_hint || 'Elasticidade média mantém tolerância padrão.' }}</small>
        </div>

        <div class="action-row compact">
          <button class="btn btn-primary" type="submit" :disabled="saving || loading">
            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
            {{ saving ? 'Salvando...' : 'Salvar modelagem' }}
          </button>
          <button class="btn btn-secondary" type="button" @click="newProfile">
            <i class="fa-solid fa-eraser" aria-hidden="true"></i>
            Limpar
          </button>
          <button
            v-if="selectedProfile"
            class="btn btn-secondary"
            type="button"
            :disabled="usageTotal(selectedProfile) > 0"
            @click="removeProfile"
          >
            <i class="fa-solid fa-trash" aria-hidden="true"></i>
            Remover
          </button>
        </div>
      </form>
    </div>
  </section>
</template>
