export type Summary = Record<string, number>

export type MerchantRow = {
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

export type CompanyRow = {
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

export type EmailSettings = {
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

export type TransactionalEmail = {
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

export type TransactionalEmailSend = {
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

export type CheckoutProviderOption = {
  key: string
  label: string
  configured: boolean
  credit_card_enabled: boolean
  payment_methods: string[]
}

export type CheckoutSettings = {
  payment_provider: string
  active_provider_configured: boolean
  providers: CheckoutProviderOption[]
}

export type Permission = { view: boolean; edit: boolean }
export type PermissionMap = Record<string, Permission>
export type Module = { key: string; label: string; description: string }
export type MerchantOption = { id: number; name: string; slug: string }
export type CompanyOption = {
  id: number
  access_code: string
  name: string
  document: string | null
  platform: string
  status: string
  merchant: {
    id: number
    name: string
    slug: string
  }
}

export type SaasUser = {
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
      merchant_company_id?: number | null
      company?: CompanyOption | null
      permissions: PermissionMap
    }
  }>
}

export function normalizeEmailSettings(data: Partial<EmailSettings>): EmailSettings {
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

export function emptyPermissions(list: Module[]) {
  return Object.fromEntries(
    list.map((module) => [module.key, { view: false, edit: false }]),
  ) as PermissionMap
}

export function normalizePermissions(source: PermissionMap | undefined, list: Module[]) {
  return Object.fromEntries(
    list.map((module) => {
      const edit = Boolean(source?.[module.key]?.edit)
      return [module.key, { view: edit || Boolean(source?.[module.key]?.view), edit }]
    }),
  ) as PermissionMap
}
