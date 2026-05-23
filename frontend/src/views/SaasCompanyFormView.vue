<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api } from '../services/api'
import type { CompanyRow, MerchantRow } from '../services/saasTypes'

const route = useRoute()
const router = useRouter()
const companyId = computed(() => Number(route.params.id || 0))
const editing = computed(() => Boolean(companyId.value))

const merchants = ref<MerchantRow[]>([])
const loading = ref(false)
const saving = ref(false)
const cepLoading = ref(false)
const error = ref('')

const form = reactive({
  merchant_id: '',
  merchant_name: 'Loja teste',
  billing_status: 'trialing',
  name: 'Loja teste',
  legal_name: '',
  document: '',
  zip_code: '',
  street: '',
  number: '',
  complement: '',
  district: '',
  city: '',
  state: '',
  domain: '',
  platform: 'custom',
  external_store_id: '',
  status: 'active',
  owner_name: '',
  owner_email: '',
  owner_cpf: '',
  owner_password: '',
})

onMounted(() => {
  loadForm()
})

async function loadForm() {
  loading.value = true
  error.value = ''

  try {
    const [merchantsResponse, companiesResponse] = await Promise.all([
      api.get('/saas/merchants'),
      editing.value ? api.get('/saas/companies') : Promise.resolve({ data: { data: [] } }),
    ])
    merchants.value = merchantsResponse.data.data

    if (editing.value) {
      const company = (companiesResponse.data.data as CompanyRow[]).find((item) => item.id === companyId.value)
      if (!company) {
        error.value = 'Empresa nao encontrada.'
        return
      }

      Object.assign(form, {
        merchant_id: '',
        merchant_name: company.merchant.name,
        billing_status: company.merchant.billing_status,
        name: company.name,
        legal_name: company.legal_name || '',
        document: company.document || '',
        zip_code: company.zip_code || '',
        street: company.street || '',
        number: company.number || '',
        complement: company.complement || '',
        district: company.district || '',
        city: company.city || '',
        state: company.state || '',
        domain: company.domain || '',
        platform: company.platform || 'custom',
        external_store_id: company.external_store_id || '',
        status: company.status || 'active',
        owner_name: '',
        owner_email: '',
        owner_cpf: '',
        owner_password: '',
      })
    }
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Nao foi possivel carregar o cadastro da empresa.'
  } finally {
    loading.value = false
  }
}

