export interface ServiceLocation {
  id: number
  name: string
  type: string
  address?: string
  latitude?: number
  longitude?: number
}

export interface VendorClusterLocation {
  id: number
  route_stop_id: number
  vendor_location_id: number
  vendor_location?: ServiceLocation
  location_order: number
  is_optional: boolean
}

export interface RouteStop {
  id: number
  route_id: number
  stop_type: 'SHOP' | 'VENDOR_CLUSTER' | 'CUSTOMER' | 'AD_HOC'
  location_id: number | null
  location?: ServiceLocation
  stop_order: number
  estimated_duration_minutes: number
  notes?: string
  vendor_cluster_locations?: VendorClusterLocation[]
  created_at: string
  updated_at: string
}

export interface RouteSchedule {
  id: number
  route_id: number
  scheduled_time: string
  name?: string | null
  is_active: boolean
  days_of_week?: number[]
  created_at: string
  updated_at: string
}

export interface Route {
  id: number
  name: string
  code: string
  description?: string
  start_location_id: number
  start_location?: ServiceLocation
  is_active: boolean
  stops?: RouteStop[]
  schedules?: RouteSchedule[]
  created_at: string
  updated_at: string
  created_by: number
  updated_by: number
}

export interface PathResult {
  path: Array<{
    location_id: number
    location_name: string
    route_id: number | null
  }>
  routes: number[]
  hops: number
}
