<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../services/api'
import type { CompanyRow, IntegrationChangeRequest, MerchantRow, Summary, TransactionalEmailSend } from '../services/saasTypes'

const summary = ref<Summary>({})
const merchants = ref<MerchantRow[]>([])
const companies = ref<CompanyRow[]>([])
const emailSends = ref<TransactionalEmailSend[]>([])
const integrationChangeRequests = ref<IntegrationChangeRequest[]>([])
const loading = ref(false)
const error = ref('')

onMounted(() => {
  loadOverview()
})

async function loadOverview() {
  loading.value = true
  error.value = ''

  try {
    const [overviewResponse, merchantsResponse, companiesResponse, emailSendsResponse, changeRequestsResponse] = await Promise.all([
      api.get('/saas/overview'),
      api.get('/saas/merchants'),
      api.get('/saas/companies'),
      api.get('/saas/transactional-email-sends'),
      api.get('/saas/integration-change-requests', { params: { status: 'pending' } }),
    ])

    summary.value = overviewResponse.data.data.summary
    merchants.value = merchantsResponse.data.data
    companies.value = companiesResponse.data.data
    emailSends.value = emailSendsResponse.data.data
    integrationChangeRequests.value = changeRequestsResponse.data.data
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar o painel SaaS.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">SaaS</span>
        <h1>Visão geral</h1>
        <p>Resumo operacional do Provador Virtual e atalhos para as áreas administrativas.</p>
      </div>
      <button class="btn btn-secondary" type="button" :disabled="loading" @click="loadOverview">
        <i class="fa-solid fa-rotate" aria-hidden="true"></i>
        Atualizar
      </button>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>

    <div class="metric-grid">
      <article class="metric-card">
        <i class="fa-solid fa-store" aria-hidden="true"></i>
        <strong>{{ summary.merchants || 0 }}</strong>
        <span>{{ summary.companies || 0 }} empresas cadastradas</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-code" aria-hidden="true"></i>
        <strong>{{ summary.active_widgets || 0 }}</strong>
        <span>Provadores ativos</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
        <strong>{{ summary.recommendations_7d || 0 }}</strong>
        <span>Recomendações em 7 dias</span>
      </article>
      <article class="metric-card">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <strong>{{ (summary.failed_imports_7d || 0) + (summary.failed_integrations_7d || 0) }}</strong>
        <span>Falhas recentes</span>
      </article>
    </div>

    <div class="quick-grid">
      <RouterLink to="/saas/empresas" class="quick-card">
        <i class="fa-solid fa-building" aria-hidden="true"></i>
        <strong>Empresas</strong>
        <span>Listagem, novo cadastro, edição e ativação.</span>
      </RouterLink>
      <RouterLink to="/saas/usuarios" class="quick-card">
        <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
        <strong>Usuários SaaS</strong>
        <span>Usuários internos, suporte e acessos vinculados.</span>
      </RouterLink>
      <RouterLink to="/saas/emails" class="quick-card">
        <i class="fa-solid fa-envelope-open-text" aria-hidden="true"></i>
        <strong>E-mails</strong>
        <span>Credenciais SMTP, templates e histórico de envios.</span>
      </RouterLink>
      <RouterLink to="/saas/checkout" class="quick-card">
        <i class="fa-solid fa-credit-card" aria-hidden="true"></i>
        <strong>Checkout</strong>
        <span>Operadora ativa, meios de pagamento e status das credenciais.</span>
      </RouterLink>
      <RouterLink to="/saas/pedidos" class="quick-card">
        <i class="fa-solid fa-receipt" aria-hidden="true"></i>
        <strong>Pedidos</strong>
        <span>Contratações, tentativas recusadas e detalhes da operadora.</span>
      </RouterLink>
    </div>

    <section v-if="integrationChangeRequests.length" class="panel-main subsection">
      <div class="subsection-heading">
        <h2>Solicitações de troca</h2>
        <span>{{ integrationChangeRequests.length }} pendente{{ integrationChangeRequests.length === 1 ? '' : 's' }}</span>
      </div>
      <div class="change-request-list">
        <article v-for="request in integrationChangeRequests.slice(0, 6)" :key="request.id">
          <i class="fa-solid fa-right-left" aria-hidden="true"></i>
          <span>
            <strong>{{ request.company.name }}</strong>
            <small>
              {{ request.from_platform_label }} para {{ request.to_platform_label }} ·
              solicitado por {{ request.user.name || request.user.email || 'usuário do portal' }}
            </small>
          </span>
          <RouterLink class="btn btn-secondary btn-compact" :to="`/saas/empresas/${request.company.id}/editar`">
            Abrir empresa
          </RouterLink>
        </article>
      </div>
    </section>

    <section class="panel-main subsection">
      <div class="subsection-heading">
        <h2>Empresas recentes</h2>
        <RouterLink class="btn btn-secondary" to="/saas/empresas">
          <i class="fa-solid fa-table-list" aria-hidden="true"></i>
          Ver listagem
        </RouterLink>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Código</th>
              <th>Empresa</th>
              <th>Lojista</th>
              <th>Plataforma</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!companies.length">
              <td colspan="5">Sem empresas.</td>
            </tr>
            <tr v-for="company in companies.slice(0, 8)" :key="company.id">
              <td><strong>{{ company.access_code }}</strong></td>
              <td>
                <strong>{{ company.name }}</strong>
                <small>{{ company.document || company.domain || 'sem documento' }}</small>
              </td>
              <td>{{ company.merchant.name }}</td>
              <td>
                <strong>{{ company.integration_state?.platform_label || company.platform }}</strong>
                <small>
                  {{ company.integration_state?.technical_label || 'Sem conexão' }}
                  ·
                  {{ company.integration_state?.commercial_label || company.merchant.billing_status }}
                </small>
              </td>
              <td>
                <span class="status-pill" :class="{ ok: company.status === 'active', warning: company.status !== 'active' }">
                  {{ company.status === 'active' ? 'Ativa' : company.status }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="panel-main subsection">
      <div class="subsection-heading">
        <h2>Lojistas recentes</h2>
        <span>{{ merchants.length }} carregados</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Lojista</th>
              <th>Status</th>
              <th>Produtos</th>
              <th>Tabelas</th>
              <th>Integrações</th>
              <th>7 dias</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!merchants.length">
              <td colspan="6">Sem lojistas.</td>
            </tr>
            <tr v-for="merchant in merchants.slice(0, 8)" :key="merchant.id">
              <td>
                <strong>{{ merchant.name }}</strong>
                <small>{{ merchant.slug }}</small>
              </td>
              <td>{{ merchant.billing_status }}</td>
              <td>{{ merchant.products_count }}</td>
              <td>{{ merchant.measurement_tables_count }}</td>
              <td>{{ merchant.platform_connections_count }}</td>
              <td>{{ merchant.recommendations_7d }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="panel-main subsection">
      <div class="subsection-heading">
        <h2>Envios de e-mail recentes</h2>
        <RouterLink class="btn btn-secondary" to="/saas/emails">
          <i class="fa-solid fa-envelope" aria-hidden="true"></i>
          Ver e-mails
        </RouterLink>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Template</th>
              <th>Empresa</th>
              <th>Destinatário</th>
              <th>Status</th>
              <th>Data</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!emailSends.length">
              <td colspan="5">Nenhum envio registrado.</td>
            </tr>
            <tr v-for="send in emailSends.slice(0, 8)" :key="send.id">
              <td>
                <strong>{{ send.template_name || send.code }}</strong>
                <small>{{ send.subject || 'sem assunto' }}</small>
              </td>
              <td>{{ send.company_name || '-' }}</td>
              <td>
                <strong>{{ send.recipient_name || '-' }}</strong>
                <small>{{ send.recipient_email || '-' }}</small>
              </td>
              <td>
                <span class="status-pill" :class="{ ok: send.status === 'sent', warning: send.status === 'skipped' }">
                  {{ send.status }}
                </span>
              </td>
              <td>{{ send.sent_at || send.created_at || '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </section>
</template>
