<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { api } from '../services/api'
import { showFeedback } from '../services/saveFeedback'

type RuleKey = 'category' | 'brand' | 'gender' | 'age_group' | 'status' | 'fit_profile'

type ImportRule = {
  label: string
  enabled: boolean
  required: boolean
  source_field: string
  fallback: string | null
  aliases: Record<string, string>
}

type PlatformConnection = {
  id: number
  platform: string
  status: string
  import_rules: Record<RuleKey, ImportRule> | null
}

type Platform = {
  key: string
  name: string
  status: string
  connection: PlatformConnection | null
}

type RuleSimulationWarning = {
  id: string
  severity: 'info' | 'warning' | 'error'
  code: string
  message: string
  rule_keys: RuleKey[]
}

type RuleSimulationChange = {
  field: RuleKey
  label: string
  before: string | null
  after: string | null
  source_field: string | null
  source_value: string | null
  origin: string | null
}

type RuleSimulation = {
  platform: string
  sample_source: 'real_catalog' | 'synthetic' | string
  sample_total: number
  affected_products: number
  affected_percentage: number
  active_rules: number
  required_rules: number
  save_blocked: boolean
  warnings: RuleSimulationWarning[]
  impact_by_rule: Array<{
    key: RuleKey
    label: string
    enabled: boolean
    required: boolean
    source_field: string
    fallback: string | null
    affected_products: number
    affected_percentage: number
    missing_required: number
    fallback_applied: number
    status: 'ok' | 'warning' | 'muted' | string
    changed_values: Array<{ value: string, count: number }>
  }>
  rows: Array<{
    id: string
    name: string
    sku: string | null
    category: string | null
    brand: string | null
    changes: RuleSimulationChange[]
    warnings: RuleSimulationWarning[]
    status: 'changed' | 'unchanged' | string
  }>
}

const ruleKeys: RuleKey[] = ['category', 'brand', 'gender', 'age_group', 'status', 'fit_profile']
const ruleIcons: Record<RuleKey, string> = {
  category: 'fa-tags',
  brand: 'fa-copyright',
  gender: 'fa-venus-mars',
  age_group: 'fa-user-clock',
  status: 'fa-toggle-on',
  fit_profile: 'fa-shirt',
}
const sourceOptions: Record<RuleKey, string[]> = {
  category: ['category', 'categoria', 'product_type', 'tipo', 'google_product_category'],
  brand: ['brand', 'marca', 'manufacturer', 'fabricante'],
  gender: ['gender', 'genero', 'sexo'],
  age_group: ['age_group', 'faixa_etaria', 'idade'],
  status: ['status', 'availability', 'disponibilidade', 'ativo'],
  fit_profile: ['fit_profile', 'modelagem', 'fit', 'caimento'],
}
const fallbackOptions: Partial<Record<RuleKey, Array<{ value: string; label: string }>>> = {
  gender: [
    { value: 'female', label: 'Feminino' },
    { value: 'male', label: 'Masculino' },
    { value: 'unisex', label: 'Unissex' },
    { value: 'kids', label: 'Infantil' },
  ],
  age_group: [
    { value: 'adult', label: 'Adulto' },
    { value: 'teen', label: 'Teen' },
    { value: 'kids', label: 'Infantil' },
    { value: 'baby', label: 'Bebê' },
  ],
  status: [
    { value: 'active', label: 'Ativo' },
    { value: 'inactive', label: 'Inativo' },
    { value: 'draft', label: 'Rascunho' },
    { value: 'archived', label: 'Arquivado' },
  ],
  fit_profile: [
    { value: 'regular', label: 'Regular' },
    { value: 'slim', label: 'Slim' },
    { value: 'loose', label: 'Ampla' },
    { value: 'oversized', label: 'Oversized' },
    { value: 'comfort', label: 'Conforto' },
  ],
}
const examplePayload: Record<string, string> = {
  category: 'Vestidos',
  categoria: 'Vestidos',
  product_type: 'Full Body',
  brand: 'Zak',
  marca: 'Zak',
  gender: 'Feminino',
  genero: 'Feminino',
  age_group: 'Adulto',
  faixa_etaria: 'Adulto',
  status: 'Ativo',
  availability: 'in stock',
  disponibilidade: 'em estoque',
  fit_profile: 'Ampla',
  modelagem: 'Ampla',
}

