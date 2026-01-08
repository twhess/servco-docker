import { defineStore } from 'pinia'
import { api } from 'src/boot/axios'
import { Notify } from 'quasar'

export interface Item {
  id: number
  name: string
  description: string
  qr_code: string
  status: string
  current_location_id: number
  current_location?: any
  type?: any
  category?: any
  created_at: string
  updated_at: string
}

export interface ItemMovement {
  id: number
  item_id: number
  from_location_id: number
  from_location?: any
  to_location_id: number
  to_location?: any
  moved_by_user_id: number
  moved_by?: any
  moved_at: string
  movement_type: string
  reference_number?: string
  notes?: string
  parts_request_id?: number
  parts_request?: any
  created_at: string
  updated_at: string
}

interface ItemsState {
  items: Item[]
  activeItem: Item | null
  movementHistory: ItemMovement[]
  loading: boolean
  error: string | null
}

export const useItemsStore = defineStore('items', {
  state: (): ItemsState => ({
    items: [],
    activeItem: null,
    movementHistory: [],
    loading: false,
    error: null,
  }),

  getters: {
    getItemById: (state) => (id: number) => {
      return state.items.find(i => i.id === id)
    },

    itemsByLocation: (state) => (locationId: number) => {
      return state.items.filter(i => i.current_location_id === locationId)
    },
  },

  actions: {
    async scanQrCode(qrCode: string): Promise<Item | null> {
      this.loading = true
      this.error = null
      try {
        const response = await api.get(`/items/scan/${qrCode}`)
        const item = response.data.data
        this.activeItem = item

        // Add to items list if not already present
        if (!this.items.find(i => i.id === item.id)) {
          this.items.push(item)
        }

        return item
      } catch (error: any) {
        if (error.response?.status === 404) {
          Notify.create({
            type: 'warning',
            message: 'Item not found',
          })
          return null
        }
        this.error = error.response?.data?.message || 'Failed to scan QR code'
        Notify.create({
          type: 'negative',
          message: this.error || undefined,
        })
        throw error
      } finally {
        this.loading = false
      }
    },

    async fetchItem(id: number) {
      this.loading = true
      this.error = null
      try {
        // Note: There's no dedicated /items/{id} endpoint yet
        // For now, we'll scan by ID (assuming QR code = ID for simplicity)
        // In production, you'd want a proper GET /items/{id} endpoint
        const response = await api.get(`/items/${id}`)
        const item = response.data.data
        this.activeItem = item

        const index = this.items.findIndex(i => i.id === id)
        if (index !== -1) {
          this.items[index] = item
        } else {
          this.items.push(item)
        }

        return item
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to fetch item'
        Notify.create({
          type: 'negative',
          message: this.error || undefined,
        })
        throw error
      } finally {
        this.loading = false
      }
    },

    async fetchMovementHistory(itemId: number): Promise<ItemMovement[]> {
      this.loading = true
      this.error = null
      try {
        const response = await api.get(`/items/${itemId}/movement-history`)
        this.movementHistory = response.data.data
        return this.movementHistory
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to fetch movement history'
        Notify.create({
          type: 'negative',
          message: this.error || undefined,
        })
        throw error
      } finally {
        this.loading = false
      }
    },

    async fetchCurrentRequest(itemId: number) {
      this.loading = true
      this.error = null
      try {
        const response = await api.get(`/items/${itemId}/current-request`)
        return response.data.data
      } catch (error: any) {
        // Don't show error notification if no request found (expected case)
        if (error.response?.status !== 404) {
          this.error = error.response?.data?.message || 'Failed to fetch current request'
          Notify.create({
            type: 'negative',
            message: this.error || undefined,
          })
        }
        return null
      } finally {
        this.loading = false
      }
    },

    clearError() {
      this.error = null
    },

    clearActiveItem() {
      this.activeItem = null
      this.movementHistory = []
    },
  },
})
