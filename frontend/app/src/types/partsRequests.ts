import type { Vendor, Address } from './vendors'

export interface PartsRequest {
  id: number
  reference_number: string
  request_type: {
    id: number
    name: string
  }
  status: {
    id: number
    name: string
  }
  urgency: {
    id: number
    name: string
  }
  vendor_name?: string | null
  vendor_id?: number | null
  vendor?: Vendor | null
  vendor_address_id?: number | null
  vendor_address?: Address | null
  customer_name?: string | null
  customer_address?: string | null
  vendor_order_number?: string | null
  origin_location_id?: number | null
  origin_location?: {
    id: number
    name: string
  }
  receiving_location_id?: number | null
  receiving_location?: {
    id: number
    name: string
  }
  details?: string | null
  special_instructions?: string | null
  not_before_datetime?: string | null
  scheduled_for_date?: string | null
  run_instance_id?: number | null
  run_instance?: {
    id: number
    display_name?: string
    scheduled_date: string
    scheduled_time: string
    route?: {
      id: number
      name: string
      code: string
    }
    schedule?: {
      id: number
      name: string | null
      scheduled_time: string
    }
  }
  pickup_stop_id?: number | null
  pickup_stop?: any
  dropoff_stop_id?: number | null
  dropoff_stop?: any
  parent_request_id?: number | null
  segment_order?: number | null
  is_segment?: boolean
  override_run_instance_id?: number | null
  override_reason?: string | null
  item_id?: number | null
  requested_by?: {
    id: number
    name: string
  }
  requested_at?: string
  assigned_runner?: {
    id: number
    name: string
  } | null
  created_by?: {
    id: number
    name: string
  }
  created_at: string
  updated_at: string
  archived_at?: string | null
}
