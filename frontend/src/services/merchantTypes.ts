export type MeasurementTableOption = {
  id: number
  name: string
}

export type ProductVariant = {
  id: number
  sku: string | null
  size_label: string
  color: string | null
  price: string | number | null
  stock_quantity: number | null
  is_active: boolean
}

export type FitProfile = {
  id: number
  merchant_company_id: number | null
  name: string
  code: string
  description: string | null
  product_type: string | null
  gender: 'female' | 'male' | 'unisex' | 'kids' | null
  fit_intensity: 'very_slim' | 'slim' | 'regular' | 'relaxed' | 'oversized' | 'custom'
  stretch_level: 'none' | 'low' | 'medium' | 'high'
  status: 'active' | 'draft' | 'inactive'
  products_count: number
  measurement_tables_count: number
  metadata?: Record<string, unknown>
  guidance?: {
    rules_context?: Record<string, unknown>
    ai_context?: Record<string, unknown>
    recommendation_impact?: {
      summary?: string
      confidence_hint?: string
    }
  }
}

export type FitProfileSuggestion = {
  id?: number
  mode: 'existing' | 'create'
  name: string
  code: string
  product_type?: string | null
  gender?: 'female' | 'male' | 'unisex' | 'kids' | null
  fit_intensity?: FitProfile['fit_intensity']
  stretch_level?: FitProfile['stretch_level']
  status?: FitProfile['status']
  confidence?: 'high' | 'medium' | 'low'
  reasons?: string[]
  profile?: {
    name: string
    code: string
    description?: string | null
    product_type?: string | null
    gender?: 'female' | 'male' | 'unisex' | 'kids' | null
    fit_intensity?: FitProfile['fit_intensity']
    stretch_level?: FitProfile['stretch_level']
  }
}

export type FitProfileDiagnosticProduct = {
  id: number
  name: string
  sku?: string | null
  category?: string | null
  brand?: string | null
  gender?: string | null
  age_group?: string | null
  fit_profile?: string | null
  sizes?: string[]
}

export type FitProfileDiagnosticIssue = {
  code: string
  severity: 'warning' | 'danger'
  title: string
  cause: string
  action: string
  product: FitProfileDiagnosticProduct
  suggested_profile: FitProfileSuggestion
}

export type FitProfileDiagnosticGroup = {
  key: string
  code: string
  severity: 'warning' | 'danger'
  title: string
  cause: string
  action: string
  suggested_profile: FitProfileSuggestion
  products_count: number
  product_ids: number[]
  sample_products: FitProfileDiagnosticProduct[]
  category?: string | null
  brand?: string | null
  gender?: string | null
  age_group?: string | null
}

export type FitProfileDiagnostics = {
  summary: {
    products_analyzed: number
    issues: number
    without_modeling: number
    modeling_not_found: number
    modeling_inactive: number
    modeling_incompatible: number
    groups: number
  }
  groups: FitProfileDiagnosticGroup[]
  issues: FitProfileDiagnosticIssue[]
}

export type NormalizedBrand = {
  id: number
  name: string
  slug: string
  status: string
  metadata?: Record<string, unknown>
}

export type MerchantBrandSuggestion = {
  mode: 'mapped' | 'existing' | 'create'
  normalized_brand_id?: number | null
  normalized_name: string
  confidence: 'high' | 'medium' | 'low'
  reasons: string[]
}

export type MerchantBrand = {
  id: number
  merchant_company_id: number | null
  normalized_brand_id: number | null
  name: string
  slug: string
  source: string
  status: string
  product_count: number
  normalized_product_count: number
  aliases: string[]
  suggestion: MerchantBrandSuggestion | null
  metadata?: Record<string, unknown>
  normalized_brand?: NormalizedBrand | null
}

export type TaxonomyCategory = {
  id: number
  parent_id: number | null
  name: string
  slug: string
  category_type: string
  gender?: string | null
  age_group?: string | null
  translations?: Record<string, string>
  status: string
  metadata?: Record<string, unknown>
  parent?: {
    id?: number | null
    name?: string | null
    slug?: string | null
    category_type?: string | null
  } | null
  children?: TaxonomyCategory[]
}

export type MerchantCategorySuggestion = {
  mode: 'mapped' | 'existing' | 'create'
  taxonomy_category_id?: number | null
  taxonomy_name: string
  category_type: string
  confidence: 'high' | 'medium' | 'low'
  reasons: string[]
}

export type MerchantCategory = {
  id: number
  merchant_company_id: number | null
  taxonomy_category_id: number | null
  name: string
  slug: string
  source: string
  status: string
  product_count: number
  normalized_product_count: number
  aliases: string[]
  suggestion: MerchantCategorySuggestion | null
  metadata?: Record<string, unknown>
  taxonomy_category?: TaxonomyCategory | null
}

export type TaxonomyVersion = {
  id: number
  version: string
  label: string
  status: string
  summary?: Record<string, unknown>
  metadata?: Record<string, unknown>
  published_at?: string | null
}