const platforms = ref<Platform[]>([])
const selectedPlatformKey = ref('bigshop')
const selectedRuleKey = ref<RuleKey>('category')
const loading = ref(false)
const saving = ref(false)
const simulating = ref(false)
const simulation = ref<RuleSimulation | null>(null)
const simulationSignature = ref('')
const aliasDraft = reactive({ alias: '', target: '' })
const rules = reactive<Record<RuleKey, ImportRule>>(defaultRules())

const selectedPlatform = computed(() => platforms.value.find((platform) => platform.key === selectedPlatformKey.value) || platforms.value[0] || null)
const selectedRule = computed(() => rules[selectedRuleKey.value])
const activeRules = computed(() => ruleKeys.filter((key) => rules[key].enabled).length)
const requiredRules = computed(() => ruleKeys.filter((key) => rules[key].enabled && rules[key].required).length)
const fallbackRules = computed(() => ruleKeys.filter((key) => rules[key].enabled && rules[key].fallback).length)
const previewRows = computed(() => ruleKeys.map((key) => previewRule(key)))
const currentRulesSignature = computed(() => JSON.stringify(serializedRules()))
const simulationIsStale = computed(() => Boolean(simulation.value && simulationSignature.value !== currentRulesSignature.value))
const simulationRequired = computed(() => !simulation.value || simulationIsStale.value)
const blockingSimulation = computed(() => Boolean(simulation.value?.save_blocked && !simulationIsStale.value))
const saveBlocked = computed(() => saving.value || simulationRequired.value || blockingSimulation.value)
const criticalWarnings = computed(() => (simulation.value?.warnings || []).filter((warning) => warning.severity === 'error'))
const nonCriticalWarnings = computed(() => (simulation.value?.warnings || []).filter((warning) => warning.severity !== 'error'))
const changedSimulationRows = computed(() => (simulation.value?.rows || []).filter((row) => row.status === 'changed'))

onMounted(() => {
  loadPlatforms()
})

async function loadPlatforms() {
  loading.value = true

  try {
    const { data } = await api.get('/integrations')
    platforms.value = data.data

    if (!platforms.value.find((platform) => platform.key === selectedPlatformKey.value)) {
      selectedPlatformKey.value = platforms.value[0]?.key || 'bigshop'
    }

    fillRules()
  } finally {
    loading.value = false
  }
}

function fillRules() {
  replaceRules(selectedPlatform.value?.connection?.import_rules || defaultRules())
  simulation.value = null
  simulationSignature.value = ''
}

function selectRule(key: RuleKey) {
  selectedRuleKey.value = key
  aliasDraft.alias = ''
  aliasDraft.target = ''
}

async function saveRules() {
  if (!selectedPlatform.value) {
    return
  }

  if (simulationRequired.value) {
    showFeedback({
      status: 'error',
      title: 'Simule o impacto',
      message: 'Execute a simulação atual antes de salvar as regras.',
    })
    return
  }

  if (blockingSimulation.value) {
    showFeedback({
      status: 'error',
      title: 'Simulação com bloqueios',
      message: 'Ajuste os conflitos sinalizados antes de salvar as regras.',
    })
    return
  }

  saving.value = true

  try {
    await api.patch(`/integrations/${selectedPlatform.value.key}`, {
      import_rules: serializedRules(),
    })
    await loadPlatforms()
    showFeedback({
      status: 'success',
      title: 'Regras salvas',
      message: 'As próximas prévias e sincronizações vão usar este mapeamento.',
    })
  } catch (requestError: any) {
    showFeedback({
      status: 'error',
      title: 'Não foi possível salvar',
      message: requestError.response?.data?.message || 'Revise os campos das regras de importação.',
    })
  } finally {
    saving.value = false
  }
}

function resetRules() {
  replaceRules(defaultRules())
  simulation.value = null
  simulationSignature.value = ''
}

