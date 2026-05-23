<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api } from '../services/api'
import {
  emptyPermissions,
  normalizePermissions,
  type Module,
  type PermissionMap,
  type SaasUser,
} from '../services/saasTypes'

const route = useRoute()
const router = useRouter()
const userId = computed(() => Number(route.params.id || 0))
const editing = computed(() => Boolean(userId.value))

const saasModules = ref<Module[]>([])
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const permissionDraft = ref<PermissionMap>({})

const form = reactive({
  id: null as number | null,
  name: '',
  email: '',
  cpf: '',
  password: '',
  role: 'support',
  status: 'active',
})

const activeModules = computed(() => saasModules.value)

onMounted(() => {
  loadForm()
})

async function loadForm() {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get('/saas/users')
    saasModules.value = data.meta.saas_modules

    if (editing.value) {
      const user = (data.data as SaasUser[]).find((item) => item.id === userId.value)
      if (!user) {
        error.value = 'Usuário não encontrado.'
        return
      }
      editUser(user)
      return
    }

    permissionDraft.value = emptyPermissions(saasModules.value)
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar o usuário.'
  } finally {
    loading.value = false
  }
}

function editUser(user: SaasUser) {
  form.id = user.id
  form.name = user.name
  form.email = user.email
  form.cpf = user.cpf || ''
  form.password = ''
  form.role = user.role
  form.status = user.status
  permissionDraft.value = normalizePermissions(
    user.permissions,
    saasModules.value,
  )
}

function resetPermissionsForRole() {
  permissionDraft.value = emptyPermissions(activeModules.value)
}

function togglePermission(moduleKey: string, action: 'view' | 'edit') {
  const permission = permissionDraft.value[moduleKey]

  if (!permission) {
    return
  }

  if (action === 'edit' && permission.edit) {
    permission.view = true
  }

  if (action === 'view' && !permission.view) {
    permission.edit = false
  }
}

async function saveUser() {
  saving.value = true
  error.value = ''

  try {
    const payload = {
      name: form.name.trim(),
      email: form.email.trim(),
      cpf: form.cpf.trim() || null,
      password: form.password || undefined,
      role: form.role,
      status: form.status,
      permissions: permissionDraft.value,
    }

    form.id
      ? await api.patch(`/saas/users/${form.id}`, payload)
      : await api.post('/saas/users', payload)
    await router.push('/saas/usuarios')
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível salvar o usuário.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">SaaS</span>
        <h1>{{ editing ? 'Editar usuário' : 'Novo usuário' }}</h1>
        <p>Controle de papel, status e permissões por módulo.</p>
      </div>
      <RouterLink class="btn btn-secondary" to="/saas/usuarios">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Voltar
      </RouterLink>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>

    <form class="panel-main admin-form form-page" @submit.prevent="saveUser">
      <div class="form-grid">
        <label>
          Nome
          <input v-model="form.name" required />
        </label>
        <label>
          E-mail
          <input v-model="form.email" type="email" required />
        </label>
        <label>
          CPF
          <input v-model="form.cpf" inputmode="numeric" />
        </label>
      </div>

      <div class="form-grid">
        <label>
          Senha
          <input v-model="form.password" type="password" autocomplete="new-password" :placeholder="editing ? 'Manter senha atual' : 'Senha inicial'" />
        </label>
        <label>
          Tipo de usuário
          <select v-model="form.role" @change="resetPermissionsForRole">
            <option value="admin">Master admin</option>
            <option value="support">Suporte SaaS</option>
          </select>
        </label>
        <label>
          Status
          <select v-model="form.status">
            <option value="active">Ativo</option>
            <option value="inactive">Inativo</option>
          </select>
        </label>
      </div>

      <div class="permission-grid">
        <div v-for="module in activeModules" :key="module.key" class="permission-row">
          <span>
            <strong>{{ module.label }}</strong>
            <small>{{ module.description }}</small>
          </span>
          <label>
            <input
              v-model="permissionDraft[module.key].view"
              type="checkbox"
              @change="togglePermission(module.key, 'view')"
            />
            Ver
          </label>
          <label>
            <input
              v-model="permissionDraft[module.key].edit"
              type="checkbox"
              @change="togglePermission(module.key, 'edit')"
            />
            Editar
          </label>
        </div>
      </div>

      <div class="action-row compact">
        <button class="btn btn-primary" type="submit" :disabled="saving || loading">
          <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
          {{ saving ? 'Salvando...' : 'Salvar usuário' }}
        </button>
      </div>
    </form>
  </section>
</template>
