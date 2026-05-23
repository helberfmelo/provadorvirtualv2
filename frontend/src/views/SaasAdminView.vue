<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { api } from '../services/api'

type Summary = Record<string, number>
type MerchantRow = {
  id: number
  name: string
  slug: string
  billing_status: string
  companies_count: number
  products_count: number
  measurement_tables_count: number
  widget_installs_count: number
  platform_connections_count: number
  recommendations_7d: number
  last_recommendation_at: string | null
}
type CompanyRow = {
  id: number
  access_code: string
  name: string
  legal_name: string | null
  document: string | null
  zip_code: string | null
  city: string | null
  state: string | null
  domain: string | null
  platform: string
  status: string
  merchant: {
    id: number
    name: string
    slug: string
    billing_status: string
  }
}

const summary = ref<Summary>({})
const merchants = ref<MerchantRow[]>([])
const companies = ref<CompanyRow[]>([])
const loading = ref(false)
const savingCompany = ref(false)
const cepLoading = ref(false)
const error = ref('')
const notice = ref('')

const companyForm = reactive({
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
  status: 'active',
  owner_name: '',
  owner_email: '',
  owner_cpf: '',
  owner_password: '',
})

onMounted(() => {
  loadSaas()
})

async function loadSaas() {
  loading.value = true
  error.value = ''

  try {
    const [overviewResponse, merchantsResponse, companiesResponse] = await Promise.all([
      api.get('/saas/overview'),
      api.get('/saas/merchants'),
      api.get('/saas/companies'),
    ])

    summary.value = overviewResponse.data.data.summary
    merchants.value = merchantsResponse.data.data
    companies.value = companiesResponse.data.data
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Nao foi possivel carregar o painel SaaS.'
  } finally {
    loading.value = false
  }
}

async function lookupCep() {
  const cep = companyForm.zip_code.replace(/\D+/g, '')
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

    companyForm.street = data.logradouro || companyForm.street
    companyForm.district = data.bairro || companyForm.district
    companyForm.city = data.localidade || companyForm.city
    companyForm.state = data.uf || companyForm.state
  } finally {
    cepLoading.value = false
  }
}

async function createCompany() {
  savingCompany.value = true
  notice.value = ''
  error.value = ''

  try {
    const payload = Object.fromEntries(
      Object.entries(companyForm)
        .map(([key, value]) => [key, typeof value === 'string' ? value.trim() : value])
        .filter(([, value]) => value !== ''),
    )

    const { data } = await api.post('/saas/companies', payload)
    notice.value = `Empresa criada com codigo ${data.data.access_code}.`
    await loadSaas()
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Nao foi possivel criar a empresa.'
  } finally {
    savingCompany.value = false
  }
}
</script>