async function simulateRules() {
  if (!selectedPlatform.value) {
    return
  }

  simulating.value = true

  try {
    const signature = currentRulesSignature.value
    const { data } = await api.post(`/integrations/${selectedPlatform.value.key}/import-rules/simulate`, {
      import_rules: serializedRules(),
    })
    simulation.value = data.data
    simulationSignature.value = signature
  } catch (requestError: any) {
    showFeedback({
      status: 'error',
      title: 'Não foi possível simular',
      message: requestError.response?.data?.message || 'Revise as regras antes de testar o impacto.',
    })
  } finally {
    simulating.value = false
  }
}

function addAlias() {
  const alias = aliasDraft.alias.trim()
  const target = aliasDraft.target.trim()

  if (!alias || !target) {
    return
  }

  selectedRule.value.aliases[alias] = target
  aliasDraft.alias = ''
  aliasDraft.target = ''
}

function renameAlias(oldAlias: string, newAlias: string) {
  const alias = newAlias.trim()

  if (!alias || alias === oldAlias) {
    return
  }

  const value = selectedRule.value.aliases[oldAlias]
  delete selectedRule.value.aliases[oldAlias]
  selectedRule.value.aliases[alias] = value
}

function removeAlias(alias: string) {
  delete selectedRule.value.aliases[alias]
}

function inputValue(event: Event) {
  return (event.target as HTMLInputElement).value
}

function serializedRules() {
  return ruleKeys.reduce((payload, key) => {
    payload[key] = {
      enabled: rules[key].enabled,
      required: rules[key].required,
      source_field: rules[key].source_field,
      fallback: rules[key].fallback || null,
      aliases: { ...rules[key].aliases },
    }

    return payload
  }, {} as Record<RuleKey, Omit<ImportRule, 'label'>>)
}

function replaceRules(nextRules: Record<string, Partial<ImportRule>>) {
  const normalized = normalizeRules(nextRules)

  ruleKeys.forEach((key) => {
    rules[key] = normalized[key]
  })
}

function normalizeRules(nextRules?: Record<string, Partial<ImportRule>> | null) {
  const base = defaultRules()
  const source = nextRules || {}

  ruleKeys.forEach((key) => {
    const incoming = source[key] || {}
    base[key] = {
      ...base[key],
      ...incoming,
      fallback: incoming.fallback ?? base[key].fallback,
      aliases: { ...base[key].aliases, ...(incoming.aliases || {}) },
    }
  })

  return base
}

function defaultRules(): Record<RuleKey, ImportRule> {
  return {
    category: rule('Categoria', 'category', null, true),
    brand: rule('Marca', 'brand'),
    gender: rule('Gênero', 'gender', 'unisex', true, {
      Feminino: 'female',
      Masculino: 'male',
      Infantil: 'kids',
      Unissex: 'unisex',
    }),
    age_group: rule('Faixa etária', 'age_group', 'adult', false, {
      Adulto: 'adult',
      Infantil: 'kids',
      Bebê: 'baby',
      Teen: 'teen',
    }),
    status: rule('Status', 'status', 'active', true, {
      Ativo: 'active',
      'Em estoque': 'active',
      Inativo: 'inactive',
      'Sem estoque': 'inactive',
      Rascunho: 'draft',
    }),
    fit_profile: rule('Modelagem', 'fit_profile', 'regular', true, {
      Regular: 'regular',
      Ampla: 'loose',
      Slim: 'slim',
      Oversized: 'oversized',
      Conforto: 'comfort',
    }),
  }
}

function rule(label: string, sourceField: string, fallback: string | null = null, required = false, aliases: Record<string, string> = {}): ImportRule {
  return {
    label,
    enabled: true,
    required,
    source_field: sourceField,
    fallback,
    aliases,
  }
}

