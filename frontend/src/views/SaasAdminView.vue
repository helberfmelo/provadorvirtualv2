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
  street: string | null
  number: string | null
  complement: string | null
  district: string | null
  city: string | null
  state: string | null
  domain: string | null
  platform: string
  external_store_id: string | null
  status: string
  merchant: {
    id: number
    name: string
    slug: string
    billing_status: string
  }
}
type EmailSettings = {
  id: number | null
  mailer: string
  host: string
  port: number | null
  username: string
  has_smtp_password: boolean
  encryption: string | null
  from_address: string
  from_name: string
  is_active: boolean
}
type TransactionalEmail = {
  id: number
  code: string
  name: string
  description: string | null
  subject: string
  body: string
  variables: string[]
  is_active: boolean
  updated_at: string | null
}
type TransactionalEmailSend = {
  id: number
  code: string
  template_name: string | null
  company_name: string | null
  recipient_email: string | null
  recipient_name: string | null
  subject: string | null
  status: string
  error: string | null
  sent_at: string | null
  created_at: string | null
}

const summary = ref<Summary>({})
const merchants = ref<MerchantRow[]>([])
const companies = ref<CompanyRow[]>([])
const transactionalEmails = ref<TransactionalEmail[]>([])
const emailSends = ref<TransactionalEmailSend[]>([])
const loading = ref(false)
const savingCompany = ref(false)
const savingEmailSettings = ref(false)
const savingTemplate = ref(false)
const cepLoading = ref(false)
const error = ref('')
const notice = ref('')
const smtpPassword = ref('')
const editingTemplateId = ref<number | null>(null)
const editingCompanyId = ref<number | null>(null)

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
const emailSettings = reactive<EmailSettings>({
  id: null,
  mailer: 'smtp',
  host: '',
  port: 587,
  username: '',
  has_smtp_password: false,
  encryption: 'tls',
  from_address: 'noreply@provadorvirtual.online',
  from_name: 'Provador Virtual',
  is_active: false,
})
const templateForm = reactive({
  code: '',
  name: '',
  description: '',
  subject: '',
  body: '',
  variables: 'nome, empresa, codigo_empresa, link_login',
  is_active: true,
})

onMounted(() => {
  loadSaas()
})

async function loadSaas() {
  loading.value = true
  error.value = ''

  try {
    const [overviewResponse, merchantsResponse, companiesResponse, emailSettingsResponse, transactionalEmailsResponse, emailSendsResponse] = await Promise.all([
      api.get('/saas/overview'),
      api.get('/saas/merchants'),
      api.get('/saas/companies'),
      api.get('/saas/email-settings'),
      api.get('/saas/transactional-emails'),
      api.get('/saas/transactional-email-sends'),
    ])

    summary.value = overviewResponse.data.data.summary
    merchants.value = merchantsResponse.data.data
    companies.value = companiesResponse.data.data
    Object.assign(emailSettings, normalizeEmailSettings(emailSettingsResponse.data.data))
    transactionalEmails.value = transactionalEmailsResponse.data.data
    emailSends.value = emailSendsResponse.data.data
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Nao foi possivel carregar o painel SaaS.'
  } finally {
    loading.value = false
  }
}

