import { defineStore } from 'pinia'
import { api } from 'src/boot/axios'
import type { Route, RouteStop, RouteSchedule, PathResult } from 'src/types/routes'

interface RouteStopFormData {
  stop_type: 'SHOP' | 'VENDOR_CLUSTER' | 'CUSTOMER' | 'AD_HOC'
  location_id: number | null
  stop_order: number
  estimated_duration_minutes: number
  notes?: string
  vendor_locations?: Array<{
    vendor_location_id: number
    location_order: number
    is_optional: boolean
  }>
}

interface RouteFormData {
  name: string
  code: string
  description?: string
  start_location_id: number
  is_active: boolean
}

interface RoutesState {
  routes: Route[]
  activeRoute: Route | null
  loading: boolean
  error: string | null
}

export const useRoutesStore = defineStore('routes', {
  state: (): RoutesState => ({
    routes: [],
    activeRoute: null,
    loading: false,
    error: null,
  }),

  getters: {
    activeRoutes: (state) => state.routes.filter(r => r.is_active),
    inactiveRoutes: (state) => state.routes.filter(r => !r.is_active),

    getRouteById: (state) => (id: number) => {
      return state.routes.find(r => r.id === id)
    },

    routesByLocation: (state) => (locationId: number) => {
      return state.routes.filter(r =>
        r.stops?.some(s =>
          s.location_id === locationId ||
          s.vendor_cluster_locations?.some(v => v.vendor_location_id === locationId)
        )
      )
    },
  },

  actions: {
    async fetchRoutes(filters?: { active?: boolean }) {
      this.loading = true
      this.error = null
      try {
        const params = filters ? { active: filters.active } : {}
        const response = await api.get('/routes', { params })
        this.routes = response.data.data
        return this.routes
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to fetch routes'
        throw error
      } finally {
        this.loading = false
      }
    },

    async fetchRoute(id: number) {
      this.loading = true
      this.error = null
      try {
        const response = await api.get(`/routes/${id}`)
        const route: Route = response.data.data
        this.activeRoute = route

        // Update in routes list if exists
        const index = this.routes.findIndex(r => r.id === id)
        if (index !== -1) {
          this.routes[index] = route
        }

        return route
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to fetch route'
        throw error
      } finally {
        this.loading = false
      }
    },

    async createRoute(data: RouteFormData) {
      this.loading = true
      this.error = null
      try {
        const response = await api.post('/routes', data)
        const newRoute = response.data.data
        this.routes.push(newRoute)
        return newRoute
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to create route'
        throw error
      } finally {
        this.loading = false
      }
    },

    async updateRoute(id: number, data: Partial<RouteFormData>) {
      this.loading = true
      this.error = null
      try {
        const response = await api.put(`/routes/${id}`, data)
        const updatedRoute = response.data.data

        const index = this.routes.findIndex(r => r.id === id)
        if (index !== -1) {
          this.routes[index] = updatedRoute
        }

        if (this.activeRoute?.id === id) {
          this.activeRoute = updatedRoute
        }

        return updatedRoute
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to update route'
        throw error
      } finally {
        this.loading = false
      }
    },

    async deleteRoute(id: number) {
      this.loading = true
      this.error = null
      try {
        await api.delete(`/routes/${id}`)

        // Mark as inactive in local state
        const routeToDelete = this.routes.find(r => r.id === id)
        if (routeToDelete) {
          routeToDelete.is_active = false
        }

        if (this.activeRoute?.id === id) {
          this.activeRoute.is_active = false
        }
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to delete route'
        throw error
      } finally {
        this.loading = false
      }
    },

    async activateRoute(id: number) {
      this.loading = true
      this.error = null
      try {
        const response = await api.post(`/routes/${id}/activate`)
        const activatedRoute = response.data.data

        const index = this.routes.findIndex(r => r.id === id)
        if (index !== -1) {
          this.routes[index] = activatedRoute
        }

        if (this.activeRoute?.id === id) {
          this.activeRoute = activatedRoute
        }

        return activatedRoute
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to activate route'
        throw error
      } finally {
        this.loading = false
      }
    },

    async addStop(routeId: number, stopData: RouteStopFormData) {
      this.loading = true
      this.error = null
      try {
        const response = await api.post(`/routes/${routeId}/stops`, stopData)
        const newStop = response.data.data

        // Refresh active route to get updated stops
        if (this.activeRoute?.id === routeId) {
          await this.fetchRoute(routeId)
        }

        return newStop
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to add stop'
        throw error
      } finally {
        this.loading = false
      }
    },

    async updateStop(routeId: number, stopId: number, data: Partial<RouteStopFormData>) {
      this.loading = true
      this.error = null
      try {
        const response = await api.put(`/routes/${routeId}/stops/${stopId}`, data)
        const updatedStop = response.data.data

        // Refresh active route
        if (this.activeRoute?.id === routeId) {
          await this.fetchRoute(routeId)
        }

        return updatedStop
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to update stop'
        throw error
      } finally {
        this.loading = false
      }
    },

    async removeStop(routeId: number, stopId: number) {
      this.loading = true
      this.error = null
      try {
        await api.delete(`/routes/${routeId}/stops/${stopId}`)

        // Refresh active route
        if (this.activeRoute?.id === routeId) {
          await this.fetchRoute(routeId)
        }
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to remove stop'
        throw error
      } finally {
        this.loading = false
      }
    },

    async reorderStops(routeId: number, stopsOrder: Array<{ id: number; order: number }>) {
      this.loading = true
      this.error = null
      try {
        await api.post(`/routes/${routeId}/stops/reorder`, { stops: stopsOrder })

        // Refresh active route
        if (this.activeRoute?.id === routeId) {
          await this.fetchRoute(routeId)
        }
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to reorder stops'
        throw error
      } finally {
        this.loading = false
      }
    },

    async addSchedule(routeId: number, time: string, name?: string, daysOfWeek?: number[]) {
      this.loading = true
      this.error = null
      try {
        const response = await api.post(`/routes/${routeId}/schedules`, {
          scheduled_time: time,
          name: name || null,
          is_active: true,
          days_of_week: daysOfWeek || [1, 2, 3, 4, 5],
        })
        const newSchedule = response.data.data

        // Refresh active route
        if (this.activeRoute?.id === routeId) {
          await this.fetchRoute(routeId)
        }

        return newSchedule
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to add schedule'
        throw error
      } finally {
        this.loading = false
      }
    },

    async updateSchedule(routeId: number, scheduleId: number, data: { scheduled_time?: string; name?: string; is_active?: boolean; days_of_week?: number[] }) {
      this.loading = true
      this.error = null
      try {
        const response = await api.put(`/routes/${routeId}/schedules/${scheduleId}`, data)
        const updatedSchedule = response.data.data

        // Refresh active route
        if (this.activeRoute?.id === routeId) {
          await this.fetchRoute(routeId)
        }

        return updatedSchedule
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to update schedule'
        throw error
      } finally {
        this.loading = false
      }
    },

    async removeSchedule(routeId: number, scheduleId: number) {
      this.loading = true
      this.error = null
      try {
        await api.delete(`/routes/${routeId}/schedules/${scheduleId}`)

        // Refresh active route
        if (this.activeRoute?.id === routeId) {
          await this.fetchRoute(routeId)
        }
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to remove schedule'
        throw error
      } finally {
        this.loading = false
      }
    },

    async findPath(fromLocationId: number, toLocationId: number): Promise<PathResult | null> {
      this.loading = true
      this.error = null
      try {
        const response = await api.get('/routes/path', {
          params: {
            from: fromLocationId,
            to: toLocationId,
          },
        })
        return response.data.data
      } catch (error: any) {
        if (error.response?.status === 404) {
          // No path found
          return null
        }
        this.error = error.response?.data?.message || 'Failed to find path'
        throw error
      } finally {
        this.loading = false
      }
    },

    async rebuildCache() {
      this.loading = true
      this.error = null
      try {
        await api.post('/routes/rebuild-cache')
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to rebuild cache'
        throw error
      } finally {
        this.loading = false
      }
    },

    clearError() {
      this.error = null
    },

    clearActiveRoute() {
      this.activeRoute = null
    },
  },
})
