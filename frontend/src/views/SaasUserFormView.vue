<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api } from '../services/api'
import {
  emptyPermissions,
  normalizePermissions,
  type MerchantOption,
  type Module,
  type PermissionMap,
  type SaasUser,
} from '../services/saasTypes'

const route = useRoute()
const router = useRouter()
const userId = computed(() => Number(route.params.id || 0))
const editing = computed(() => Boolean(userId.value))

const saasModules = ref<Module[]>([])
const merchantModules = ref<Module[]>([])
const merchants = ref<MerchantOption[]>([])
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
  merchant_id: '',
  merchant_role: 'staff',
  merchant_user_status: 'active',
  is_owner: false,
})

const activeModules = computed(() => form.role === 'merchant' ? merchantModules.value : saasModules.value)

onMounted(() => {
  loadForm()
})

async function loadForm() {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get('/saas/users')
    saasModules.value = data.meta.saas_modules
    merchantModules.value = data.meta.merchant_modules
    merchants.value = data.meta.merchants

    if (editing.value) {
      const user = (data.data as SaasUser[]).find((item) => item.id === userId.value)
      if (!user) {
        error.value = 'Usuario nao encontrado.'
        return
      }
      editUser(user)
      return
    }

    permissionDraft.value = emptyPermissions(saasModules.value)
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Nao foi possivel carregar o usuario.'
  } finally {
    loading.value = false
  }
}

function editUser(user: SaasUser) {
  const firstAccess = user.merchants[0]

  form.id = user.id
  form.name = user.name
  form.email = user.email
  form.cpf = user.cpf || ''
  form.password = ''
  form.role = user.role
  form.status = user.status
  form.merchant_id = firstAccess?.id ? String(firstAccess.id) : ''
  form.merchant_role = firstAccess?.access.role || 'staff'
  form.merchant_user_status = firstAccess?.access.status || 'active'
  form.is_owner = Boolean(firstAccess?.access.is_owner)
  permissionDraft.value = normalizePermissions(
    user.role === 'merchant' ? firstAccess?.access.permissions : user.permissions,
    user.role === 'merchant' ? merchantModules.value : saasModules.value,
  )
}

function resetPermissionsForRole() {
  form.merchant_id = form.role === 'merchant' ? form.merchant_id : ''
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
    if (form.role === 'merchant' && !form.merchant_id) {
      error.value = 'Selecione o lojista para o acesso de empresa.'
      return
    }

    const payload = {
      name: form.name.trim(),
      email: form.email.trim(),
      cpf: form.cpf.trim() || null,
      password: form.password || undefined,
      role: form.role,
      status: form.status,
      permissions: permissionDraft.value,
      merchant_id: form.role === 'merchant' ? Number(form.merchant_id) : undefined,
      merchant_role: form.is_owner ? 'owner' : form.merchant_role,
      merchant_user_status: form.merchant_user_status,
      is_owner: form.role === 'merchant' ? form.is_owner : false,
    }

    form.id
      ? await api.patch(`/saas/users/${form.id}`, payload)
      : await api.post('/saas/users', payload)
    await router.push('/saas/usuarios')
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Nao foi possivel salvar o usuario.'
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
        <h1>{{ editing ? 'Editar usuario' : 'Novo usuario' }}</h1>
        <p>Controle de papel, status e permissoes por modulo.</p>
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
          Tipo de usuario
          <select v-model="form.role" @change="resetPermissionsForRole">
            <option value="admin">Master admin</option>
            <option value="support">Suporte SaaS</option>
            <option value="merchant">Lojista</option>
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

      <div v-if="form.role === 'merchant'" class="form-grid">
        <label>
          Lojista
          <select v-model="form.merchant_id" required>
            <option value="">Selecione</option>
            <option v-for="merchant in merchants" :key="merchant.id" :value="merchant.id">
              {{ merchant.name }}
            </option>
          </select>
        </label>
        <label>
          Perfil na empresa
          <select v-model="form.merchant_role" :disabled="form.is_owner">
            <option value="staff">Equipe</option>
            <option value="manager">Gerente</option>
            <option value="owner">Dono</option>
          </select>
        </label>
        <label>
          Status na empresa
          <select v-model="form.merchant_user_status">
            <option value="active">Ativo</option>
            <option value="inactive">Inativo</option>
          </select>
        </label>
      </div>

      <label v-if="form.role === 'merchant'" class="check-line">
        <input v-model="form.is_owner" type="checkbox" />
        <span>Dono da empresa com acesso total</span>
      </label>

      <div class="permission-grid" :class="{ disabled: form.role === 'merchant' && form.is_owner }">
        <div v-for="module in activeModules" :key="module.key" class="permission-row">
          <span>
            <strong>{{ module.label }}</strong>
            <small>{{ module.description }}</small>
          </span>
          <label>
            <input
              v-model="permissionDraft[module.key].view"
              type="checkbox"
              :disabled="form.role === 'merchant' && form.is_owner"
              @change="togglePermission(module.key, 'view')"
            />
            Ver
          </label>
          <label>
            <input
              v-model="permissionDraft[module.key].edit"
              type="checkbox"
              :disabled="form.role === 'merchant' && form.is_owner"
              @change="togglePermission(module.key, 'edit')"
            />
            Editar
          </label>
        </div>
      </div>

      <div class="action-row compact">
        <button class="btn btn-primary" type="submit" :disabled="saving || loading">
          <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
          {{ saving ? 'Salvando...' : 'Salvar usuario' }}
        </button>
      </div>
    </form>
  </section>
</template>
