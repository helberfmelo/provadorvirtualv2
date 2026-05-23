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

export type Product = {
  id: number
  measurement_table_id: number | null
  name: string
  sku: string | null
  category: string | null
  gender: string | null
  fit_profile: string | null
  status: string
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
}

export type MeasurementTable = {
  id: number
  name: string
  product_type: string
  gender: string | null
  fit_profile: string | null
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
  gender: string
  fit_profile: string
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
