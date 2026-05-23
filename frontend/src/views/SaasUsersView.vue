<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { api } from '../services/api'

type Permission = { view: boolean; edit: boolean }
type PermissionMap = Record<string, Permission>
type Module = { key: string; label: string; description: string }
type MerchantOption = { id: number; name: string; slug: string }
type SaasUser = {
  id: number
  name: string
  email: string
  cpf: string | null
  role: string
  status: string
  permissions: PermissionMap
  merchants: Array<{
    id: number
    name: string
    slug: string
    access: {
      role: string
      status: string
      is_owner: boolean
      permissions: PermissionMap
    }
  }>
}

const users = ref<SaasUser[]>([])
const saasModules = ref<Module[]>([])
const merchantModules = ref<Module[]>([])
const merchants = ref<MerchantOption[]>([])
const selected = ref<SaasUser | null>(null)
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const notice = ref('')
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

const editing = computed(() => Boolean(form.id))
const activeModules = computed(() => form.role === 'merchant' ? merchantModules.value : saasModules.value)

onMounted(() => {
  loadUsers()
})

async function loadUsers() {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get('/saas/users')
    users.value = data.data
    saasModules.value = data.meta.saas_modules
    merchantModules.value = data.meta.merchant_modules
    merchants.value = data.meta.merchants

    if (!selected.value && users.value[0]) {
      editUser(users.value[0])
    } else if (!users.value.length) {
      newUser()
    }
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Nao foi possivel carregar os usuarios SaaS.'
  } finally {
    loading.value = false
  }
}

function newUser() {
  selected.value = null
  form.id = null
  form.name = ''
  form.email = ''
  form.cpf = ''
  form.password = ''
  form.role = 'support'
  form.status = 'active'
  form.merchant_id = ''
  form.merchant_role = 'staff'
  form.merchant_user_status = 'active'
  form.is_owner = false
  permissionDraft.value = emptyPermissions(saasModules.value)
}

function editUser(user: SaasUser) {
  const firstAccess = user.merchants[0]

  selected.value = user
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

function emptyPermissions(list: Module[]) {
  return Object.fromEntries(
    list.map((module) => [module.key, { view: false, edit: false }]),
  ) as PermissionMap
}

function normalizePermissions(source: PermissionMap | undefined, list: Module[]) {
  return Object.fromEntries(
    list.map((module) => {
      const edit = Boolean(source?.[module.key]?.edit)
      return [module.key, { view: edit || Boolean(source?.[module.key]?.view), edit }]
    }),
  ) as PermissionMap
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
  notice.value = ''

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

    const request = form.id
      ? api.patch(`/saas/users/${form.id}`, payload)
      : api.post('/saas/users', payload)

    const { data } = await request
    notice.value = form.id ? 'Usuario atualizado.' : 'Usuario criado.'
    await loadUsers()
    editUser(data.data)
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Nao foi possivel salvar o usuario.'
  } finally {
    saving.value = false
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
        <h1>Usuarios e permissoes</h1>
      </div>
      <button class="btn btn-secondary" type="button" @click="newUser">
        <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
        Novo usuario
      </button>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>
    <p v-if="notice" class="success-message">{{ notice }}</p>

    <div class="user-grid">
      <section class="panel-main">
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
                  <button type="button" title="Editar" aria-label="Editar usuario" @click="editUser(user)">
                    <i class="fa-solid fa-pen" aria-hidden="true"></i>
                  </button>
                  <button type="button" :title="user.status === 'active' ? 'Desativar' : 'Ativar'" @click="toggleUser(user)">
                    <i :class="user.status === 'active' ? 'fa-solid fa-toggle-on' : 'fa-solid fa-toggle-off'" aria-hidden="true"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <form class="panel-main admin-form" @submit.prevent="saveUser">
        <div class="subsection-heading">
          <h2>{{ editing ? 'Editar usuario' : 'Novo usuario' }}</h2>
          <span>{{ form.role === 'merchant' ? 'portal da empresa' : 'portal SaaS' }}</span>
        </div>

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
          <button class="btn btn-primary" type="submit" :disabled="saving">
            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
            Salvar usuario
          </button>
        </div>
      </form>
    </div>
  </section>
</template>
