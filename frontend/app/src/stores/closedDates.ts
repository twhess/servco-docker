import { defineStore } from 'pinia'
import { api } from 'src/boot/axios'

export interface ClosedDate {
  id: number
  date: string
  name: string
  notes?: string | null
  created_by_user?: {
    id: number
    name: string
  }
  updated_by_user?: {
    id: number
    name: string
  }
  created_at: string
  updated_at: string
}

interface ClosedDatesState {
  closedDates: ClosedDate[]
  loading: boolean
  error: string | null
}

export const useClosedDatesStore = defineStore('closedDates', {
  state: (): ClosedDatesState => ({
    closedDates: [],
    loading: false,
    error: null,
  }),

  getters: {
    upcomingDates: (state) => {
      const today = new Date().toISOString().split('T')[0] ?? ''
      return state.closedDates
        .filter((d) => d.date >= today)
        .sort((a, b) => a.date.localeCompare(b.date))
    },

    getByDate: (state) => (date: string) => {
      return state.closedDates.find((d) => d.date === date)
    },

    isDateClosed: (state) => (date: string) => {
      return state.closedDates.some((d) => d.date === date)
    },
  },

  actions: {
    async fetchClosedDates(filters?: { year?: number; upcoming?: boolean }) {
      this.loading = true
      this.error = null
      try {
        const params: Record<string, string | number> = {}
        if (filters?.year) params.year = filters.year
        if (filters?.upcoming) params.upcoming = 'true'

        const response = await api.get('/closed-dates', { params })
        this.closedDates = response.data.data
        return this.closedDates
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to fetch closed dates'
        throw error
      } finally {
        this.loading = false
      }
    },

    async createClosedDate(data: { date: string; name: string; notes?: string }) {
      this.loading = true
      this.error = null
      try {
        const response = await api.post('/closed-dates', data)
        const newDate = response.data.data
        this.closedDates.push(newDate)
        this.closedDates.sort((a, b) => a.date.localeCompare(b.date))
        return newDate
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to create closed date'
        throw error
      } finally {
        this.loading = false
      }
    },

    async updateClosedDate(
      id: number,
      data: { date?: string; name?: string; notes?: string | null }
    ) {
      this.loading = true
      this.error = null
      try {
        const response = await api.put(`/closed-dates/${id}`, data)
        const updated = response.data.data

        const index = this.closedDates.findIndex((d) => d.id === id)
        if (index !== -1) {
          this.closedDates[index] = updated
        }
        this.closedDates.sort((a, b) => a.date.localeCompare(b.date))

        return updated
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to update closed date'
        throw error
      } finally {
        this.loading = false
      }
    },

    async deleteClosedDate(id: number) {
      this.loading = true
      this.error = null
      try {
        await api.delete(`/closed-dates/${id}`)
        this.closedDates = this.closedDates.filter((d) => d.id !== id)
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to delete closed date'
        throw error
      } finally {
        this.loading = false
      }
    },

    async checkDate(date: string): Promise<{ is_closed: boolean; closed_date: ClosedDate | null }> {
      try {
        const response = await api.get('/closed-dates/check', { params: { date } })
        return response.data
      } catch (error: any) {
        throw error
      }
    },

    clearError() {
      this.error = null
    },
  },
})
