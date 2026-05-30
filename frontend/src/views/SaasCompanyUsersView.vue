<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../services/api'
import type { SaasUser } from '../services/saasTypes'
import { showFeedback } from '../services/saveFeedback'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
type CompanyUserRow = {
  key: string
  user: SaasUser
  merchant: SaasUser['merchants'][number]
}

const users = ref<SaasUser[]>([])
const loading = ref(false)
const error = ref('')

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
  const nextStatus = row.merchant.access.status === 'active' ? 'inactive' : 'active'

  try {
    await api.patch(`/saas/company-users/${row.user.id}`, {
      merchant_company_id: row.merchant.access.merchant_company_id,
      merchant_user_status: nextStatus,
    })
    showFeedback({
      status: 'success',
      title: nextStatus === 'active' ? 'Acesso ativado' : 'Acesso desativado',
      message: nextStatus === 'active'
        ? 'O usuário voltou a acessar esta empresa.'
        : 'O acesso do usuário foi pausado nesta empresa.',
    })
    await loadUsers()
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível alterar o acesso.'
  }
}

async function resendInvite(row: CompanyUserRow) {
  error.value = ''

  try {
    await api.patch(`/saas/company-users/${row.user.id}`, {
      merchant_company_id: row.merchant.access.merchant_company_id,
      send_invite: true,
    })
    showFeedback({
      status: 'success',
      title: 'Convite atualizado',
      message: 'O convite voltou para pendente até o primeiro acesso deste usuário.',
    })
    await loadUsers()
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível atualizar o convite.'
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

function invitationLabel(row: CompanyUserRow) {
  switch (row.merchant.access.invitation.status) {
    case 'pending':
      return 'Convite pendente'
    case 'not_sent':
      return 'Convite não enviado'
    default:
      return 'Aceito'
  }
}

function invitationDate(row: CompanyUserRow) {
  const invitation = row.merchant.access.invitation

  if (invitation.status === 'accepted' && invitation.accepted_at) {
    return `Primeiro acesso em ${new Date(invitation.accepted_at).toLocaleString('pt-BR')}`
  }

  if (invitation.invited_at) {
    return `Último convite em ${new Date(invitation.invited_at).toLocaleString('pt-BR')}`
  }

  return 'Aguardando envio do convite'
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">SaaS</span>
        <h1>Usuários das empresas</h1>
        <p>Gerencie quem acessa cada empresa cliente e quais módulos ficam disponíveis.</p>
      </div>
      <div class="action-row compact">
        <button class="btn btn-secondary" type="button" :disabled="loading" @click="loadUsers">
          <i class="fa-solid fa-rotate" aria-hidden="true"></i>
          Atualizar
        </button>
        <RouterLink v-if="auth.canSaasEdit('saas_company_users')" class="btn btn-primary" to="/saas/usuarios-empresas/novo">
          <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
          Novo usuário
        </RouterLink>
      </div>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>

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
              <th>Convite</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="8">Nenhum usuário de empresa cadastrado.</td>
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
              <td>
                <span class="status-pill" :class="{ ok: row.merchant.access.invitation.status === 'accepted', warning: row.merchant.access.invitation.status !== 'accepted' }">
                  {{ invitationLabel(row) }}
                </span>
                <small>{{ invitationDate(row) }}</small>
              </td>
              <td class="row-actions">
                <RouterLink
                  v-if="auth.canSaasEdit('saas_company_users')"
                  class="icon-link"
                  :to="{ path: `/saas/usuarios-empresas/${row.user.id}/editar`, query: { company: row.merchant.access.merchant_company_id || '' } }"
                  title="Editar"
                  aria-label="Editar usuário da empresa"
                >
                  <i class="fa-solid fa-pen" aria-hidden="true"></i>
                </RouterLink>
                <button
                  v-if="auth.canSaasEdit('saas_company_users')"
                  type="button"
                  :title="row.merchant.access.status === 'active' ? 'Desativar' : 'Ativar'"
                  @click="toggleAccess(row)"
                >
                  <i :class="row.merchant.access.status === 'active' ? 'fa-solid fa-toggle-on' : 'fa-solid fa-toggle-off'" aria-hidden="true"></i>
                </button>
                <button
                  v-if="auth.canSaasEdit('saas_company_users') && row.merchant.access.invitation.status !== 'accepted'"
                  type="button"
                  title="Reenviar convite"
                  @click="resendInvite(row)"
                >
                  <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </section>
</template>
