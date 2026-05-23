<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../services/api'
import type { SaasUser } from '../services/saasTypes'

type CompanyUserRow = {
  key: string
  user: SaasUser
  merchant: SaasUser['merchants'][number]
}

const users = ref<SaasUser[]>([])
const loading = ref(false)
const error = ref('')
const notice = ref('')

const rows = computed<CompanyUserRow[]>(() => users.value.flatMap((user) => (
  user.merchants.map((merchant) => ({
    key: `${user.id}-${merchant.id}-${merchant.access.merchant_company_id || 'merchant'}`,
    user,
    merchant,
  }))
)))

onMounted(() => {
  loadUsers()
})

async function loadUsers() {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get('/saas/company-users')
    users.value = data.data
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar os usuários das empresas.'
  } finally {
    loading.value = false
  }
}

async function toggleAccess(row: CompanyUserRow) {
  error.value = ''
  notice.value = ''
  const nextStatus = row.merchant.access.status === 'active' ? 'inactive' : 'active'

  try {
    await api.patch(`/saas/company-users/${row.user.id}`, {
      merchant_company_id: row.merchant.access.merchant_company_id,
      merchant_user_status: nextStatus,
    })
    notice.value = nextStatus === 'active' ? 'Acesso ativado.' : 'Acesso desativado.'
    await loadUsers()
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível alterar o acesso.'
  }
}

function companyLabel(row: CompanyUserRow) {
  const company = row.merchant.access.company

  if (!company) {
    return row.merchant.name
  }

  return `${company.access_code || company.id} - ${company.name}`
}

function companyDocument(row: CompanyUserRow) {
  return row.merchant.access.company?.document || row.merchant.slug
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">SaaS</span>
        <h1>Usuários das empresas</h1>
        <p>Listagem de usuários dos clientes. Cadastro e edição ficam em telas próprias.</p>
      </div>
      <div class="action-row compact">
        <button class="btn btn-secondary" type="button" :disabled="loading" @click="loadUsers">
          <i class="fa-solid fa-rotate" aria-hidden="true"></i>
          Atualizar
        </button>
        <RouterLink class="btn btn-primary" to="/saas/usuarios-empresas/novo">
          <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
          Novo usuário
        </RouterLink>
      </div>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>
    <p v-if="notice" class="success-message">{{ notice }}</p>

    <section class="panel-main subsection">
      <div class="subsection-heading">
        <h2>Acessos de empresas clientes</h2>
        <span>{{ loading ? 'carregando' : `${rows.length} acessos` }}</span>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Usuário</th>
              <th>Empresa</th>
              <th>Lojista</th>
              <th>Perfil</th>
              <th>Status usuário</th>
              <th>Status acesso</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="7">Nenhum usuário de empresa cadastrado.</td>
            </tr>
            <tr v-for="row in rows" :key="row.key">
              <td>
                <strong>{{ row.user.name }}</strong>
                <small>{{ row.user.email }} {{ row.user.cpf ? `- CPF ${row.user.cpf}` : '' }}</small>
              </td>
              <td>
                <strong>{{ companyLabel(row) }}</strong>
                <small>{{ companyDocument(row) }}</small>
              </td>
              <td>{{ row.merchant.name }}</td>
              <td>{{ row.merchant.access.is_owner ? 'Dono' : row.merchant.access.role }}</td>
              <td>
                <span class="status-pill" :class="{ ok: row.user.status === 'active', warning: row.user.status !== 'active' }">
                  {{ row.user.status === 'active' ? 'Ativo' : 'Inativo' }}
                </span>
              </td>
              <td>
                <span class="status-pill" :class="{ ok: row.merchant.access.status === 'active', warning: row.merchant.access.status !== 'active' }">
                  {{ row.merchant.access.status === 'active' ? 'Ativo' : 'Inativo' }}
                </span>
              </td>
              <td class="row-actions">
                <RouterLink
                  class="icon-link"
                  :to="{ path: `/saas/usuarios-empresas/${row.user.id}/editar`, query: { company: row.merchant.access.merchant_company_id || '' } }"
                  title="Editar"
                  aria-label="Editar usuário da empresa"
                >
                  <i class="fa-solid fa-pen" aria-hidden="true"></i>
                </RouterLink>
                <button type="button" :title="row.merchant.access.status === 'active' ? 'Desativar' : 'Ativar'" @click="toggleAccess(row)">
                  <i :class="row.merchant.access.status === 'active' ? 'fa-solid fa-toggle-on' : 'fa-solid fa-toggle-off'" aria-hidden="true"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </section>
</template>
