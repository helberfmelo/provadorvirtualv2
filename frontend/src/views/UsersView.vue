<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../services/api'
import type { PortalUser } from '../services/merchantTypes'
import { showFeedback } from '../services/saveFeedback'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const users = ref<PortalUser[]>([])
const loading = ref(false)
const error = ref('')

onMounted(() => {
  loadUsers()
})

async function loadUsers() {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get('/merchant/users')
    users.value = data.data
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar os usuários.'
  } finally {
    loading.value = false
  }
}

async function toggleUser(user: PortalUser) {
  error.value = ''
  const nextStatus = user.access?.status === 'active' ? 'inactive' : 'active'

  try {
    await api.patch(`/merchant/users/${user.id}`, {
      merchant_user_status: nextStatus,
    })
    showFeedback({
      status: 'success',
      title: nextStatus === 'active' ? 'Usuário ativado' : 'Usuário desativado',
      message: nextStatus === 'active'
        ? 'O usuário voltou a acessar o portal da empresa.'
        : 'O usuário foi pausado no portal da empresa.',
    })
    await loadUsers()
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível alterar o status.'
  }
}

async function resendInvite(user: PortalUser) {
  error.value = ''

  try {
    await api.patch(`/merchant/users/${user.id}`, {
      send_invite: true,
    })
    showFeedback({
      status: 'success',
      title: 'Convite atualizado',
      message: 'O convite deste acesso voltou para pendente até o primeiro login.',
    })
    await loadUsers()
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível atualizar o convite.'
  }
}

function invitationLabel(user: PortalUser) {
  switch (user.access?.invitation.status) {
    case 'pending':
      return 'Convite pendente'
    case 'not_sent':
      return 'Convite não enviado'
    default:
      return 'Aceito'
  }
}

function invitationDate(user: PortalUser) {
  const invitation = user.access?.invitation

  if (!invitation) {
    return ''
  }

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
        <span class="eyebrow">Usuários</span>
        <h1>Acessos da empresa</h1>
        <p>Convide a equipe e libere apenas os módulos necessários para cada pessoa.</p>
      </div>
      <div class="action-row compact">
        <button class="btn btn-secondary" type="button" :disabled="loading" @click="loadUsers">
          <i class="fa-solid fa-rotate" aria-hidden="true"></i>
          Atualizar
        </button>
        <RouterLink v-if="auth.canEdit('users')" class="btn btn-primary" to="/app/usuarios/novo">
          <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
          Novo usuário
        </RouterLink>
      </div>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>

    <section class="panel-main subsection">
      <div class="subsection-heading">
        <h2>Usuários cadastrados</h2>
        <span>{{ loading ? 'carregando' : `${users.length} usuários` }}</span>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Usuário</th>
              <th>Perfil</th>
              <th>Status</th>
              <th>Convite</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!users.length">
              <td colspan="5">Nenhum usuário cadastrado.</td>
            </tr>
            <tr v-for="user in users" :key="user.id">
              <td>
                <strong>{{ user.name }}</strong>
                <small>{{ user.email }} {{ user.cpf ? `- CPF ${user.cpf}` : '' }}</small>
              </td>
              <td>{{ user.access?.is_owner ? 'Dono' : user.access?.role }}</td>
              <td>
                <span class="status-pill" :class="{ ok: user.access?.status === 'active', warning: user.access?.status !== 'active' }">
                  {{ user.access?.status === 'active' ? 'Ativo' : 'Inativo' }}
                </span>
              </td>
              <td>
                <span class="status-pill" :class="{ ok: user.access?.invitation.status === 'accepted', warning: user.access?.invitation.status !== 'accepted' }">
                  {{ invitationLabel(user) }}
                </span>
                <small>{{ invitationDate(user) }}</small>
              </td>
              <td class="row-actions">
                <RouterLink
                  v-if="auth.canEdit('users')"
                  class="icon-link"
                  :to="`/app/usuarios/${user.id}/editar`"
                  title="Editar"
                  aria-label="Editar usuário"
                >
                  <i class="fa-solid fa-pen" aria-hidden="true"></i>
                </RouterLink>
                <button
                  v-if="auth.canEdit('users')"
                  type="button"
                  :title="user.access?.status === 'active' ? 'Desativar' : 'Ativar'"
                  @click="toggleUser(user)"
                >
                  <i :class="user.access?.status === 'active' ? 'fa-solid fa-toggle-on' : 'fa-solid fa-toggle-off'" aria-hidden="true"></i>
                </button>
                <button
                  v-if="auth.canEdit('users') && user.access?.invitation.status !== 'accepted'"
                  type="button"
                  title="Reenviar convite"
                  @click="resendInvite(user)"
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
