<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../services/api'
import type { SaasUser } from '../services/saasTypes'

const users = ref<SaasUser[]>([])
const loading = ref(false)
const error = ref('')
const notice = ref('')

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
    error.value = requestError.response?.data?.message || 'Nao foi possivel carregar os usuarios SaaS.'
  } finally {
    loading.value = false
  }
}

async function toggleUser(user: SaasUser) {
  error.value = ''
  notice.value = ''
  const nextStatus = user.status === 'active' ? 'inactive' : 'active'

  try {
    await api.patch(`/saas/users/${user.id}`, {
      status: nextStatus,
    })
    notice.value = nextStatus === 'active' ? 'Usuario ativado.' : 'Usuario desativado.'
    await loadUsers()
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Nao foi possivel alterar o status.'
  }
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">SaaS</span>
        <h1>Usuarios SaaS</h1>
        <p>Listagem de usuarios internos e acessos vinculados. Cadastro e edicao ficam em telas proprias.</p>
      </div>
      <div class="action-row compact">
        <button class="btn btn-secondary" type="button" :disabled="loading" @click="loadUsers">
          <i class="fa-solid fa-rotate" aria-hidden="true"></i>
          Atualizar
        </button>
        <RouterLink class="btn btn-primary" to="/saas/usuarios/novo">
          <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
          Novo usuario
        </RouterLink>
      </div>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>
    <p v-if="notice" class="success-message">{{ notice }}</p>

    <section class="panel-main subsection">
      <div class="subsection-heading">
        <h2>Usuarios cadastrados</h2>
        <span>{{ loading ? 'carregando' : `${users.length} usuarios` }}</span>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Usuario</th>
              <th>Tipo</th>
              <th>Empresas</th>
              <th>Status</th>
              <th>Acoes</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!users.length">
              <td colspan="5">Nenhum usuario cadastrado.</td>
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
                <RouterLink class="icon-link" :to="`/saas/usuarios/${user.id}/editar`" title="Editar" aria-label="Editar usuario">
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
