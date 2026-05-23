<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { api } from '../services/api'

type Permission = { view: boolean; edit: boolean }
type PermissionMap = Record<string, Permission>
type Module = { key: string; label: string; description: string }
type PortalUser = {
  id: number
  name: string
  email: string
  cpf: string | null
  status: string
  role: string
  access: {
    role: string
    status: string
    is_owner: boolean
    permissions: PermissionMap
  } | null
}

const users = ref<PortalUser[]>([])
const modules = ref<Module[]>([])
const selected = ref<PortalUser | null>(null)
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
  merchant_role: 'staff',
  merchant_user_status: 'active',
  is_owner: false,
})

const editing = computed(() => Boolean(form.id))

onMounted(() => {
  loadUsers()
})

async function loadUsers() {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get('/merchant/users')
    users.value = data.data
    modules.value = data.meta.modules

    if (!selected.value && users.value[0]) {
      editUser(users.value[0])
    } else if (!users.value.length) {
      newUser()
    }
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Nao foi possivel carregar os usuarios.'
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
  form.merchant_role = 'staff'
  form.merchant_user_status = 'active'
  form.is_owner = false
  permissionDraft.value = emptyPermissions()
}

function editUser(user: PortalUser) {
  selected.value = user
  form.id = user.id
  form.name = user.name
  form.email = user.email
  form.cpf = user.cpf || ''
  form.password = ''
  form.merchant_role = user.access?.role || 'staff'
  form.merchant_user_status = user.access?.status || 'active'
  form.is_owner = Boolean(user.access?.is_owner)
  permissionDraft.value = normalizePermissions(user.access?.permissions)
}

function emptyPermissions() {
  return Object.fromEntries(
    modules.value.map((module) => [module.key, { view: false, edit: false }]),
  ) as PermissionMap
}

function normalizePermissions(source?: PermissionMap) {
  return Object.fromEntries(
    modules.value.map((module) => {
      const edit = Boolean(source?.[module.key]?.edit)
      return [module.key, { view: edit || Boolean(source?.[module.key]?.view), edit }]
    }),
  ) as PermissionMap
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
    const payload = {
      name: form.name.trim(),
      email: form.email.trim(),
      cpf: form.cpf.trim() || null,
      password: form.password || undefined,
      merchant_role: form.is_owner ? 'owner' : form.merchant_role,
      merchant_user_status: form.merchant_user_status,
      is_owner: form.is_owner,
      permissions: permissionDraft.value,
    }

    const request = form.id
      ? api.patch(`/merchant/users/${form.id}`, payload)
      : api.post('/merchant/users', payload)

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
                  <button type="button" title="Editar" aria-label="Editar usuario" @click="editUser(user)">
                    <i class="fa-solid fa-pen" aria-hidden="true"></i>
                  </button>
                  <button type="button" :title="user.access?.status === 'active' ? 'Desativar' : 'Ativar'" @click="toggleUser(user)">
                    <i :class="user.access?.status === 'active' ? 'fa-solid fa-toggle-on' : 'fa-solid fa-toggle-off'" aria-hidden="true"></i>
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
          <span>{{ form.is_owner ? 'acesso total' : 'permissoes por modulo' }}</span>
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
            Perfil
            <select v-model="form.merchant_role" :disabled="form.is_owner">
              <option value="staff">Equipe</option>
              <option value="manager">Gerente</option>
              <option value="owner">Dono</option>
            </select>
          </label>
          <label>
            Status
            <select v-model="form.merchant_user_status">
              <option value="active">Ativo</option>
              <option value="inactive">Inativo</option>
            </select>
          </label>
        </div>

        <label class="check-line">
          <input v-model="form.is_owner" type="checkbox" />
          <span>Dono da empresa com acesso total</span>
        </label>

        <div class="permission-grid" :class="{ disabled: form.is_owner }">
          <div v-for="module in modules" :key="module.key" class="permission-row">
            <span>
              <strong>{{ module.label }}</strong>
              <small>{{ module.description }}</small>
            </span>
            <label>
              <input
                v-model="permissionDraft[module.key].view"
                type="checkbox"
                :disabled="form.is_owner"
                @change="togglePermission(module.key, 'view')"
              />
              Ver
            </label>
            <label>
              <input
                v-model="permissionDraft[module.key].edit"
                type="checkbox"
                :disabled="form.is_owner"
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