async function lookupCep() {
  const cep = form.zip_code.replace(/\D+/g, '')
  if (cep.length !== 8) {
    return
  }

  cepLoading.value = true

  try {
    const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`)
    const data = await response.json()
    if (data?.erro) {
      return
    }

    form.street = data.logradouro || form.street
    form.district = data.bairro || form.district
    form.city = data.localidade || form.city
    form.state = data.uf || form.state
  } finally {
    cepLoading.value = false
  }
}

function payload(includeOwner = true) {
  const base = Object.fromEntries(
    Object.entries(form)
      .map(([key, value]) => [key, typeof value === 'string' ? value.trim() : value])
      .filter(([, value]) => value !== ''),
  )

  if (!includeOwner) {
    for (const key of ['merchant_id', 'merchant_name', 'billing_status', 'owner_name', 'owner_email', 'owner_cpf', 'owner_password']) {
      delete base[key]
    }
  }

  return base
}

async function saveCompany() {
  saving.value = true
  error.value = ''

  try {
    editing.value
      ? await api.patch(`/saas/companies/${companyId.value}`, payload(false))
      : await api.post('/saas/companies', payload(true))
    await router.push('/saas/empresas')
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Nao foi possivel salvar a empresa.'
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
        <h1>{{ editing ? 'Editar empresa' : 'Nova empresa' }}</h1>
        <p>Cadastro em tela propria para manter a listagem limpa e operacional.</p>
      </div>
      <RouterLink class="btn btn-secondary" to="/saas/empresas">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Voltar
      </RouterLink>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>

    <form class="panel-main admin-form form-page" @submit.prevent="saveCompany">
      <div class="form-grid">
        <label>
          Lojista existente
          <select v-model="form.merchant_id" :disabled="editing">
            <option value="">Criar novo lojista</option>
            <option v-for="merchant in merchants" :key="merchant.id" :value="merchant.id">
              {{ merchant.name }}
            </option>
          </select>
        </label>
        <label>
          Nome do lojista
          <input v-model="form.merchant_name" :disabled="Boolean(form.merchant_id) || editing" />
        </label>
        <label>
          Status comercial
          <select v-model="form.billing_status" :disabled="editing">
            <option value="trialing">Trial</option>
            <option value="active">Ativo</option>
            <option value="pending_payment">Pagamento pendente</option>
            <option value="past_due">Em atraso</option>
            <option value="canceled">Cancelado</option>
          </select>
        </label>
      </div>

      <div class="form-grid">
        <label>
          Nome da empresa
          <input v-model="form.name" required />
        </label>
        <label>
          Razao social
          <input v-model="form.legal_name" />
        </label>
        <label>
          CNPJ
          <input v-model="form.document" inputmode="numeric" />
        </label>
      </div>

      <div class="form-grid">
        <label>
          CEP
          <input v-model="form.zip_code" inputmode="numeric" @blur="lookupCep" />
          <small>{{ cepLoading ? 'Buscando endereco...' : 'Preenche endereco automaticamente ao sair do campo.' }}</small>
        </label>
        <label>
          Rua
          <input v-model="form.street" />
        </label>
        <label>
          Numero
          <input v-model="form.number" />
        </label>
      </div>

      <div class="form-grid">
        <label>
          Complemento
          <input v-model="form.complement" />
        </label>
        <label>
          Bairro
          <input v-model="form.district" />
        </label>
        <label>
          Cidade
          <input v-model="form.city" />
        </label>
      </div>

      <div class="form-grid">
        <label>
          UF
          <input v-model="form.state" maxlength="2" />
        </label>
        <label>
          Dominio
          <input v-model="form.domain" placeholder="loja.com.br" />
        </label>
        <label>
          Plataforma
          <select v-model="form.platform">
            <option value="bigshop">BigShop</option>
            <option value="shopify">Shopify</option>
            <option value="woocommerce">WooCommerce</option>
            <option value="nuvemshop">Nuvemshop</option>
            <option value="vtex">VTEX</option>
            <option value="tray">Tray</option>
            <option value="loja_integrada">Loja Integrada</option>
            <option value="magento">Magento</option>
            <option value="opencart">OpenCart</option>
            <option value="custom">Personalizada</option>
          </select>
        </label>
      </div>

      <div class="form-grid">
        <label>
          ID externo
          <input v-model="form.external_store_id" />
        </label>
        <label>
          Status da empresa
          <select v-model="form.status">
            <option value="active">Ativa</option>
            <option value="inactive">Inativa</option>
            <option value="trialing">Trial</option>
            <option value="pending_payment">Pagamento pendente</option>
          </select>
        </label>
      </div>

      <div v-if="!editing" class="form-grid">
        <label>
          Admin da empresa
          <input v-model="form.owner_name" />
        </label>
        <label>
          E-mail do admin
          <input v-model="form.owner_email" type="email" />
        </label>
        <label>
          CPF do admin
          <input v-model="form.owner_cpf" inputmode="numeric" />
        </label>
        <label>
          Senha inicial
          <input v-model="form.owner_password" type="password" autocomplete="new-password" />
        </label>
      </div>

      <div class="action-row compact">
        <button class="btn btn-primary" type="submit" :disabled="saving || loading">
          <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
          {{ saving ? 'Salvando...' : 'Salvar empresa' }}
        </button>
      </div>
    </form>
  </section>
</template>
