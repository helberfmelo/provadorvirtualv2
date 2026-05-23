<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api } from '../services/api'
import {
  emptyPermissions,
  normalizePermissions,
  type CompanyOption,
  type Module,
  type PermissionMap,
  type SaasUser,
} from '../services/saasTypes'

const route = useRoute()
const router = useRouter()
const userId = computed(() => Number(route.params.id || 0))
const editing = computed(() => Boolean(userId.value))

const merchantModules = ref<Module[]>([])
const companies = ref<CompanyOption[]>([])
const users = ref<SaasUser[]>([])
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
  status: 'active',
  merchant_company_id: '',
  merchant_role: 'staff',
  merchant_user_status: 'active',
  is_owner: false,
})

const selectedCompany = computed(() => companies.value.find((company) => String(company.id) === String(form.merchant_company_id)) || null)

onMounted(() => {
  loadForm()
})

async function loadForm() {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get('/saas/company-users')
    merchantModules.value = data.meta.merchant_modules
    companies.value = data.meta.companies
    users.value = data.data

    if (editing.value) {
      const user = users.value.find((item) => item.id === userId.value)
      if (!user) {
        error.value = 'Usuário não encontrado.'
        return
      }
      editUser(user)
      return
    }

    permissionDraft.value = emptyPermissions(merchantModules.value)
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível carregar o usuário da empresa.'
  } finally {
    loading.value = false
  }
}

function editUser(user: SaasUser) {
  const queryCompanyId = Number(route.query.company || 0)
  const firstAccess = user.merchants.find((merchant) => merchant.access.merchant_company_id === queryCompanyId) || user.merchants[0]

  form.id = user.id
  form.name = user.name
  form.email = user.email
  form.cpf = user.cpf || ''
  form.password = ''
  form.status = user.status
  form.merchant_company_id = firstAccess?.access.merchant_company_id ? String(firstAccess.access.merchant_company_id) : ''
  form.merchant_role = firstAccess?.access.role || 'staff'
  form.merchant_user_status = firstAccess?.access.status || 'active'
  form.is_owner = Boolean(firstAccess?.access.is_owner)
  permissionDraft.value = normalizePermissions(firstAccess?.access.permissions, merchantModules.value)
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
    if (!form.merchant_company_id) {
      error.value = 'Selecione a empresa cliente.'
      return
    }

    const payload = {
      name: form.name.trim(),
      email: form.email.trim(),
      cpf: form.cpf.trim() || null,
      password: form.password || undefined,
      status: form.status,
      merchant_company_id: Number(form.merchant_company_id),
      merchant_role: form.is_owner ? 'owner' : form.merchant_role,
      merchant_user_status: form.merchant_user_status,
      is_owner: form.is_owner,
      permissions: permissionDraft.value,
    }

    form.id
      ? await api.patch(`/saas/company-users/${form.id}`, payload)
      : await api.post('/saas/company-users', payload)
    await router.push('/saas/usuarios-empresas')
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Não foi possível salvar o usuário da empresa.'
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
        <h1>{{ editing ? 'Editar usuário da empresa' : 'Novo usuário da empresa' }}</h1>
        <p>Vincule o usuário a uma empresa cliente e defina os módulos do portal da empresa.</p>
      </div>
      <RouterLink class="btn btn-secondary" to="/saas/usuarios-empresas">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Voltar
      </RouterLink>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>

    <form class="panel-main admin-form form-page" @submit.prevent="saveUser">
      <div class="form-grid">
        <label>
          Empresa cliente
          <select v-model="form.merchant_company_id" required>
            <option value="">Selecione</option>
            <option v-for="company in companies" :key="company.id" :value="company.id">
              {{ company.access_code || company.id }} - {{ company.name }} - {{ company.document || company.merchant.name }}
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
          Status do acesso
          <select v-model="form.merchant_user_status">
            <option value="active">Ativo</option>
            <option value="inactive">Inativo</option>
          </select>
        </label>
      </div>

      <div v-if="selectedCompany" class="detail-strip">
        <span><strong>Código</strong>{{ selectedCompany.access_code || selectedCompany.id }}</span>
        <span><strong>Plataforma</strong>{{ selectedCompany.platform }}</span>
        <span><strong>Lojista</strong>{{ selectedCompany.merchant.name }}</span>
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
          Status global
          <select v-model="form.status">
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
        <div v-for="module in merchantModules" :key="module.key" class="permission-row">
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
        <button class="btn btn-primary" type="submit" :disabled="saving || loading">
          <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
          {{ saving ? 'Salvando...' : 'Salvar usuário' }}
        </button>
      </div>
    </form>
  </section>
</template>
