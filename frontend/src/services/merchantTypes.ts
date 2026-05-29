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
}

export type Product = {
  id: number
  measurement_table_id: number | null
  name: string
  sku: string | null
  category: string | null
  gender: string | null
  fit_profile: string | null
  status: string
  has_sync_error?: boolean
  readiness_status?: 'ready' | 'pending'
  readiness_issues?: string[]
  variants_count?: number
  variants?: ProductVariant[]
  measurement_table?: MeasurementTableOption | null
}

export type MeasurementRow = {
  id?: number
  size_label: string
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
