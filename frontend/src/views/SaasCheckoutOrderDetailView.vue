<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api } from '../services/api'
import type { CheckoutOrderDetail } from '../services/saasTypes'

const route = useRoute()
const order = ref<CheckoutOrderDetail | null>(null)
const loading = ref(false)
const error = ref('')

const companyAddress = computed(() => {
  const company = order.value?.company
  if (!company) {
    return '-'
  }

  return [
    [company.street, company.number].filter(Boolean).join(', '),
    company.complement,
    company.district,
    [company.city, company.state].filter(Boolean).join('/'),
    company.zip_code,
  ].filter(Boolean).join(' - ') || '-'
})

onMounted(() => {
  loadOrder()
})

async function loadOrder() {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get(`/saas/checkout-orders/${route.params.id}`)
    order.value = data.data
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar o pedido.'
  } finally {
    loading.value = false
  }
}

function price(cents: number) {
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  }).format((cents || 0) / 100)
}

function dateTime(value: string | null | undefined) {
  return value ? new Date(value).toLocaleString('pt-BR') : '-'
}

function paymentMethodLabel(method: string | undefined) {
  return {
    credit_card: 'Cartão',
    pix: 'Pix',
    boleto: 'Boleto',
  }[method || ''] || method || '-'
}

function pretty(value: unknown) {
  return JSON.stringify(value || {}, null, 2)
}

function statusClass(status: string | undefined) {
  return {
    ok: status === 'paid',
    warning: ['pending', 'checkout_created'].includes(status || ''),
  }
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">SaaS</span>
        <h1>Pedido {{ order ? `#${order.id}` : '' }}</h1>
        <p v-if="order">{{ order.reference }}</p>
      </div>
      <RouterLink class="btn btn-secondary" to="/saas/pedidos">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Voltar
      </RouterLink>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>
    <div v-if="loading" class="empty-state">Carregando pedido...</div>

    <template v-else-if="order">
      <div class="order-detail-grid">
        <section class="panel-main admin-form">
          <div class="subsection-heading">
            <h2>Resumo</h2>
            <span class="status-pill" :class="statusClass(order.status)">{{ order.status_label }}</span>
          </div>
          <dl class="detail-list">
            <dt>Plano</dt>
            <dd>{{ order.plan_name }}</dd>
            <dt>Valor</dt>
            <dd>{{ price(order.amount_cents) }}</dd>
            <dt>Pagamento</dt>
            <dd>{{ paymentMethodLabel(order.payment_method) }} · {{ order.provider_label }}</dd>
            <dt>Criado em</dt>
            <dd>{{ dateTime(order.created_at) }}</dd>
            <dt>Pago em</dt>
            <dd>{{ dateTime(order.paid_at) }}</dd>
            <dt>Motivo da falha</dt>
            <dd>{{ order.failure_reason || '-' }}</dd>
          </dl>
        </section>

        <section class="panel-main admin-form">
          <div class="subsection-heading">
            <h2>Cliente</h2>
            <span>{{ order.lead.email }}</span>
          </div>
          <dl class="detail-list">
            <dt>Nome</dt>
            <dd>{{ order.lead.name }}</dd>
            <dt>E-mail</dt>
            <dd>{{ order.lead.email }}</dd>
            <dt>Telefone</dt>
            <dd>{{ order.lead.phone || '-' }}</dd>
            <dt>Usuário</dt>
            <dd>{{ order.user.name || '-' }} · {{ order.user.cpf || '-' }}</dd>
          </dl>
        </section>

        <section class="panel-main admin-form">
          <div class="subsection-heading">
            <h2>Empresa</h2>
            <span>{{ order.company?.access_code || '-' }}</span>
          </div>
          <dl class="detail-list">
            <dt>Nome</dt>
            <dd>{{ order.company?.name || order.lead.company }}</dd>
            <dt>Razão social</dt>
            <dd>{{ order.company?.legal_name || '-' }}</dd>
            <dt>CNPJ</dt>
            <dd>{{ order.company?.document || order.company_document || '-' }}</dd>
            <dt>Domínio</dt>
            <dd>{{ order.company?.domain || '-' }}</dd>
            <dt>Endereço</dt>
            <dd>{{ companyAddress }}</dd>
          </dl>
        </section>

        <section class="panel-main admin-form">
          <div class="subsection-heading">
            <h2>Operadora</h2>
            <span>{{ order.provider.label }}</span>
          </div>
          <dl class="detail-list">
            <dt>Código interno</dt>
            <dd>{{ order.provider.order_code || '-' }}</dd>
            <dt>ID pedido</dt>
            <dd>{{ order.provider.order_id || '-' }}</dd>
            <dt>ID cobrança</dt>
            <dd>{{ order.provider.charge_id || '-' }}</dd>
            <dt>Última sincronização</dt>
            <dd>{{ dateTime(order.provider.last_sync_at) }}</dd>
          </dl>
        </section>

        <section class="panel-main admin-form">
          <div class="subsection-heading">
            <h2>Aceite</h2>
            <span>{{ dateTime(order.acceptance?.accepted_at) }}</span>
          </div>
          <dl class="detail-list">
            <dt>Termos</dt>
            <dd>{{ order.acceptance?.terms_version || '-' }}</dd>
            <dt>Privacidade</dt>
            <dd>{{ order.acceptance?.privacy_version || '-' }}</dd>
            <dt>IP</dt>
            <dd>{{ order.acceptance?.ip_address || '-' }}</dd>
            <dt>User agent</dt>
            <dd>{{ order.acceptance?.user_agent || '-' }}</dd>
          </dl>
        </section>

        <section class="panel-main admin-form">
          <div class="subsection-heading">
            <h2>Assinatura</h2>
            <span>{{ order.billing_subscription ? 'registrada' : 'sem recorrência' }}</span>
          </div>
          <pre class="json-box">{{ pretty(order.billing_subscription) }}</pre>
        </section>
      </div>

      <section class="panel-main subsection">
        <div class="subsection-heading">
          <h2>Dados técnicos</h2>
          <span>checkout</span>
        </div>
        <div class="order-json-grid">
          <div>
            <strong>Snapshot do pagamento</strong>
            <pre class="json-box">{{ pretty(order.payment_snapshot) }}</pre>
          </div>
          <div>
            <strong>Falha</strong>
            <pre class="json-box">{{ pretty(order.failure) }}</pre>
          </div>
          <div>
            <strong>Payload da operadora</strong>
            <pre class="json-box">{{ pretty(order.provider_payload) }}</pre>
          </div>
          <div>
            <strong>Metadados internos</strong>
            <pre class="json-box">{{ pretty(order.metadata) }}</pre>
          </div>
        </div>
      </section>
    </template>
  </section>
</template>
