<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../services/api'
import type { SaasUser } from '../services/saasTypes'
import { showFeedback } from '../services/saveFeedback'

const users = ref<SaasUser[]>([])
const loading = ref(false)
const error = ref('')

onMounted(() => {
  loadUsers()
})

async function loadUsers() {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get('/saas/users')
    users.value = data.data
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar os usuários SaaS.'
  } finally {
    loading.value = false
  }
}

async function toggleUser(user: SaasUser) {
  error.value = ''
  const nextStatus = user.status === 'active' ? 'inactive' : 'active'

  try {
    await api.patch(`/saas/users/${user.id}`, {
      status: nextStatus,
    })
    showFeedback({
      status: 'success',
      title: nextStatus === 'active' ? 'Usuário ativado' : 'Usuário desativado',
      message: nextStatus === 'active'
        ? 'O usuário interno voltou a acessar o SaaS.'
        : 'O usuário interno foi pausado no SaaS.',
    })
    await loadUsers()
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível alterar o status.'
  }
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">SaaS</span>
        <h1>Usuários SaaS</h1>
        <p>Controle a equipe interna, papéis administrativos e permissões de suporte.</p>
      </div>
      <div class="action-row compact">
        <button class="btn btn-secondary" type="button" :disabled="loading" @click="loadUsers">
          <i class="fa-solid fa-rotate" aria-hidden="true"></i>
          Atualizar
        </button>
        <RouterLink class="btn btn-primary" to="/saas/usuarios/novo">
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
              <th>Tipo</th>
              <th>Empresas</th>
              <th>Status</th>
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
              <td>{{ user.role }}</td>
              <td>
                <small v-if="!user.merchants.length">Sem empresa vinculada</small>
                <small v-for="merchant in user.merchants" v-else :key="merchant.id">
                  {{ merchant.name }} - {{ merchant.access.status }}
                </small>
              </td>
              <td>
                <span class="status-pill" :class="{ ok: user.status === 'active', warning: user.status !== 'active' }">
                  {{ user.status === 'active' ? 'Ativo' : 'Inativo' }}
                </span>
              </td>
              <td class="row-actions">
                <RouterLink class="icon-link" :to="`/saas/usuarios/${user.id}/editar`" title="Editar" aria-label="Editar usuário">
                  <i class="fa-solid fa-pen" aria-hidden="true"></i>
                </RouterLink>
                <button type="button" :title="user.status === 'active' ? 'Desativar' : 'Ativar'" @click="toggleUser(user)">
                  <i :class="user.status === 'active' ? 'fa-solid fa-toggle-on' : 'fa-solid fa-toggle-off'" aria-hidden="true"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </section>
</template>
