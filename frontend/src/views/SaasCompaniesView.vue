<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../services/api'
import type { CompanyRow } from '../services/saasTypes'

const companies = ref<CompanyRow[]>([])
const loading = ref(false)
const error = ref('')
const notice = ref('')

onMounted(() => {
  loadCompanies()
})

async function loadCompanies() {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get('/saas/companies')
    companies.value = data.data
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Nao foi possivel carregar as empresas.'
  } finally {
    loading.value = false
  }
}

function companyPayload(company: CompanyRow, status: string) {
  return {
    name: company.name,
    legal_name: company.legal_name,
    document: company.document,
    zip_code: company.zip_code,
    street: company.street,
    number: company.number,
    complement: company.complement,
    district: company.district,
    city: company.city,
    state: company.state,
    domain: company.domain,
    platform: company.platform,
    external_store_id: company.external_store_id,
    status,
  }
}

async function toggleCompany(company: CompanyRow) {
  notice.value = ''
  error.value = ''
  const nextStatus = company.status === 'active' ? 'inactive' : 'active'

  try {
    await api.patch(`/saas/companies/${company.id}`, companyPayload(company, nextStatus))
    notice.value = nextStatus === 'active' ? 'Empresa ativada.' : 'Empresa desativada.'
    await loadCompanies()
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Nao foi possivel alterar o status da empresa.'
  }
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">SaaS</span>
        <h1>Empresas</h1>
        <p>Listagem operacional de empresas clientes. Cadastros e edicoes abrem em tela propria.</p>
      </div>
      <div class="action-row compact">
        <button class="btn btn-secondary" type="button" :disabled="loading" @click="loadCompanies">
          <i class="fa-solid fa-rotate" aria-hidden="true"></i>
          Atualizar
        </button>
        <RouterLink class="btn btn-primary" to="/saas/empresas/nova">
          <i class="fa-solid fa-plus" aria-hidden="true"></i>
          Nova empresa
        </RouterLink>
      </div>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>
    <p v-if="notice" class="success-message">{{ notice }}</p>

    <section class="panel-main subsection">
      <div class="subsection-heading">
        <h2>Empresas cadastradas</h2>
        <span>{{ loading ? 'carregando' : `${companies.length} empresas` }}</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Codigo</th>
              <th>Empresa</th>
              <th>Lojista</th>
              <th>Plataforma</th>
              <th>Endereco</th>
              <th>Status</th>
              <th>Acoes</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!companies.length">
              <td colspan="7">Sem empresas.</td>
            </tr>
            <tr v-for="company in companies" :key="company.id">
              <td><strong>{{ company.access_code }}</strong></td>
              <td>
                <strong>{{ company.name }}</strong>
                <small>{{ company.document || company.domain || 'sem documento' }}</small>
              </td>
              <td>
                <strong>{{ company.merchant.name }}</strong>
                <small>{{ company.merchant.slug }}</small>
              </td>
              <td>{{ company.platform }}</td>
              <td>{{ [company.city, company.state].filter(Boolean).join('/') || '-' }}</td>
              <td>
                <span class="status-pill" :class="{ ok: company.status === 'active', warning: company.status !== 'active' }">
                  {{ company.status === 'active' ? 'Ativa' : company.status }}
                </span>
              </td>
              <td class="row-actions">
                <RouterLink class="icon-link" :to="`/saas/empresas/${company.id}/editar`" title="Editar" aria-label="Editar empresa">
                  <i class="fa-solid fa-pen" aria-hidden="true"></i>
                </RouterLink>
                <button type="button" :title="company.status === 'active' ? 'Desativar' : 'Ativar'" @click="toggleCompany(company)">
                  <i :class="company.status === 'active' ? 'fa-solid fa-toggle-on' : 'fa-solid fa-toggle-off'" aria-hidden="true"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </section>
</template>
