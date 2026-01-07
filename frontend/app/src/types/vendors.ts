export interface Address {
  id: number
  label?: string | null
  company_name?: string | null
  attention?: string | null
  line1: string
  line2?: string | null
  city: string
  state: string
  postal_code: string
  country: string
  phone?: string | null
  email?: string | null
  instructions?: string | null
  lat?: number | null
  lng?: number | null
  is_validated: boolean
  full_address: string
  one_line_address: string
  created_at: string
  updated_at: string
  // Pivot data when attached to vendor/customer
  pivot?: {
    address_type: 'pickup' | 'billing' | 'shipping' | 'physical' | 'other'
    is_primary: boolean
    sort_order: number
  }
}

export interface Vendor {
  id: number
  name: string
  legal_name?: string | null
  normalized_name: string
  is_acronym: boolean
  phone?: string | null
  email?: string | null
  notes?: string | null
  status: 'active' | 'inactive'
  addresses?: Address[]
  created_at: string
  updated_at: string
}

export interface VendorSearchResult {
  id: number
  name: string
  normalized_name: string
  phone?: string | null
  email?: string | null
  addresses?: Address[]
}

export interface VendorDuplicateCandidate {
  id: number
  name: string
  normalized_name: string
  similarity: number
  phone?: string | null
  email?: string | null
}

export interface VendorCreateRequest {
  name: string
  legal_name?: string | null
  phone?: string | null
  email?: string | null
  notes?: string | null
  status?: 'active' | 'inactive'
  is_acronym?: boolean
  force_create?: boolean
}

export interface AcronymDetectionResult {
  isLikely: boolean
  reason: string
  suggestedName: string
}

export interface AddressCreateRequest {
  label?: string | null
  company_name?: string | null
  attention?: string | null
  line1: string
  line2?: string | null
  city: string
  state: string
  postal_code: string
  country?: string
  phone?: string | null
  email?: string | null
  instructions?: string | null
  lat?: number | null
  lng?: number | null
}

export interface VendorAddressAttachRequest {
  address: AddressCreateRequest
  address_type?: 'pickup' | 'billing' | 'shipping' | 'physical' | 'other'
  is_primary?: boolean
}