<template>
  <section class="dashboard app-workspace">
    <div class="page-heading">
      <div>
        <span class="eyebrow">SaaS</span>
        <h1>Operacao dos lojistas</h1>
      </div>
      <button class="btn btn-secondary" type="button" :disabled="loading" @click="loadSaas">
        <i class="fa-solid fa-rotate" aria-hidden="true"></i>
        Atualizar
      </button>
    </div>

    <p v-if="error" class="form-error">{{ error }}</p>
    <p v-if="notice" class="success-message">{{ notice }}</p>

    <template v-if="!error || companies.length">
      <div class="metric-grid">
        <article class="metric-card">
          <i class="fa-solid fa-store" aria-hidden="true"></i>
          <strong>{{ summary.merchants || 0 }}</strong>
          <span>{{ summary.companies || 0 }} empresas cadastradas</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-code" aria-hidden="true"></i>
          <strong>{{ summary.active_widgets || 0 }}</strong>
          <span>Widgets ativos</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
          <strong>{{ summary.recommendations_7d || 0 }}</strong>
          <span>Recomendacoes em 7 dias</span>
        </article>
        <article class="metric-card">
          <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
          <strong>{{ (summary.failed_imports_7d || 0) + (summary.failed_integrations_7d || 0) }}</strong>
          <span>Falhas recentes</span>
        </article>
      </div>

      <div class="saas-grid">
        <form class="panel-main admin-form" @submit.prevent="createCompany">
          <div class="subsection-heading">
            <h2>Cadastrar empresa teste</h2>
            <span>Sem checkout publico</span>
          </div>

          <div class="form-grid">
            <label>
              Lojista existente
              <select v-model="companyForm.merchant_id">
                <option value="">Criar novo lojista</option>
                <option v-for="merchant in merchants" :key="merchant.id" :value="merchant.id">
                  {{ merchant.name }}
                </option>
              </select>
            </label>
            <label>
              Nome do lojista
              <input v-model="companyForm.merchant_name" :disabled="Boolean(companyForm.merchant_id)" />
            </label>
            <label>
              Status comercial
              <select v-model="companyForm.billing_status">
                <option value="trialing">Trial</option>
                <option value="active">Ativo</option>
                <option value="pending_payment">Pagamento pendente</option>
              </select>
            </label>
          </div>

          <div class="form-grid">
            <label>
              Nome da empresa
              <input v-model="companyForm.name" required />
            </label>
            <label>
              Razao social
              <input v-model="companyForm.legal_name" />
            </label>
            <label>
              CNPJ
              <input v-model="companyForm.document" inputmode="numeric" />
            </label>
          </div>

          <div class="form-grid">
            <label>
              CEP
              <input v-model="companyForm.zip_code" inputmode="numeric" required @blur="lookupCep" />
              <small>{{ cepLoading ? 'Buscando endereco...' : 'Preenche endereco automaticamente ao sair do campo.' }}</small>
            </label>
            <label>
              Rua
              <input v-model="companyForm.street" />
            </label>
            <label>
              Numero
              <input v-model="companyForm.number" />
            </label>
          </div>

          <div class="form-grid">
            <label>
              Complemento
              <input v-model="companyForm.complement" />
            </label>
            <label>
              Bairro
              <input v-model="companyForm.district" />
            </label>
            <label>
              Cidade
              <input v-model="companyForm.city" />
            </label>
          </div>

          <div class="form-grid">
            <label>
              UF
              <input v-model="companyForm.state" maxlength="2" />
            </label>
            <label>
              Dominio
              <input v-model="companyForm.domain" placeholder="loja.com.br" />
            </label>
            <label>
              Plataforma
              <select v-model="companyForm.platform">
                <option value="bigshop">BigShop</option>
                <option value="shopify">Shopify</option>
                <option value="woocommerce">WooCommerce</option>
                <option value="nuvemshop">Nuvemshop</option>
                <option value="vtex">VTEX</option>
                <option value="tray">Tray</option>
                <option value="custom">Personalizada</option>
              </select>
            </label>
          </div>

          <div class="form-grid">
            <label>
              Admin da empresa
              <input v-model="companyForm.owner_name" />
            </label>
            <label>
              E-mail do admin
              <input v-model="companyForm.owner_email" type="email" />
            </label>
            <label>
              Senha inicial
              <input v-model="companyForm.owner_password" type="password" autocomplete="new-password" />
            </label>
          </div>

          <div class="action-row compact">
            <button class="btn btn-primary" type="submit" :disabled="savingCompany">
              <i class="fa-solid fa-building-circle-check" aria-hidden="true"></i>
              Criar empresa
            </button>
          </div>
        </form>

        <section class="panel-main">
          <div class="subsection-heading">
            <h2>Empresas</h2>
            <span>{{ companies.length }} recentes</span>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Codigo</th>
                  <th>Empresa</th>
                  <th>Lojista</th>
                  <th>Plataforma</th>
                  <th>Endereco</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!companies.length">
                  <td colspan="6">Sem empresas.</td>
                </tr>
                <tr v-for="company in companies" :key="company.id">
                  <td><strong>{{ company.access_code }}</strong></td>
                  <td>
                    <strong>{{ company.name }}</strong>
                    <small>{{ company.document || company.domain || 'sem documento' }}</small>
                  </td>
                  <td>{{ company.merchant.name }}</td>
                  <td>{{ company.platform }}</td>
                  <td>{{ [company.city, company.state].filter(Boolean).join('/') || '-' }}</td>
                  <td>{{ company.status }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </div>

      <section class="panel-main">
        <div class="subsection-heading">
          <h2>Lojistas</h2>
          <span>{{ merchants.length }} recentes</span>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Lojista</th>
                <th>Status</th>
                <th>Produtos</th>
                <th>Tabelas</th>
                <th>Integracoes</th>
                <th>7 dias</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!merchants.length">
                <td colspan="6">Sem lojistas.</td>
              </tr>
              <tr v-for="merchant in merchants" :key="merchant.id">
                <td>
                  <strong>{{ merchant.name }}</strong>
                  <small>{{ merchant.slug }}</small>
                </td>
                <td>{{ merchant.billing_status }}</td>
                <td>{{ merchant.products_count }}</td>
                <td>{{ merchant.measurement_tables_count }}</td>
                <td>{{ merchant.platform_connections_count }}</td>
                <td>{{ merchant.recommendations_7d }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </section>
</template>
