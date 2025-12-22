import type { Route, RouteStop } from './routes'
import type { PartsRequest } from './partsRequests'

export interface User {
  id: number
  name: string
  email: string
}

export interface RunStopActual {
  id: number
  run_instance_id: number
  route_stop_id: number
  route_stop?: RouteStop
  arrived_at: string | null
  departed_at: string | null
  tasks_completed: number
  tasks_total: number
  notes?: string
  created_at: string
  updated_at: string
}

export interface RunNote {
  id: number
  run_instance_id: number
  note_type: 'general' | 'delay' | 'issue' | 'completion'
  notes: string
  created_by_user_id: number
  created_by?: User
  created_at: string
  updated_at: string
}

export interface RunInstance {
  id: number
  route_id: number
  route?: Route
  scheduled_date: string
  scheduled_time: string
  assigned_runner_user_id: number | null
  assigned_runner?: User
  assigned_vehicle_location_id: number | null
  assigned_vehicle?: any
  status: 'pending' | 'in_progress' | 'completed' | 'canceled'
  actual_start_at: string | null
  actual_end_at: string | null
  current_stop_id: number | null
  current_stop?: RouteStop
  requests?: PartsRequest[]
  stop_actuals?: RunStopActual[]
  notes?: RunNote[]
  created_at: string
  updated_at: string
}
