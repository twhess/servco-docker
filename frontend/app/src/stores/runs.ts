import { defineStore } from 'pinia'
import { api } from 'src/boot/axios'
import type { RunInstance, RunNote } from 'src/types/runs'

interface RunFilters {
  date?: string
  status?: string
  runner_id?: number
  route_id?: number
}

interface RunsState {
  runs: RunInstance[]
  myRuns: RunInstance[]
  activeRun: RunInstance | null
  loading: boolean
  error: string | null
}

export const useRunsStore = defineStore('runs', {
  state: (): RunsState => ({
    runs: [],
    myRuns: [],
    activeRun: null,
    loading: false,
    error: null,
  }),

  getters: {
    upcomingRuns: (state) => state.runs.filter(r => r.status === 'pending'),
    inProgressRuns: (state) => state.runs.filter(r => r.status === 'in_progress'),
    completedRuns: (state) => state.runs.filter(r => r.status === 'completed'),

    getRunById: (state) => (id: number) => {
      return state.runs.find(r => r.id === id) || state.myRuns.find(r => r.id === id)
    },

    todayMyRuns: (state) => {
      const today = new Date().toISOString().split('T')[0]
      return state.myRuns.filter(r => r.scheduled_date === today)
    },
  },

  actions: {
    async fetchRuns(filters?: RunFilters) {
      this.loading = true
      this.error = null
      try {
        const response = await api.get('/runs', { params: filters })
        this.runs = response.data.data
        return this.runs
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to fetch runs'
        throw error
      } finally {
        this.loading = false
      }
    },

    async fetchMyRuns(date?: string) {
      this.loading = true
      this.error = null
      try {
        const params = date ? { date } : {}
        const response = await api.get('/runs/my-runs', { params })
        this.myRuns = response.data.data
        return this.myRuns
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to fetch my runs'
        throw error
      } finally {
        this.loading = false
      }
    },

    async fetchRun(id: number) {
      this.loading = true
      this.error = null
      try {
        const response = await api.get(`/runs/${id}`)
        this.activeRun = response.data.data

        // Update in runs list if exists
        const index = this.runs.findIndex(r => r.id === id)
        if (index !== -1) {
          this.runs[index] = this.activeRun
        }

        // Update in myRuns list if exists
        const myIndex = this.myRuns.findIndex(r => r.id === id)
        if (myIndex !== -1) {
          this.myRuns[myIndex] = this.activeRun
        }

        return this.activeRun
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to fetch run'
        throw error
      } finally {
        this.loading = false
      }
    },

    async assignRunner(runId: number, runnerId: number, vehicleId?: number) {
      this.loading = true
      this.error = null
      try {
        const response = await api.post(`/runs/${runId}/assign`, {
          assigned_runner_user_id: runnerId,
          assigned_vehicle_location_id: vehicleId,
        })
        const updatedRun = response.data.data

        this.updateRunInLists(updatedRun)
        return updatedRun
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to assign runner'
        throw error
      } finally {
        this.loading = false
      }
    },

    async startRun(runId: number) {
      this.loading = true
      this.error = null
      try {
        const response = await api.post(`/runs/${runId}/start`)
        const updatedRun = response.data.data

        this.updateRunInLists(updatedRun)
        return updatedRun
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to start run'
        throw error
      } finally {
        this.loading = false
      }
    },

    async completeRun(runId: number) {
      this.loading = true
      this.error = null
      try {
        const response = await api.post(`/runs/${runId}/complete`)
        const updatedRun = response.data.data

        this.updateRunInLists(updatedRun)
        return updatedRun
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to complete run'
        throw error
      } finally {
        this.loading = false
      }
    },

    async updateCurrentStop(runId: number, stopId: number, arrivedAt?: string) {
      this.loading = true
      this.error = null
      try {
        const response = await api.put(`/runs/${runId}/current-stop`, {
          current_stop_id: stopId,
          arrived_at: arrivedAt,
        })
        const updatedRun = response.data.data

        this.updateRunInLists(updatedRun)
        return updatedRun
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to update current stop'
        throw error
      } finally {
        this.loading = false
      }
    },

    async arriveAtStop(runId: number, stopId: number) {
      this.loading = true
      this.error = null
      try {
        const response = await api.post(`/runs/${runId}/stops/${stopId}/arrive`)
        const actual = response.data.data

        // Refresh run to get updated data
        await this.fetchRun(runId)
        return actual
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to record arrival'
        throw error
      } finally {
        this.loading = false
      }
    },

    async departFromStop(runId: number, stopId: number, force = false) {
      this.loading = true
      this.error = null
      try {
        const response = await api.post(`/runs/${runId}/stops/${stopId}/depart`, null, {
          params: { force },
        })

        // Check if warning returned
        if (response.data.warning) {
          this.loading = false
          return {
            warning: true,
            message: response.data.message,
            incomplete_tasks: response.data.incomplete_tasks,
          }
        }

        const actual = response.data.data

        // Refresh run to get updated data
        await this.fetchRun(runId)
        return actual
      } catch (error: any) {
        // Check if it's a warning about incomplete tasks
        if (error.response?.status === 400 && error.response.data.warning) {
          this.loading = false
          return {
            warning: true,
            message: error.response.data.message,
            incomplete_tasks: error.response.data.incomplete_tasks,
          }
        }

        this.error = error.response?.data?.message || 'Failed to record departure'
        throw error
      } finally {
        this.loading = false
      }
    },

    async addNote(runId: number, noteType: string, notes: string) {
      this.loading = true
      this.error = null
      try {
        const response = await api.post(`/runs/${runId}/notes`, {
          note_type: noteType,
          notes,
        })
        const newNote = response.data.data

        // Add to active run if loaded
        if (this.activeRun?.id === runId && this.activeRun.notes) {
          this.activeRun.notes.unshift(newNote)
        }

        return newNote
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to add note'
        throw error
      } finally {
        this.loading = false
      }
    },

    async fetchNotes(runId: number) {
      this.loading = true
      this.error = null
      try {
        const response = await api.get(`/runs/${runId}/notes`)
        const notes = response.data.data

        // Update active run if loaded
        if (this.activeRun?.id === runId) {
          this.activeRun.notes = notes
        }

        return notes
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to fetch notes'
        throw error
      } finally {
        this.loading = false
      }
    },

    // Helper method to update run in all lists
    updateRunInLists(updatedRun: RunInstance) {
      const index = this.runs.findIndex(r => r.id === updatedRun.id)
      if (index !== -1) {
        this.runs[index] = updatedRun
      }

      const myIndex = this.myRuns.findIndex(r => r.id === updatedRun.id)
      if (myIndex !== -1) {
        this.myRuns[myIndex] = updatedRun
      }

      if (this.activeRun?.id === updatedRun.id) {
        this.activeRun = updatedRun
      }
    },

    clearError() {
      this.error = null
    },

    clearActiveRun() {
      this.activeRun = null
    },
  },
})