export type TaxonomyMappingSuggestion = {
  id: number
  merchant_company_id: number | null
  taxonomy_version_id: number | null
  suggestion_type: 'category' | 'brand'
  source: string
  original_value: string
  suggested_target_type: 'taxonomy_category' | 'normalized_brand' | 'proposed_category' | 'proposed_brand'
  suggested_name: string | null
  confidence_score: number
  confidence_level: 'high' | 'medium' | 'low'
  status: 'pending' | 'approved' | 'applied' | 'rejected'
  review_required: boolean
  can_auto_apply: boolean
  reasons: string[]
  impact: {
    products_count?: number
    affected_product_ids?: number[]
    uses?: string[]
    can_auto_apply?: boolean
    requires_confirmation?: boolean
    critical_fields?: string[]
    applied_products?: number
    approved_target_name?: string
  }
  context: {
    mapping_type?: string
    local_value?: string
    suggested_value?: string
    category_type?: string
    mode?: string
    signals?: {
      gender?: string | null
      age_group?: string | null
      fit_profile?: string | null
      size_system?: string | null
      sources?: string[]
    }
    sample?: {
      products_count?: number
      product_ids?: number[]
    }
  }
  version?: TaxonomyVersion | null
  merchant_category?: MerchantCategory | null
  merchant_brand?: MerchantBrand | null
  taxonomy_category?: TaxonomyCategory | null
  normalized_brand?: NormalizedBrand | null
  reviewed_at?: string | null
  applied_at?: string | null
  created_at?: string | null
  updated_at?: string | null
}

export type Product = {
  id: number
  measurement_table_id: number | null
  external_product_id?: string | null
  description?: string | null
  name: string
  sku: string | null
  category: string | null
  normalized_category?: {
    id?: number | null
    name: string
    slug?: string | null
    type?: string | null
    parent_id?: number | null
    parent_name?: string | null
    gender?: string | null
    age_group?: string | null
    original_name?: string | null
    merchant_category_id?: number | null
    source?: string | null
    applied_at?: string | null
  } | null
  gender: string | null
  fit_profile: string | null
  brand?: string | null
  normalized_brand?: {
    id?: number | null
    name: string
    slug?: string | null
    original_name?: string | null
    merchant_brand_id?: number | null
    source?: string | null
    applied_at?: string | null
  } | null
  age_group?: string | null
  data_source?: string
  source_label?: string
  status: string
  image_url?: string | null
  activation?: {
    virtual_try_on_enabled: boolean
    measurement_table_enabled: boolean
    updated_at?: string | null
    virtual_try_on_updated_at?: string | null
    measurement_table_updated_at?: string | null
  }
  has_sync_error?: boolean
  readiness_status?: 'ready' | 'pending'
  readiness_issues?: string[]
  diagnostics?: Array<{
    severity: 'ok' | 'info' | 'warning' | 'danger'
    code: string
    title: string
    cause: string
    action: string
  }>
  size_labels?: string[]
  variants_count?: number
  variants?: ProductVariant[]
  measurement_table?: MeasurementTableOption | null
  origin_fields?: Array<{
    field: string
    label: string
    value: string | number | boolean | null
    imported_value?: string | number | boolean | null
    source: string
    source_label: string
    manual_override?: {
      value?: string | number | boolean | null
      imported_value?: string | number | boolean | null
      source?: string
      updated_at?: string | null
    }
  }>
  imported_snapshot?: Record<string, string | number | boolean | null>
  manual_overrides?: Record<string, {
    value?: string | number | boolean | null
    imported_value?: string | number | boolean | null
    source?: string
    updated_at?: string | null
  }>
  history?: Array<{
    event: string
    category?: string
    severity?: string
    source?: string
    details?: Record<string, unknown>
    created_at?: string | null
  }>
}

export type MeasurementRow = {
  id?: number
  size_label: string
  note?: string | null
  size_note?: string | null
  sort_order?: number
  bust_min?: number | null
  bust_max?: number | null
  waist_min?: number | null
  waist_max?: number | null
  hip_min?: number | null
  hip_max?: number | null
  height_min?: number | null
  height_max?: number | null
  weight_min?: number | null
  weight_max?: number | null
  length_min?: number | null
  length_max?: number | null
  shoulder_min?: number | null
  shoulder_max?: number | null
  composite_min?: number | null
  composite_max?: number | null
  measurements?: Record<string, { label?: string; min?: number | null; max?: number | null; value?: number | null }>
  composite_measurements?: Record<string, { label?: string; formula?: string; min?: number | null; max?: number | null; value?: number | null }>
  measurement_notes?: Record<string, string | null>
  metadata?: Record<string, unknown>
}

export type MeasurementCustomVariation = {
  field: 'bust' | 'waist' | 'hip' | 'height' | 'weight' | 'length' | 'shoulder' | 'composite'
  mode: 'restricted' | 'wide'
  min?: number | null
  max?: number | null
  note?: string | null
}

export type MeasurementTable = {
  id: number
  name: string
  product_type: string
  gender: string | null
  fit_profile: string | null
  measurement_target: 'body' | 'garment' | 'mixed'
  size_system: 'br_alpha' | 'br_numeric' | 'international' | 'custom'
  range_mode: 'min_max' | 'exact' | 'tolerance'
  status: string
  source: string
  notes: string | null
  activation?: {
    virtual_try_on_enabled: boolean
    virtual_try_on_updated_at?: string | null
  }
  custom_variations?: MeasurementCustomVariation[]
  metadata?: Record<string, unknown>
  rows_count?: number
  products_count?: number
  rows?: MeasurementRow[]
}

export type MeasurementTemplate = {
  key: string
  name: string
  product_type: string
  product_type_label?: string
  gender: string
  gender_label?: string
  fit_profile: string
  source?: string
  source_label?: string
  market_basis?: string
  fields?: string[]
  rows: MeasurementRow[]
}

export type Permission = { view: boolean; edit: boolean }
export type PermissionMap = Record<string, Permission>
export type Module = { key: string; label: string; description: string }

export type PortalUser = {
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