function normalizeEmailSettings(data: Partial<EmailSettings>): EmailSettings {
  return {
    id: data.id ?? null,
    mailer: data.mailer || 'smtp',
    host: data.host || '',
    port: data.port || 587,
    username: data.username || '',
    has_smtp_password: Boolean(data.has_smtp_password),
    encryption: data.encryption || 'tls',
    from_address: data.from_address || 'noreply@provadorvirtual.online',
    from_name: data.from_name || 'Provador Virtual',
    is_active: Boolean(data.is_active),
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

function resetCompanyForm() {
  editingCompanyId.value = null
  Object.assign(companyForm, {
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
}

function editCompany(company: CompanyRow) {
  editingCompanyId.value = company.id
  Object.assign(companyForm, {
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
    status: company.status || 'active',
    owner_name: '',
    owner_email: '',
    owner_cpf: '',
    owner_password: '',
  })
}

function companyPayload(includeOwner = true) {
  const base = Object.fromEntries(
    Object.entries(companyForm)
      .map(([key, value]) => [key, typeof value === 'string' ? value.trim() : value])
      .filter(([, value]) => value !== ''),
  )

  if (includeOwner) {
    return base
  }

  for (const key of ['merchant_id', 'merchant_name', 'billing_status', 'owner_name', 'owner_email', 'owner_cpf', 'owner_password']) {
    delete base[key]
  }

  return base
}

async function saveCompany() {
  savingCompany.value = true
  notice.value = ''
  error.value = ''

  try {
    const { data } = editingCompanyId.value
      ? await api.patch(`/saas/companies/${editingCompanyId.value}`, companyPayload(false))
      : await api.post('/saas/companies', companyPayload(true))
    notice.value = editingCompanyId.value
      ? 'Empresa atualizada.'
      : `Empresa criada com codigo ${data.data.access_code}.`
    if (!editingCompanyId.value) {
      resetCompanyForm()
    }
    await loadSaas()
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Nao foi possivel salvar a empresa.'
  } finally {
    savingCompany.value = false
  }
}

async function toggleCompany(company: CompanyRow) {
  notice.value = ''
  error.value = ''

  try {
    await api.patch(`/saas/companies/${company.id}`, {
      name: company.name,
      legal_name: company.legal_name,
      document: company.document,
      zip_code: company.zip_code,
      street: company.street,
      number: company.number,
      complement: company.complement,
      district: company.district,
      city: company.city,
      state: company.state,
      domain: company.domain,
      platform: company.platform,
      external_store_id: company.external_store_id,
      status: company.status === 'active' ? 'inactive' : 'active',
    })
    notice.value = company.status === 'active' ? 'Empresa desativada.' : 'Empresa ativada.'
    await loadSaas()
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Nao foi possivel alterar o status da empresa.'
  }
}

async function saveEmailSettings() {
  savingEmailSettings.value = true
  notice.value = ''
  error.value = ''

  try {
    const payload: Record<string, unknown> = {
      mailer: emailSettings.mailer,
      host: emailSettings.host.trim(),
      port: emailSettings.port,
      username: emailSettings.username.trim(),
      encryption: emailSettings.encryption || null,
      from_address: emailSettings.from_address.trim(),
      from_name: emailSettings.from_name.trim(),
      is_active: emailSettings.is_active,
    }

    if (smtpPassword.value.trim()) {
      payload.smtp_password = smtpPassword.value.trim()
    }

    const { data } = await api.patch('/saas/email-settings', payload)
    Object.assign(emailSettings, normalizeEmailSettings(data.data))
    smtpPassword.value = ''
    notice.value = 'Credenciais de e-mail salvas.'
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Nao foi possivel salvar as credenciais de e-mail.'
  } finally {
    savingEmailSettings.value = false
  }
}

function resetTemplateForm() {
  editingTemplateId.value = null
  Object.assign(templateForm, {
    code: '',
    name: '',
    description: '',
    subject: '',
    body: '',
    variables: 'nome, empresa, codigo_empresa, link_login',
    is_active: true,
  })
}

function editTemplate(template: TransactionalEmail) {
  editingTemplateId.value = template.id
  Object.assign(templateForm, {
    code: template.code,
    name: template.name,
    description: template.description || '',
    subject: template.subject,
    body: template.body,
    variables: (template.variables || []).join(', '),
    is_active: template.is_active,
  })
}

async function saveTemplate() {
  savingTemplate.value = true
  notice.value = ''
  error.value = ''

  try {
    const payload = {
      code: templateForm.code.trim(),
      name: templateForm.name.trim(),
      description: templateForm.description.trim(),
      subject: templateForm.subject.trim(),
      body: templateForm.body.trim(),
      variables: templateForm.variables
        .split(/[,\n]+/)
        .map((variable) => variable.trim())
        .filter(Boolean),
      is_active: templateForm.is_active,
    }
    const request = editingTemplateId.value
      ? api.patch(`/saas/transactional-emails/${editingTemplateId.value}`, payload)
      : api.post('/saas/transactional-emails', payload)
    await request
    await loadSaas()
    resetTemplateForm()
    notice.value = 'E-mail transacional salvo.'
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Nao foi possivel salvar o e-mail transacional.'
  } finally {
    savingTemplate.value = false
  }
}

async function toggleTemplate(template: TransactionalEmail) {
  notice.value = ''
  error.value = ''

  try {
    await api.patch(`/saas/transactional-emails/${template.id}`, {
      is_active: !template.is_active,
    })
    await loadSaas()
    notice.value = template.is_active ? 'Template desativado.' : 'Template ativado.'
  } catch (requestError: any) {
    error.value = requestError.response?.data?.message || 'Nao foi possivel alterar o status do template.'
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
        <form class="panel-main admin-form" @submit.prevent="saveCompany">
          <div class="subsection-heading">
            <h2>{{ editingCompanyId ? 'Editar empresa' : 'Cadastrar empresa teste' }}</h2>
            <button class="btn btn-secondary" type="button" @click="resetCompanyForm">
              <i class="fa-solid fa-plus" aria-hidden="true"></i>
              Nova
            </button>
          </div>

          <div class="form-grid">
            <label>
              Lojista existente
              <select v-model="companyForm.merchant_id" :disabled="Boolean(editingCompanyId)">
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
                <option value="loja_integrada">Loja Integrada</option>
                <option value="magento">Magento</option>
                <option value="opencart">OpenCart</option>
                <option value="custom">Personalizada</option>
              </select>
            </label>
            <label>
              Status da empresa
              <select v-model="companyForm.status">
                <option value="active">Ativa</option>
                <option value="inactive">Inativa</option>
                <option value="trialing">Trial</option>
                <option value="pending_payment">Pagamento pendente</option>
              </select>
            </label>
          </div>

          <div v-if="!editingCompanyId" class="form-grid">
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
              {{ editingCompanyId ? 'Salvar empresa' : 'Criar empresa' }}
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
                  <th>Acoes</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!companies.length">
                  <td colspan="7">Sem empresas.</td>
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
                  <td>
                    <span class="status-pill" :class="{ ok: company.status === 'active', warning: company.status !== 'active' }">
                      {{ company.status === 'active' ? 'Ativa' : company.status }}
                    </span>
                  </td>
                  <td class="row-actions">
                    <button type="button" title="Editar" aria-label="Editar empresa" @click="editCompany(company)">
                      <i class="fa-solid fa-pen" aria-hidden="true"></i>
                    </button>
                    <button type="button" :title="company.status === 'active' ? 'Desativar' : 'Ativar'" @click="toggleCompany(company)">
                      <i :class="company.status === 'active' ? 'fa-solid fa-toggle-on' : 'fa-solid fa-toggle-off'" aria-hidden="true"></i>
                    </button>
                  </td>
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

      <div class="saas-grid email-grid">
        <form class="panel-main admin-form" @submit.prevent="saveEmailSettings">
          <div class="subsection-heading">
            <h2>Credenciais de e-mail</h2>
            <span>{{ emailSettings.is_active ? 'envio ativo' : 'envio inativo' }}</span>
          </div>

          <div class="form-grid">
            <label>
              Mailer
              <select v-model="emailSettings.mailer">
                <option value="smtp">SMTP</option>
              </select>
            </label>
            <label>
              Host SMTP
              <input v-model="emailSettings.host" placeholder="mail.provadorvirtual.online" />
            </label>
            <label>
              Porta
              <input v-model.number="emailSettings.port" type="number" min="1" max="65535" />
            </label>
          </div>

          <div class="form-grid">
            <label>
              Usuario
              <input v-model="emailSettings.username" autocomplete="username" />
            </label>
            <label>
              Senha SMTP
              <input v-model="smtpPassword" type="password" autocomplete="new-password" placeholder="Manter senha atual" />
              <small>{{ emailSettings.has_smtp_password ? 'Senha ja salva no cofre criptografado.' : 'Nenhuma senha salva ainda.' }}</small>
            </label>
            <label>
              Criptografia
              <select v-model="emailSettings.encryption">
                <option value="tls">TLS</option>
                <option value="ssl">SSL</option>
              </select>
            </label>
          </div>

          <div class="form-grid">
            <label>
              E-mail remetente
              <input v-model="emailSettings.from_address" type="email" />
            </label>
            <label>
              Nome remetente
              <input v-model="emailSettings.from_name" />
            </label>
            <label>
              Status
              <select v-model="emailSettings.is_active">
                <option :value="true">Ativo</option>
                <option :value="false">Inativo</option>
              </select>
            </label>
          </div>

          <div class="action-row compact">
            <button class="btn btn-primary" type="submit" :disabled="savingEmailSettings">
              <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
              Salvar credenciais
            </button>
          </div>
        </form>

        <form class="panel-main admin-form" @submit.prevent="saveTemplate">
          <div class="subsection-heading">
            <h2>{{ editingTemplateId ? 'Editar e-mail' : 'Novo e-mail' }}</h2>
            <button class="btn btn-secondary" type="button" @click="resetTemplateForm">
              <i class="fa-solid fa-plus" aria-hidden="true"></i>
              Novo
            </button>
          </div>

          <div class="form-grid">
            <label>
              Codigo
              <input v-model="templateForm.code" placeholder="cadastro_realizado" />
            </label>
            <label>
              Nome
              <input v-model="templateForm.name" required />
            </label>
            <label>
              Status
              <select v-model="templateForm.is_active">
                <option :value="true">Ativo</option>
                <option :value="false">Inativo</option>
              </select>
            </label>
          </div>

          <label>
            Assunto
            <input v-model="templateForm.subject" required />
          </label>
          <label>
            Descricao interna
            <input v-model="templateForm.description" />
          </label>
          <label>
            Variaveis
            <input v-model="templateForm.variables" placeholder="nome, empresa, link_checkout" />
          </label>
          <label>
            Corpo do e-mail
            <textarea v-model="templateForm.body" rows="8" required></textarea>
          </label>

          <div class="action-row compact">
            <button class="btn btn-primary" type="submit" :disabled="savingTemplate">
              <i class="fa-solid fa-envelope-circle-check" aria-hidden="true"></i>
              Salvar e-mail
            </button>
          </div>
        </form>
      </div>

      <section class="panel-main">
        <div class="subsection-heading">
          <h2>E-mails transacionais</h2>
          <span>{{ transactionalEmails.length }} templates</span>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Codigo</th>
                <th>Nome</th>
                <th>Assunto</th>
                <th>Status</th>
                <th>Acoes</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!transactionalEmails.length">
                <td colspan="5">Sem e-mails transacionais.</td>
              </tr>
              <tr v-for="template in transactionalEmails" :key="template.id">
                <td><strong>{{ template.code }}</strong></td>
                <td>
                  <strong>{{ template.name }}</strong>
                  <small>{{ template.description || 'sem descricao' }}</small>
                </td>
                <td>{{ template.subject }}</td>
                <td>
                  <span class="status-pill" :class="{ ok: template.is_active, warning: !template.is_active }">
                    {{ template.is_active ? 'Ativo' : 'Inativo' }}
                  </span>
                </td>
                <td class="row-actions">
                  <button type="button" title="Editar" @click="editTemplate(template)">
                    <i class="fa-solid fa-pen" aria-hidden="true"></i>
                  </button>
                  <button type="button" :title="template.is_active ? 'Desativar' : 'Ativar'" @click="toggleTemplate(template)">
                    <i :class="template.is_active ? 'fa-solid fa-toggle-on' : 'fa-solid fa-toggle-off'" aria-hidden="true"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="panel-main">
        <div class="subsection-heading">
          <h2>Historico de envios</h2>
          <span>{{ emailSends.length }} recentes</span>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Template</th>
                <th>Empresa</th>
                <th>Destinatario</th>
                <th>Status</th>
                <th>Data</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!emailSends.length">
                <td colspan="5">Nenhum envio registrado.</td>
              </tr>
              <tr v-for="send in emailSends" :key="send.id">
                <td>
                  <strong>{{ send.template_name || send.code }}</strong>
                  <small>{{ send.subject || 'sem assunto' }}</small>
                </td>
                <td>{{ send.company_name || '-' }}</td>
                <td>
                  <strong>{{ send.recipient_name || '-' }}</strong>
                  <small>{{ send.recipient_email || '-' }}</small>
                </td>
                <td>
                  <span class="status-pill" :class="{ ok: send.status === 'sent', warning: send.status === 'skipped' }">
                    {{ send.status }}
                  </span>
                  <small v-if="send.error">{{ send.error }}</small>
                </td>
                <td>{{ send.sent_at || send.created_at || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </section>
</template>