function previewRule(key: RuleKey) {
  const rule = rules[key]
  const raw = examplePayload[rule.source_field] || sourceOptions[key].map((field) => examplePayload[field]).find(Boolean) || null
  const alias = raw ? Object.entries(rule.aliases).find(([entry]) => normalize(entry) === normalize(raw))?.[1] : null
  const normalized = normalizeValue(key, alias || raw)
  const value = normalized || (rule.fallback ? normalizeValue(key, rule.fallback) : null)
  const origin = normalized ? rule.source_field : (value ? 'fallback' : null)

  return {
    key,
    label: rule.label,
    enabled: rule.enabled,
    required: rule.required,
    raw,
    value,
    origin,
    status: !rule.enabled ? 'muted' : value ? 'ok' : (rule.required ? 'warning' : 'muted'),
  }
}

function normalizeValue(key: RuleKey, value: string | null) {
  if (!value) {
    return null
  }

  const normalized = normalize(value)

  if (key === 'gender') {
    return ({
      feminino: 'female',
      female: 'female',
      masculino: 'male',
      male: 'male',
      infantil: 'kids',
      kids: 'kids',
      unissex: 'unisex',
      unisex: 'unisex',
    } as Record<string, string>)[normalized] || normalized
  }

  if (key === 'age_group') {
    return ({
      adulto: 'adult',
      adult: 'adult',
      infantil: 'kids',
      crianca: 'kids',
      kids: 'kids',
      bebe: 'baby',
      baby: 'baby',
      adolescente: 'teen',
      teen: 'teen',
    } as Record<string, string>)[normalized] || normalized
  }

  if (key === 'status') {
    return ({
      ativo: 'active',
      active: 'active',
      'em estoque': 'active',
      'in stock': 'active',
      disponivel: 'active',
      inativo: 'inactive',
      inactive: 'inactive',
      'sem estoque': 'inactive',
      'out of stock': 'inactive',
      indisponivel: 'inactive',
      rascunho: 'draft',
      draft: 'draft',
    } as Record<string, string>)[normalized] || normalized
  }

  if (key === 'fit_profile') {
    return ({
      regular: 'regular',
      padrao: 'regular',
      tradicional: 'regular',
      ampla: 'loose',
      solta: 'loose',
      loose: 'loose',
      slim: 'slim',
      ajustada: 'slim',
      oversized: 'oversized',
      conforto: 'comfort',
      comfort: 'comfort',
    } as Record<string, string>)[normalized] || normalized
  }

  return value
}

function normalize(value: string) {
  return value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .trim()
    .toLowerCase()
    .replace(/[_-]+/g, ' ')
    .replace(/\s+/g, ' ')
}

function warningTone(severity: string) {
  return {
    error: 'error',
    warning: 'warning',
    info: 'info',
  }[severity] || 'info'
}

function simulationSourceLabel(source: string) {
  return source === 'real_catalog' ? 'catálogo real' : 'amostra técnica'
}
</script>

