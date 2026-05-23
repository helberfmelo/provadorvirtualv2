<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../services/api'
import type { PortalUser } from '../services/merchantTypes'

const users = ref<PortalUser[]>([])
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
    const { data } = await api.get('/merchant/users')
    users.value = data.data
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Nao foi possivel carregar os usuarios.'
  } finally {
    loading.value = false
  }
}

async function toggleUser(user: PortalUser) {
  error.value = ''
  notice.value = ''
  const nextStatus = user.access?.status === 'active' ? 'inactive' : 'active'

  try {
    await api.patch(`/merchant/users/${user.id}`, {
      merchant_user_status: nextStatus,
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
        <span class="eyebrow">Usuarios</span>
        <h1>Acessos da empresa</h1>
        <p>Listagem de acessos. Cadastro e edicao abrem em tela propria.</p>
      </div>
      <div class="action-row compact">
        <button class="btn btn-secondary" type="button" :disabled="loading" @click="loadUsers">
          <i class="fa-solid fa-rotate" aria-hidden="true"></i>
          Atualizar
        </button>
        <RouterLink class="btn btn-primary" to="/app/usuarios/novo">
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
              <th>Perfil</th>
              <th>Status</th>
              <th>Acoes</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!users.length">
              <td colspan="4">Nenhum usuario cadastrado.</td>
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
              <td class="row-actions">
                <RouterLink class="icon-link" :to="`/app/usuarios/${user.id}/editar`" title="Editar" aria-label="Editar usuario">
                  <i class="fa-solid fa-pen" aria-hidden="true"></i>
                </RouterLink>
                <button type="button" :title="user.access?.status === 'active' ? 'Desativar' : 'Ativar'" @click="toggleUser(user)">
                  <i :class="user.access?.status === 'active' ? 'fa-solid fa-toggle-on' : 'fa-solid fa-toggle-off'" aria-hidden="true"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </section>
</template>
