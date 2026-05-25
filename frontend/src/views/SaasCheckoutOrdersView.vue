<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../services/api'
import type { CheckoutOrderRow } from '../services/saasTypes'

const orders = ref<CheckoutOrderRow[]>([])
const loading = ref(false)
const error = ref('')

onMounted(() => {
  loadOrders()
})

async function loadOrders() {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get('/saas/checkout-orders')
    orders.value = data.data
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar os pedidos.'
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

function dateTime(value: string | null) {
  return value ? new Date(value).toLocaleString('pt-BR') : '-'
}

function paymentMethodLabel(method: string) {
  return {
    credit_card: 'Cartão',
    pix: 'Pix',
    boleto: 'Boleto',
  }[method] || method
}

function statusClass(order: CheckoutOrderRow) {
  return {
    ok: order.status === 'paid',
    warning: ['pending', 'checkout_created'].includes(order.status),
  }
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">SaaS</span>
        <h1>Pedidos</h1>
        <p>Pedidos do checkout público, incluindo tentativas recusadas pela operadora.</p>
      </div>
      <button class="btn btn-secondary" type="button" :disabled="loading" @click="loadOrders">
        <i class="fa-solid fa-rotate" aria-hidden="true"></i>
        Atualizar
      </button>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>

    <section class="panel-main subsection">
      <div class="subsection-heading">
        <h2>Pedidos registrados</h2>
        <span>{{ loading ? 'carregando' : `${orders.length} pedidos` }}</span>
      </div>

      <div class="table-wrap checkout-orders-table">
        <table>
          <thead>
            <tr>
              <th>Pedido</th>
              <th>Cliente</th>
              <th>Plano</th>
              <th>Pagamento</th>
              <th>Valor</th>
              <th>Status</th>
              <th>Motivo</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!orders.length">
              <td colspan="8">Nenhum pedido registrado.</td>
            </tr>
            <tr v-for="order in orders" :key="order.id">
              <td>
                <strong>#{{ order.id }}</strong>
                <small>{{ dateTime(order.created_at) }}</small>
              </td>
              <td>
                <strong>{{ order.lead_name }}</strong>
                <small>{{ order.lead_email }}</small>
              </td>
              <td>
                <strong>{{ order.plan_name }}</strong>
                <small>{{ order.company_document || order.lead_company }}</small>
              </td>
              <td>
                <strong>{{ paymentMethodLabel(order.payment_method) }}</strong>
                <small>{{ order.provider_label }}</small>
              </td>
              <td>{{ price(order.amount_cents) }}</td>
              <td>
                <span class="status-pill" :class="statusClass(order)">
                  {{ order.status_label }}
                </span>
              </td>
              <td class="checkout-order-reason">{{ order.failure_reason || '-' }}</td>
              <td class="row-actions">
                <RouterLink class="icon-link" :to="`/saas/pedidos/${order.id}`" title="Visualizar pedido" aria-label="Visualizar pedido">
                  <i class="fa-solid fa-eye" aria-hidden="true"></i>
                </RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </section>
</template>
