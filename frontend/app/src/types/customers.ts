import type { Address } from './vendors'

export interface Customer {
  id: number
  fb_id?: string | null
  external_id?: string | null
  source: 'manual' | 'import'
  company_name: string
  formatted_name: string
  detail?: string | null
  normalized_name: string
  phone?: string | null
  phone_secondary?: string | null
  fax?: string | null
  email?: string | null
  dot_number?: string | null
  customer_group?: string | null
  assigned_shop?: string | null
  associated_shops?: string[] | null
  sales_rep?: string | null
  credit_terms?: string | null
  credit_limit?: number | null
  tax_location?: string | null
  price_level?: string | null
  is_taxable: boolean
  tax_exempt_number?: string | null
  discount?: string | null
  default_labor_rate?: string | null
  po_required_create_so: boolean
  po_required_create_invoice: boolean
  blanket_po_number?: string | null
  portal_enabled: boolean
  portal_code?: string | null
  portal_can_see_invoices: boolean
  portal_can_pay_invoices: boolean
  settings?: Record<string, any> | null
  is_active: boolean
  notes?: string | null
  external_created_at?: string | null
  addresses?: Address[]
  created_at: string
  updated_at: string
}

export interface CustomerSearchResult {
  id: number
  company_name: string
  formatted_name: string
  detail?: string | null
  dot_number?: string | null
  phone?: string | null
  is_active: boolean
  addresses?: Address[]
}

export interface CustomerDuplicateCandidate {
  id: number
  company_name: string
  formatted_name: string
  dot_number?: string | null
  phone?: string | null
  score: number
  reasons: {
    name: boolean
    name_fuzzy: boolean
    dot: boolean
    phone: boolean
    address: boolean
  }
}

export interface CustomerCreateRequest {
  company_name: string
  formatted_name?: string
  detail?: string
  phone?: string
  phone_secondary?: string
  fax?: string
  email?: string
  dot_number?: string
  customer_group?: string
  assigned_shop?: string
  sales_rep?: string
  credit_terms?: string
  credit_limit?: number
  tax_location?: string
  price_level?: string
  is_taxable?: boolean
  tax_exempt_number?: string
  is_active?: boolean
  notes?: string
  force_create?: boolean
}

export interface CustomerImport {
  id: number
  uploaded_by: number
  file_path: string
  original_filename: string
  status: 'pending' | 'processing' | 'completed' | 'failed'
  total_rows: number
  created_count: number
  updated_count: number
  skipped_count: number
  merge_needed_count: number
  error_count: number
  started_at?: string | null
  finished_at?: string | null
  summary?: Record<string, any> | null
  error_message?: string | null
  uploader?: {
    id: number
    name: string
  }
  created_at: string
  updated_at: string
}

export interface CustomerImportRow {
  id: number
  customer_import_id: number
  row_number: number
  fb_id?: string | null
  raw_data: Record<string, any>
  action: 'created' | 'updated' | 'skipped' | 'merge_needed' | 'error'
  customer_id?: number | null
  message?: string | null
  customer?: Customer
  created_at: string
}

export interface CustomerMergeCandidate {
  id: number
  customer_import_id: number
  import_row_id: number
  matched_customer_id: number
  incoming_data: Record<string, any>
  match_score: number
  match_reasons: {
    name: boolean
    name_fuzzy: boolean
    dot: boolean
    phone: boolean
    address: boolean
  }
  status: 'pending' | 'merged' | 'created_new' | 'skipped'
  resolved_by?: number | null
  resolved_at?: string | null
  resolution_details?: Record<string, any> | null
  matched_customer?: Customer
  import_row?: CustomerImportRow
  import?: CustomerImport
  resolver?: {
    id: number
    name: string
  }
  created_at: string
  updated_at: string
}

export interface MergeComparisonData {
  existing: Record<string, any>
  incoming: Record<string, any>
  differences: string[]
  match_score: number
  match_reasons: {
    name: boolean
    name_fuzzy: boolean
    dot: boolean
    phone: boolean
    address: boolean
  }
}

export interface MergeSummary {
  pending: number
  merged: number
  created_new: number
  skipped: number
  total: number
}
