<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../services/api'
import type { CheckoutProviderOption, CheckoutSettings } from '../services/saasTypes'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const settings = ref<CheckoutSettings>({
  payment_provider: 'mercado_pago',
  active_provider_configured: false,
  boleto_enabled: false,
  providers: [],
})
const selectedProvider = ref('mercado_pago')
const boletoEnabled = ref(false)
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const saved = ref('')

const canEdit = computed(() => auth.canSaasEdit('saas_checkout'))
const activeProvider = computed(() => settings.value.providers.find((provider) => provider.key === settings.value.payment_provider))

onMounted(() => {
  loadSettings()
})

async function loadSettings() {
  loading.value = true
  error.value = ''
  saved.value = ''

  try {
    const { data } = await api.get('/saas/checkout-settings')
    settings.value = data.data
    selectedProvider.value = data.data.payment_provider
    boletoEnabled.value = Boolean(data.data.boleto_enabled)
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar as configurações do checkout.'
  } finally {
    loading.value = false
  }
}

async function saveSettings() {
  saving.value = true
  error.value = ''
  saved.value = ''

  try {
    const { data } = await api.patch('/saas/checkout-settings', {
      payment_provider: selectedProvider.value,
      boleto_enabled: boletoEnabled.value,
    })
    settings.value = data.data
    selectedProvider.value = data.data.payment_provider
    boletoEnabled.value = Boolean(data.data.boleto_enabled)
    saved.value = 'Configurações do checkout atualizadas.'
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível salvar a operadora do checkout.'
  } finally {
    saving.value = false
  }
}

function methodLabel(provider: CheckoutProviderOption) {
  return provider.payment_methods
    .map((method) => method === 'credit_card' ? 'cartão' : method)
    .join(' + ')
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">SaaS</span>
        <h1>Checkout</h1>
        <p>Escolha a operadora usada no checkout transparente público.</p>
      </div>
      <RouterLink class="btn btn-secondary" to="/saas">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Voltar
      </RouterLink>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>
    <p v-if="saved" class="form-success">{{ saved }}</p>

    <div class="saas-grid">
      <form class="panel-main admin-form" @submit.prevent="saveSettings">
        <div class="subsection-heading">
          <h2>Operadora ativa</h2>
          <span>{{ activeProvider?.label || 'Selecionar' }}</span>
        </div>

        <div class="provider-choice-list">
          <label
            v-for="provider in settings.providers"
            :key="provider.key"
            class="provider-choice"
            :class="{ active: selectedProvider === provider.key }"
          >
            <input v-model="selectedProvider" type="radio" name="payment_provider" :value="provider.key" :disabled="!canEdit || loading || saving" />
            <span>
              <strong>{{ provider.label }}</strong>
              <small>{{ methodLabel(provider) || 'meios indisponíveis' }}</small>
            </span>
            <em :class="{ ok: provider.configured, warning: !provider.configured }">
              {{ provider.configured ? 'Credenciais prontas' : 'Aguardando chaves' }}
            </em>
          </label>
        </div>

        <label class="settings-check">
          <input v-model="boletoEnabled" type="checkbox" :disabled="!canEdit || loading || saving" />
          <span>
            <strong>Habilitar boleto</strong>
            <small>Mostra boleto no checkout público quando Mercado Pago estiver ativo.</small>
          </span>
        </label>

        <div class="action-row compact">
          <button class="btn btn-primary" type="submit" :disabled="!canEdit || saving || loading">
            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
            {{ saving ? 'Salvando...' : 'Salvar operadora' }}
          </button>
        </div>
      </form>

      <section class="panel-main admin-form">
        <div class="subsection-heading">
          <h2>Status</h2>
          <span>{{ settings.active_provider_configured ? 'Pronto' : 'Pendente' }}</span>
        </div>

        <div class="check-list stacked">
          <span :class="settings.payment_provider === 'mercado_pago' ? 'passed' : 'pending'">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            Mercado Pago fica ativo para produção imediata.
          </span>
          <span class="pending">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            Pagar.me permanece disponível para finalizar quando as chaves e regras pendentes chegarem.
          </span>
          <span :class="settings.boleto_enabled ? 'passed' : 'pending'">
            <i class="fa-solid fa-barcode" aria-hidden="true"></i>
            Boleto fica oculto por padrão e aparece somente quando habilitado no SaaS.
          </span>
          <span :class="settings.active_provider_configured ? 'passed' : 'warning'">
            <i class="fa-solid fa-key" aria-hidden="true"></i>
            As chaves ficam no ambiente seguro do servidor, não neste painel.
          </span>
        </div>
      </section>
    </div>
  </section>
</template>