<template>
  <section class="dashboard app-workspace import-rules-page">
    <div class="page-heading">
      <div>
        <span class="eyebrow">Importações</span>
        <h1>Regras de importação</h1>
      </div>
      <div class="action-row compact">
        <button class="btn btn-secondary btn-compact" type="button" :disabled="simulating" @click="simulateRules">
          <i class="fa-solid fa-flask" aria-hidden="true"></i>
          {{ simulating ? 'Simulando...' : 'Simular impacto' }}
        </button>
        <button class="btn btn-primary btn-compact" type="button" :disabled="saveBlocked" @click="saveRules">
          <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
          {{ saving ? 'Salvando...' : 'Salvar regras' }}
        </button>
      </div>
    </div>

    <div v-if="loading" class="empty-state">Carregando regras...</div>

    <div v-else class="app-grid import-rules-layout">
      <aside class="panel-list import-rules-sidebar">
        <label>
          <span class="field-label">Plataforma</span>
          <select v-model="selectedPlatformKey" @change="fillRules">
            <option v-for="platform in platforms" :key="platform.key" :value="platform.key">
              {{ platform.name }}
            </option>
          </select>
        </label>

        <div class="summary-strip import-rules-summary">
          <span>
            <strong>{{ activeRules }}</strong>
            <small>ativas</small>
          </span>
          <span>
            <strong>{{ requiredRules }}</strong>
            <small>obrigatórias</small>
          </span>
          <span>
            <strong>{{ fallbackRules }}</strong>
            <small>fallbacks</small>
          </span>
        </div>

        <button
          v-for="key in ruleKeys"
          :key="key"
          class="list-row import-rule-row"
          :class="{ active: selectedRuleKey === key }"
          type="button"
          @click="selectRule(key)"
        >
          <span class="import-rule-row-title">
            <i class="fa-solid" :class="ruleIcons[key]" aria-hidden="true"></i>
            <strong>{{ rules[key].label }}</strong>
            <em :class="{ ok: rules[key].enabled }">{{ rules[key].enabled ? 'Ativa' : 'Pausada' }}</em>
          </span>
          <small>{{ rules[key].source_field }}{{ rules[key].fallback ? ` -> ${rules[key].fallback}` : '' }}</small>
        </button>
      </aside>

      <form class="panel-main admin-form" @submit.prevent="saveRules">
        <div class="subsection-heading">
          <h2>{{ selectedRule.label }}</h2>
          <span>{{ selectedPlatform?.name || 'Plataforma' }}</span>
        </div>

        <div class="import-rule-editor-grid">
          <label class="check-line">
            <input v-model="selectedRule.enabled" type="checkbox" />
            <span>Aplicar regra</span>
          </label>
          <label class="check-line">
            <input v-model="selectedRule.required" type="checkbox" />
            <span>Campo obrigatório</span>
          </label>
          <label>
            <span class="field-label">Campo de origem</span>
            <select v-model="selectedRule.source_field">
              <option v-for="field in sourceOptions[selectedRuleKey]" :key="field" :value="field">
                {{ field }}
              </option>
            </select>
          </label>
          <label>
            <span class="field-label">Fallback</span>
            <select v-if="fallbackOptions[selectedRuleKey]" v-model="selectedRule.fallback">
              <option :value="null">Sem fallback</option>
              <option v-for="option in fallbackOptions[selectedRuleKey]" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
            <input v-else v-model="selectedRule.fallback" maxlength="120" placeholder="Sem fallback" />
          </label>
        </div>

        <div class="guide-panel import-rule-alias-panel">
          <div class="subsection-heading">
            <h2>Normalizações</h2>
            <span>{{ Object.keys(selectedRule.aliases).length }}</span>
          </div>

          <div class="alias-list">
            <div v-for="alias in Object.keys(selectedRule.aliases)" :key="alias" class="alias-row">
              <input :value="alias" maxlength="120" @change="renameAlias(String(alias), inputValue($event))" />
              <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
              <input v-model="selectedRule.aliases[alias]" maxlength="120" />
              <button class="icon-link" type="button" title="Remover" @click="removeAlias(String(alias))">
                <i class="fa-solid fa-trash" aria-hidden="true"></i>
              </button>
            </div>
          </div>

          <div class="alias-row alias-row-new">
            <input v-model="aliasDraft.alias" maxlength="120" placeholder="Entrada" />
            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            <input v-model="aliasDraft.target" maxlength="120" placeholder="Valor final" />
            <button class="icon-link" type="button" title="Adicionar" @click="addAlias">
              <i class="fa-solid fa-plus" aria-hidden="true"></i>
            </button>
          </div>
        </div>

        <div class="guide-panel import-rule-preview">
          <div class="subsection-heading">
            <h2>Prévia</h2>
            <button class="btn btn-secondary btn-compact" type="button" :disabled="simulating" @click="simulateRules">
              <i class="fa-solid fa-flask" aria-hidden="true"></i>
              {{ simulating ? 'Simulando...' : 'Simular impacto' }}
            </button>
          </div>

          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Regra</th>
                  <th>Origem</th>
                  <th>Entrada</th>
                  <th>Resultado</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in previewRows" :key="row.key">
                  <td>{{ row.label }}</td>
                  <td>{{ row.origin || '-' }}</td>
                  <td>{{ row.raw || '-' }}</td>
                  <td><strong>{{ row.value || '-' }}</strong></td>
                  <td>
                    <span class="status-pill" :class="{ ok: row.status === 'ok', warning: row.status === 'warning' }">
                      {{ row.status === 'ok' ? 'Ok' : row.status === 'warning' ? 'Atenção' : 'Pausada' }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-if="simulation" class="guide-panel import-rule-impact-panel">
          <div class="subsection-heading">
            <h2>Impacto no catálogo</h2>
            <span>{{ simulationSourceLabel(simulation.sample_source) }}</span>
          </div>

          <div class="summary-strip import-rule-impact-summary">
            <span>
              <strong>{{ simulation.affected_products }}</strong>
              <small>produtos afetados</small>
            </span>
            <span>
              <strong>{{ simulation.affected_percentage }}%</strong>
              <small>da amostra</small>
            </span>
            <span>
              <strong>{{ simulation.sample_total }}</strong>
              <small>produtos testados</small>
            </span>
            <span>
              <strong>{{ simulation.active_rules }}</strong>
              <small>regras ativas</small>
            </span>
          </div>

          <div v-if="simulationIsStale" class="impact-alert info">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            <span>Simulação desatualizada</span>
          </div>

          <div v-if="criticalWarnings.length" class="impact-warning-list">
            <article v-for="warning in criticalWarnings" :key="warning.id" :class="warningTone(warning.severity)">
              <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
              <span>
                <strong>Bloqueio</strong>
                <small>{{ warning.message }}</small>
              </span>
            </article>
          </div>

          <div v-if="nonCriticalWarnings.length" class="impact-warning-list compact">
            <article v-for="warning in nonCriticalWarnings" :key="warning.id" :class="warningTone(warning.severity)">
              <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
              <span>
                <strong>{{ warning.severity === 'warning' ? 'Atenção' : 'Nota' }}</strong>
                <small>{{ warning.message }}</small>
              </span>
            </article>
          </div>

          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Regra</th>
                  <th>Origem</th>
                  <th>Afetados</th>
                  <th>Fallback</th>
                  <th>Obrigatórios</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in simulation.impact_by_rule" :key="item.key">
                  <td>
                    <strong>{{ item.label }}</strong>
                    <small>{{ item.enabled ? 'ativa' : 'pausada' }}</small>
                  </td>
                  <td>
                    {{ item.source_field }}
                    <small v-if="item.fallback">fallback {{ item.fallback }}</small>
                  </td>
                  <td>{{ item.affected_products }} · {{ item.affected_percentage }}%</td>
                  <td>{{ item.fallback_applied }}</td>
                  <td>{{ item.missing_required }}</td>
                  <td>
                    <span class="status-pill" :class="{ ok: item.status === 'ok', warning: item.status === 'warning' }">
                      {{ item.status === 'warning' ? 'Atenção' : item.status === 'muted' ? 'Pausada' : 'Ok' }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="changedSimulationRows.length" class="impact-change-list">
            <article v-for="row in changedSimulationRows.slice(0, 8)" :key="row.id">
              <span>
                <strong>{{ row.name || row.id }}</strong>
                <small>{{ row.sku || '-' }} · {{ row.category || '-' }} · {{ row.brand || '-' }}</small>
              </span>
              <div>
                <span v-for="change in row.changes" :key="`${row.id}-${change.field}`" class="impact-change-row">
                  <em>{{ change.label }}</em>
                  <small>{{ change.before || '-' }} -> {{ change.after || '-' }}</small>
                  <strong>{{ change.origin || '-' }}</strong>
                </span>
              </div>
            </article>
          </div>

          <div v-else class="empty-state">Nenhuma mudança detectada na amostra.</div>
        </div>

        <div class="action-row">
          <button class="btn btn-secondary" type="button" @click="resetRules">
            <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
            Restaurar padrão
          </button>
          <button class="btn btn-secondary" type="button" :disabled="simulating" @click="simulateRules">
            <i class="fa-solid fa-flask" aria-hidden="true"></i>
            {{ simulating ? 'Simulando...' : 'Simular impacto' }}
          </button>
          <button class="btn btn-primary" type="submit" :disabled="saveBlocked">
            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
            {{ saving ? 'Salvando...' : 'Salvar regras' }}
          </button>
        </div>
      </form>
    </div>
  </section>
</template>
